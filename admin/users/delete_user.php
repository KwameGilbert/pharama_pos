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

// Check if user ID is provided
if (isset($_GET['id'])) {
    $user_id = $_GET['id'];

    // Validate user_id
    if (!is_numeric($user_id)) {
        header("Location: index.php?error=" . urlencode("Invalid user ID."));
        exit;
    }

    // Check if user exists and is not the current admin (to prevent self-deletion)
    if ($user_id == $_SESSION['admin_id']) {
        header("Location: index.php?error=" . urlencode("You cannot delete your own account."));
        exit;
    }

    // Begin transaction
    $conn->begin_transaction();

    try {
        // First check if user has any associated data (sales, etc.)
        $check_query = "SELECT COUNT(*) as count FROM invoices WHERE user_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row['count'] > 0) {
            // If user has associated data, update their status to inactive instead of deleting
            $update_query = "UPDATE users SET status = 'inactive' WHERE user_id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("i", $user_id);
            $update_stmt->execute();

            // Commit the transaction
            $conn->commit();

            // Redirect with a message
            header("Location: index.php?error=" . urlencode("User could not be deleted because they have associated sales data. The user has been deactivated instead."));
            exit;
        } else {
            // Delete the user
            $query = "DELETE FROM users WHERE user_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();

            // Commit the transaction
            $conn->commit();

            // Redirect back to users page with success message
            header("Location: index.php?success=3");
            exit;
        }
    } catch (Exception $e) {
        // Roll back the transaction on error
        $conn->rollback();

        // Redirect with error
        header("Location: index.php?error=" . urlencode("Error deleting user: " . $e->getMessage()));
        exit;
    }
} else {
    // If accessed without required parameters
    header("Location: index.php");
    exit;
}
