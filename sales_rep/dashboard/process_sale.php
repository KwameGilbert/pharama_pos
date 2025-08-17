<?php
// Include database connection
require_once '../../config/database.php';

// Start session for authentication
session_start();

// Check if user is logged in as sales rep
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'sales_rep') {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get JSON input
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Basic validation
if (empty($data['products']) || !is_array($data['products'])) {
    echo json_encode(['success' => false, 'message' => 'No products in sale']);
    exit;
}

if (empty($data['payment']) || empty($data['payment']['method'])) {
    echo json_encode(['success' => false, 'message' => 'Payment information missing']);
    exit;
}

// Start transaction
mysqli_begin_transaction($conn);

try {
    $user_id = $_SESSION['user_id'];
    $customer_id = !empty($data['customer_id']) && $data['customer_id'] != '0' ? (int)$data['customer_id'] : null;
    $total_amount = (float)$data['final_amount'];

    // Insert invoice
    $query = "INSERT INTO invoices (customer_id, user_id, total_amount, payment_status) 
              VALUES (?, ?, ?, 'paid')";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "iid", $customer_id, $user_id, $total_amount);
    mysqli_stmt_execute($stmt);

    $invoice_id = mysqli_insert_id($conn);

    // Insert invoice items and update stock
    foreach ($data['products'] as $item) {
        $product_id = (int)$item['product_id'];
        $quantity = (int)$item['quantity'];
        $price = (float)$item['price'];
        $subtotal = (float)$item['subtotal'];

        // Check if product exists and has enough stock
        $product_query = "SELECT * FROM products WHERE product_id = ? AND stock_qty >= ?";
        $product_stmt = mysqli_prepare($conn, $product_query);
        mysqli_stmt_bind_param($product_stmt, "ii", $product_id, $quantity);
        mysqli_stmt_execute($product_stmt);
        $product_result = mysqli_stmt_get_result($product_stmt);

        if (mysqli_num_rows($product_result) == 0) {
            // Product not found or not enough stock
            throw new Exception("Product ID $product_id does not exist or has insufficient stock");
        }

        // Insert invoice item
        $item_query = "INSERT INTO invoice_items (invoice_id, product_id, quantity, price, subtotal) 
                      VALUES (?, ?, ?, ?, ?)";
        $item_stmt = mysqli_prepare($conn, $item_query);
        mysqli_stmt_bind_param($item_stmt, "iiidi", $invoice_id, $product_id, $quantity, $price, $subtotal);
        mysqli_stmt_execute($item_stmt);

        // Update product stock
        $update_query = "UPDATE products SET stock_qty = stock_qty - ? WHERE product_id = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, "ii", $quantity, $product_id);
        mysqli_stmt_execute($update_stmt);
    }

    // Record payment
    $payment_method = mysqli_real_escape_string($conn, $data['payment']['method']);
    $transaction_ref = mysqli_real_escape_string($conn, $data['payment']['transaction_ref'] ?? '');

    $payment_query = "INSERT INTO payments (invoice_id, payment_method, amount, transaction_ref) 
                      VALUES (?, ?, ?, ?)";
    $payment_stmt = mysqli_prepare($conn, $payment_query);
    mysqli_stmt_bind_param($payment_stmt, "isds", $invoice_id, $payment_method, $total_amount, $transaction_ref);
    mysqli_stmt_execute($payment_stmt);

    // Add to audit log
    $action = "Created invoice #$invoice_id for amount $total_amount";
    mysqli_query($conn, "INSERT INTO audit_logs (user_id, action) VALUES ('$user_id', '$action')");

    // Commit transaction
    mysqli_commit($conn);

    echo json_encode(['success' => true, 'invoice_id' => $invoice_id]);
} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
