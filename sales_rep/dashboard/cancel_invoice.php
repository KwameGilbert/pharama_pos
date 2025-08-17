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

// Validate input
if (!isset($data['invoice_id']) || !is_numeric($data['invoice_id']) || empty($data['reason'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$invoice_id = (int)$data['invoice_id'];
$user_id = $_SESSION['user_id'];
$reason = mysqli_real_escape_string($conn, $data['reason']);

// Verify this is the user's own invoice
$verify_query = "SELECT * FROM invoices WHERE invoice_id = '$invoice_id' AND user_id = '$user_id'";
$verify_result = mysqli_query($conn, $verify_query);

if (mysqli_num_rows($verify_result) == 0) {
    echo json_encode(['success' => false, 'message' => 'Invoice not found or you do not have permission to cancel it']);
    exit;
}

$invoice = mysqli_fetch_assoc($verify_result);

// Check if invoice is already cancelled
if ($invoice['payment_status'] == 'cancelled') {
    echo json_encode(['success' => false, 'message' => 'Invoice is already cancelled']);
    exit;
}

// Create a cancellation request in audit_logs
$action = "CANCELLATION REQUEST: Invoice #$invoice_id - $reason";
$log_query = "INSERT INTO audit_logs (user_id, action) VALUES ('$user_id', '$action')";

if (mysqli_query($conn, $log_query)) {
    echo json_encode(['success' => true, 'message' => 'Cancellation request submitted for manager approval']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error creating cancellation request: ' . mysqli_error($conn)]);
}
