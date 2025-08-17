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

// Check if user ID and status are provided
if (isset($_GET['id']) && isset($_GET['status'])) {
    $user_id = $_GET['id'];
    $status = $_GET['status'];
    
    // Validate user_id
    if (!is_numeric($user_id)) {
        header("Location: index.php?error=" . urlencode("Invalid user ID."));
        exit;
    }
    
    // Validate status
    if (!in_array($status, ['active', 'inactive'])) {
        header("Location: index.php?error=" . urlencode("Invalid status."));
        exit;
    }
    
    // Check if user exists and is not the current admin (to prevent self-deactivation)
    if ($user_id == $_SESSION['admin_id']) {
        header("Location: index.php?error=" . urlencode("You cannot change your own status."));
        exit;
    }
    
    // Update the user status
    $query = "UPDATE users SET status = ? WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $status, $user_id);
    
    if ($stmt->execute()) {
        // Redirect back to users page with success message
        header("Location: index.php?success=4");
        exit;
    } else {
        // If there was an error with the query
        header("Location: index.php?error=" . urlencode("Error updating user status: " . $conn->error));
        exit;
    }
} else {
    // If accessed without required parameters
    header("Location: index.php");
    exit;
}
?>
