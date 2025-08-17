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

// Validate required fields
if (empty($data['name']) || empty($data['phone'])) {
    echo json_encode(['success' => false, 'message' => 'Name and phone are required']);
    exit;
}

// Sanitize inputs
$name = mysqli_real_escape_string($conn, $data['name']);
$phone = mysqli_real_escape_string($conn, $data['phone']);
$email = mysqli_real_escape_string($conn, $data['email'] ?? '');
$address = mysqli_real_escape_string($conn, $data['address'] ?? '');

// Check if customer with same phone already exists
$check_query = "SELECT * FROM customers WHERE phone = '$phone'";
$check_result = mysqli_query($conn, $check_query);

if (mysqli_num_rows($check_result) > 0) {
    echo json_encode(['success' => false, 'message' => 'A customer with this phone number already exists']);
    exit;
}

// Insert new customer
$query = "INSERT INTO customers (name, phone, email, address) 
          VALUES ('$name', '$phone', '$email', '$address')";

if (mysqli_query($conn, $query)) {
    $customer_id = mysqli_insert_id($conn);

    // Add to audit log
    $user_id = $_SESSION['user_id'];
    $action = "Added new customer: $name ($customer_id)";
    mysqli_query($conn, "INSERT INTO audit_logs (user_id, action) VALUES ('$user_id', '$action')");

    echo json_encode(['success' => true, 'customer_id' => $customer_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error creating customer: ' . mysqli_error($conn)]);
}
