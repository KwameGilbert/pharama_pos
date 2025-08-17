<?php
// Include database connection
require_once '../../config/database.php';

// Start session for authentication
session_start();

// Check if user is logged in as sales rep
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'sales_rep') {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Check if search term is provided
if (!isset($_GET['term']) || empty($_GET['term'])) {
    echo json_encode([]);
    exit;
}

// Sanitize search term
$term = mysqli_real_escape_string($conn, $_GET['term']);

// Search for products by name, barcode, or ID
$query = "SELECT p.*, s.name as supplier_name 
          FROM products p
          LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
          WHERE (p.name LIKE '%$term%' OR p.batch_no = '$term' OR p.product_id = '$term')
          AND p.stock_qty > 0  
          ORDER BY p.name
          LIMIT 10";

$result = mysqli_query($conn, $query);

// Check for query execution error
if (!$result) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Database query failed: ' . mysqli_error($conn)]);
    exit;
}

// Fetch results
$products = [];
while ($row = mysqli_fetch_assoc($result)) {
    // Add expiry warning flag
    if (!empty($row['expiry_date'])) {
        $expiry = new DateTime($row['expiry_date']);
        $now = new DateTime();
        $diff = $now->diff($expiry);
        $days_to_expiry = $diff->invert ? -$diff->days : $diff->days;

        if ($days_to_expiry < 0) {
            $row['expiry_warning'] = 'expired';
        } elseif ($days_to_expiry <= 30) {
            $row['expiry_warning'] = 'near_expiry';
        }
    }

    $products[] = $row;
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($products);
