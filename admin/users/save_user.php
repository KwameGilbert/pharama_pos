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
    $name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $role = $_POST['role'] == 'admin' ? 'manager' : 'sales_rep'; // Convert to match the schema
    $status = $_POST['status'];

    // Validate input
    $errors = [];

    // Validate name
    if (empty($name)) {
        $errors[] = "Full name is required.";
    }

    // Validate email (used as username)
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else {
        // Check if email already exists
        $check_query = "SELECT user_id FROM users WHERE email = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $errors[] = "Email already exists. Please choose a different one.";
        }
    }

    // Validate phone
    if (!empty($phone) && !preg_match('/^[0-9+\-\s()]{7,15}$/', $phone)) {
        $errors[] = "Invalid phone number format.";
    }    // Validate password
    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    } elseif ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // Validate role
    if (!in_array($role, ['admin', 'sales_rep'])) {
        $errors[] = "Invalid role selected.";
    }

    // Validate status
    if (!in_array($status, ['active', 'inactive'])) {
        $errors[] = "Invalid status selected.";
    }

    // If there are no errors, insert the new user
    if (empty($errors)) {
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert user into the database
        $query = "INSERT INTO users (role, name, email, phone, password, status, created_at) 
                  VALUES (?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssss", $role, $name, $email, $phone, $hashed_password, $status);
        if ($stmt->execute()) {
            // Redirect back to users page with success message
            header("Location: index.php?success=1");
            exit;
        } else {
            // If there was an error with the query
            header("Location: index.php?error=" . urlencode("Error creating user: " . $conn->error));
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
