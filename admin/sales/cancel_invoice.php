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
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['invoice_id']) && isset($_POST['reason'])) {
    $invoice_id = $_POST['invoice_id'];
    $reason = $_POST['reason'];
    $user_id = $_SESSION['admin_id'];
    $username = $_SESSION['username'];
    $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : '';

    // Begin transaction
    $conn->begin_transaction();

    try {
        // First check if invoice exists and is not already cancelled
        $check_query = "SELECT id, status FROM invoices WHERE id = ? AND status = 'completed'";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("i", $invoice_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows == 0) {
            throw new Exception("Invoice not found or already cancelled.");
        }

        // Get all items in the invoice to restore stock
        $items_query = "SELECT product_id, quantity FROM invoice_items WHERE invoice_id = ?";
        $items_stmt = $conn->prepare($items_query);
        $items_stmt->bind_param("i", $invoice_id);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();

        // Update each product's stock
        while ($item = $items_result->fetch_assoc()) {
            $update_stock_query = "UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?";
            $update_stock_stmt = $conn->prepare($update_stock_query);
            $update_stock_stmt->bind_param("ii", $item['quantity'], $item['product_id']);
            $update_stock_stmt->execute();
            $update_stock_stmt->close();
        }

        // Update invoice status
        $update_query = "UPDATE invoices SET status = 'cancelled', cancel_reason = ?, updated_at = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("si", $reason, $invoice_id);
        $update_stmt->execute();

        // Log the action if you have a logging table
        if ($conn->query("SHOW TABLES LIKE 'invoice_logs'")->num_rows > 0) {
            $log_query = "INSERT INTO invoice_logs (invoice_id, action, user_id, username, details) VALUES (?, 'cancelled', ?, ?, ?)";
            $log_stmt = $conn->prepare($log_query);
            $log_stmt->bind_param("iiss", $invoice_id, $user_id, $username, $reason);
            $log_stmt->execute();
            $log_stmt->close();
        }

        // Commit the transaction
        $conn->commit();

        // Redirect based on where the cancellation was initiated
        if ($redirect == 'details') {
            header("Location: invoice_details.php?id=$invoice_id&success=1");
        } else {
            header("Location: index.php?success=1");
        }
        exit;
    } catch (Exception $e) {
        // Roll back the transaction on error
        $conn->rollback();

        // Redirect with error
        if ($redirect == 'details') {
            header("Location: invoice_details.php?id=$invoice_id&error=" . urlencode($e->getMessage()));
        } else {
            header("Location: index.php?error=" . urlencode($e->getMessage()));
        }
        exit;
    }
} else {
    // If accessed directly without proper data
    header("Location: index.php");
    exit;
}
