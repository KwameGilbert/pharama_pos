<?php
// Include database connection
require_once '../../config/database.php';

// Start the session
session_start();

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/index.php");
    exit;
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $user_id = $_POST['user_id'];
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $role = $_POST['role'];
    $status = $_POST['status'];

    // Validate input
    $errors = [];

    // Validate user_id
    if (!isset($user_id) || !is_numeric($user_id)) {
        $errors[] = "Invalid user ID.";
    } else {
        // Check if user exists and is not the current admin (to prevent self-editing)
        $check_query = "SELECT id FROM users WHERE id = ? AND id != ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("ii", $user_id, $_SESSION['admin_id']);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows === 0) {
            $errors[] = "User not found or you cannot edit your own account from here.";
        }
    }

    // Validate full name
    if (empty($full_name)) {
        $errors[] = "Full name is required.";
    }

    // Validate username
    if (empty($username)) {
        $errors[] = "Username is required.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "Username can only contain letters, numbers, and underscores.";
    } else {
        // Check if username already exists for a different user
        $check_query = "SELECT id FROM users WHERE username = ? AND id != ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("si", $username, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $errors[] = "Username already exists. Please choose a different one.";
        }
    }

    // Validate email
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else {
        // Check if email already exists for a different user
        $check_query = "SELECT id FROM users WHERE email = ? AND id != ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("si", $email, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $errors[] = "Email already exists. Please choose a different one.";
        }
    }

    // Validate password (only if provided)
    if (!empty($password)) {
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long.";
        } elseif ($password !== $confirm_password) {
            $errors[] = "Passwords do not match.";
        }
    }

    // Validate role
    if (!in_array($role, ['admin', 'sales_rep'])) {
        $errors[] = "Invalid role selected.";
    }

    // Validate status
    if (!in_array($status, ['active', 'inactive'])) {
        $errors[] = "Invalid status selected.";
    }

    // If there are no errors, update the user
    if (empty($errors)) {
        // Start building the query
        if (!empty($password)) {
            // Hash the new password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Update with new password
            $query = "UPDATE users SET full_name = ?, username = ?, email = ?, password = ?, role = ?, status = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssssssi", $full_name, $username, $email, $hashed_password, $role, $status, $user_id);
        } else {
            // Update without changing the password
            $query = "UPDATE users SET full_name = ?, username = ?, email = ?, role = ?, status = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssssi", $full_name, $username, $email, $role, $status, $user_id);
        }

        if ($stmt->execute()) {
            // Redirect back to users page with success message
            header("Location: index.php?success=2");
            exit;
        } else {
            // If there was an error with the query
            header("Location: index.php?error=" . urlencode("Error updating user: " . $conn->error));
            exit;
        }
    } else {
        // Redirect back with errors
        $error_string = implode("<br>", $errors);
        header("Location: index.php?error=" . urlencode($error_string));
        exit;
    }
} else {
    // If accessed directly without POST data
    header("Location: index.php");
    exit;
}
