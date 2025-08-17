<?php
// Start session
session_start();

// Include database connection
require_once '../../config/database.php';

// Log logout
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $action = "User logged out";
    mysqli_query($conn, "INSERT INTO audit_logs (user_id, action) VALUES ('$user_id', '$action')");
}

// Destroy session
session_destroy();

// Redirect to login page
header("Location: index.php");
exit;
