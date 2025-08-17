<?php
// Include database connection
require_once '../../config/database.php';

// Start the session
session_start();

// Check if user is logged in as admin/manager
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/index.php");
    exit;
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $product_select = isset($_POST['product_select']) ? intval($_POST['product_select']) : 0;

    // Use product_select if product_id is not provided
    if ($product_id == 0 && $product_select > 0) {
        $product_id = $product_select;
    }

    $adjustment_type = $_POST['adjustment_type'];
    $quantity = intval($_POST['quantity']);
    $reason = trim($_POST['reason']);
    $user_id = $_SESSION['admin_id'];

    // Validate input
    if ($product_id <= 0 || $quantity <= 0 || empty($adjustment_type) || empty($reason)) {
        header("Location: inventory.php?error=" . urlencode("All fields are required."));
        exit;
    }

    // Get current stock level
    $query = "SELECT stock_qty, name FROM products WHERE product_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows != 1) {
        header("Location: inventory.php?error=" . urlencode("Product not found."));
        exit;
    }

    $product = $result->fetch_assoc();
    $current_stock = $product['stock_qty'];
    $product_name = $product['name'];
    $new_stock = 0;

    // Calculate new stock based on adjustment type
    switch ($adjustment_type) {
        case 'add':
            $new_stock = $current_stock + $quantity;
            break;
        case 'subtract':
            $new_stock = $current_stock - $quantity;
            // Prevent negative stock
            if ($new_stock < 0) {
                $new_stock = 0;
            }
            break;
        case 'set':
            $new_stock = $quantity;
            break;
        default:
            header("Location: inventory.php?error=" . urlencode("Invalid adjustment type."));
            exit;
    }

    // Begin transaction
    $conn->begin_transaction();

    try {
        // Update product stock
        $update_query = "UPDATE products SET stock_qty = ? WHERE product_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("ii", $new_stock, $product_id);
        $update_stmt->execute();

        // Log the stock adjustment
        // First check if stock_adjustments table exists
        $check_table = $conn->query("SHOW TABLES LIKE 'stock_adjustments'");
        if ($check_table->num_rows == 0) {
            // Create the table if it doesn't exist
            $conn->query("CREATE TABLE stock_adjustments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                user_id INT NOT NULL,
                adjustment_type VARCHAR(20) NOT NULL,
                previous_qty INT NOT NULL,
                adjusted_qty INT NOT NULL,
                new_qty INT NOT NULL,
                reason TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES products(product_id),
                FOREIGN KEY (user_id) REFERENCES users(user_id)
            )");
        }

        // Insert adjustment record
        $log_query = "INSERT INTO stock_adjustments 
                     (product_id, user_id, adjustment_type, previous_qty, adjusted_qty, new_qty, reason)
                     VALUES (?, ?, ?, ?, ?, ?, ?)";
        $log_stmt = $conn->prepare($log_query);
        $log_stmt->bind_param("iisiiis", $product_id, $user_id, $adjustment_type, $current_stock, $quantity, $new_stock, $reason);
        $log_stmt->execute();

        // Also log in audit_logs if the table exists
        $check_audit = $conn->query("SHOW TABLES LIKE 'audit_logs'");
        if ($check_audit->num_rows > 0) {
            $action = "Stock adjustment: $product_name - $adjustment_type $quantity units. Previous: $current_stock, New: $new_stock. Reason: $reason";
            $audit_query = "INSERT INTO audit_logs (user_id, action) VALUES (?, ?)";
            $audit_stmt = $conn->prepare($audit_query);
            $audit_stmt->bind_param("is", $user_id, $action);
            $audit_stmt->execute();
        }

        // Commit the transaction
        $conn->commit();

        // Redirect back with success message
        header("Location: inventory.php?success=" . urlencode("Stock updated successfully."));
        exit;
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();

        // Redirect with error message
        header("Location: inventory.php?error=" . urlencode("Error updating stock: " . $e->getMessage()));
        exit;
    }
} else {
    // If accessed directly without POST data
    header("Location: inventory.php");
    exit;
}
