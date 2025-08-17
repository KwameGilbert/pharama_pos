<?php
// Start output buffering to prevent header issues
ob_start();

// Include the database connection
include_once '../../config/database.php';

// Start the session for user authentication if not already started
if (!session_id()) {
    session_start();
}

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/index.php");
    exit;
}

// Check if the product ID is provided
if (!isset($_POST['product_id']) || empty($_POST['product_id'])) {
    $_SESSION['error_msg'] = "No product specified for deletion";
    header("Location: products.php");
    exit;
}

$product_id = intval($_POST['product_id']);

// Get the product name for logging
$name_query = "SELECT name FROM products WHERE product_id = ?";
$name_stmt = $conn->prepare($name_query);
$name_stmt->bind_param("i", $product_id);
$name_stmt->execute();
$name_result = $name_stmt->get_result();

if ($name_result->num_rows === 0) {
    $_SESSION['error_msg'] = "Product not found";
    header("Location: products.php");
    exit;
}

$product = $name_result->fetch_assoc();
$product_name = $product['name'];

// Check if the product has been used in any invoices
$invoice_check_query = "SELECT COUNT(*) as count FROM invoice_items WHERE product_id = ?";
$invoice_check_stmt = $conn->prepare($invoice_check_query);
$invoice_check_stmt->bind_param("i", $product_id);
$invoice_check_stmt->execute();
$invoice_check_result = $invoice_check_stmt->get_result()->fetch_assoc();

if ($invoice_check_result['count'] > 0) {
    $_SESSION['error_msg'] = "Cannot delete product '{$product_name}' because it is referenced in sales records. Consider marking it as inactive instead.";
    header("Location: products.php");
    exit;
}

// Begin transaction to ensure all operations succeed or fail together
$conn->begin_transaction();

try {
    // Delete the product
    $delete_query = "DELETE FROM products WHERE product_id = ?";
    $delete_stmt = $conn->prepare($delete_query);
    $delete_stmt->bind_param("i", $product_id);

    if (!$delete_stmt->execute()) {
        throw new Exception("Error deleting product: " . $delete_stmt->error);
    }

    // Log the action
    $log_action = "Deleted product: $product_name (ID: $product_id)";
    $log_query = "INSERT INTO audit_logs (user_id, action) VALUES (?, ?)";
    $log_stmt = $conn->prepare($log_query);
    $log_stmt->bind_param("is", $_SESSION['admin_id'], $log_action);

    if (!$log_stmt->execute()) {
        throw new Exception("Error logging action: " . $log_stmt->error);
    }

    // Commit transaction
    $conn->commit();

    $_SESSION['success_msg'] = "Product '{$product_name}' has been deleted successfully";
} catch (Exception $e) {
    // Roll back transaction on error
    $conn->rollback();

    $_SESSION['error_msg'] = $e->getMessage();
}

// Redirect back to products page
header("Location: products.php");

// End output buffering before exit
ob_end_flush();
exit;
