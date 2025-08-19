<?php
require_once '../../config/database.php';
session_start();

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'];
    $name = trim($_POST['full_name']); // maps to "name" column
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $role = $_POST['role'];
    $status = $_POST['status'];

    $errors = [];

    // Validate user_id
    if (!isset($user_id) || !is_numeric($user_id)) {
        $errors[] = "Invalid user ID.";
    } else {
        $check_query = "SELECT user_id FROM users WHERE user_id = ? AND user_id != ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("ii", $user_id, $_SESSION['admin_id']);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows === 0) {
            $errors[] = "User not found or you cannot edit your own account here.";
        }
    }

    // Validate name
    if (empty($name)) {
        $errors[] = "Full name is required.";
    }

    // Validate email
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else {
        $check_query = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("si", $email, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $errors[] = "Email already exists. Please choose a different one.";
        }
    }

    // Validate password if provided
    if (!empty($password)) {
        if (strlen($password) < 4) {
            $errors[] = "Password must be at least 8 characters long.";
        } elseif ($password !== $confirm_password) {
            $errors[] = "Passwords do not match.";
        }
    }

    // Validate role
    if (!in_array($role, ['admin', 'sales_rep', 'manager'])) {
        $errors[] = "Invalid role selected.";
    }

    // Validate status
    if (!in_array($status, ['active', 'inactive'])) {
        $errors[] = "Invalid status selected.";
    }

    // If no errors, update user
    if (empty($errors)) {
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $query = "UPDATE users SET name = ?, email = ?, phone = ?, password = ?, role = ?, status = ? WHERE user_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssssssi", $name, $email, $phone, $hashed_password, $role, $status, $user_id);
        } else {
            $query = "UPDATE users SET name = ?, email = ?, phone = ?, role = ?, status = ? WHERE user_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssssi", $name, $email, $phone, $role, $status, $user_id);
        }

        if ($stmt->execute()) {
            header("Location: index.php?success=2");
            exit;
        } else {
            header("Location: index.php?error=" . urlencode("Error updating user: " . $conn->error));
            exit;
        }
    } else {
        $error_string = implode("<br>", $errors);
        header("Location: index.php?error=" . urlencode($error_string));
        exit;
    }
} else {
    header("Location: index.php");
    exit;
}
