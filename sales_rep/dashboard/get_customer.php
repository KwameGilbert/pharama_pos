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

// Check if customer ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid customer ID']);
    exit;
}

$customer_id = (int)$_GET['id'];

// Get customer details
$query = "SELECT * FROM customers WHERE customer_id = '$customer_id'";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) == 0) {
    echo json_encode(['success' => false, 'message' => 'Customer not found']);
    exit;
}

$customer = mysqli_fetch_assoc($result);

// Get customer's purchase history
$purchase_query = "SELECT * FROM invoices WHERE customer_id = '$customer_id' ORDER BY created_at DESC LIMIT 20";
$purchase_result = mysqli_query($conn, $purchase_query);

$purchases = [];
while ($row = mysqli_fetch_assoc($purchase_result)) {
    $purchases[] = $row;
}

// Return customer details and purchase history
echo json_encode([
    'success' => true,
    'customer' => $customer,
    'purchases' => $purchases
]);
