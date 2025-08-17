<?php
// Include the database connection
include_once '../../config/database.php';

// Start the session for user authentication
session_start();

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/index.php");
    exit;
}

// Check if format is specified
if (!isset($_GET['format']) || ($_GET['format'] != 'csv' && $_GET['format'] != 'excel')) {
    $_SESSION['error_msg'] = "Invalid export format";
    header("Location: products.php");
    exit;
}

$format = $_GET['format'];

// Build query with filters (similar to products.php)
$query = "SELECT p.*, s.name as supplier_name 
          FROM products p
          LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
          WHERE 1=1";

// Apply filters if provided
if (isset($_GET['category']) && !empty($_GET['category'])) {
    $category = $_GET['category'];
    $query .= " AND p.category = '" . $conn->real_escape_string($category) . "'";
}

if (isset($_GET['supplier']) && !empty($_GET['supplier'])) {
    $supplier = $_GET['supplier'];
    $query .= " AND p.supplier_id = '" . $conn->real_escape_string($supplier) . "'";
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $_GET['search'];
    $query .= " AND (p.name LIKE '%" . $conn->real_escape_string($search) . "%' 
                OR p.batch_no LIKE '%" . $conn->real_escape_string($search) . "%')";
}

if (isset($_GET['stock']) && !empty($_GET['stock'])) {
    $stock = $_GET['stock'];
    if ($stock == 'low') {
        $query .= " AND p.stock_qty <= p.reorder_level AND p.stock_qty > 0";
    } elseif ($stock == 'out') {
        $query .= " AND p.stock_qty = 0";
    } elseif ($stock == 'available') {
        $query .= " AND p.stock_qty > 0";
    }
}

if (isset($_GET['expiry']) && !empty($_GET['expiry'])) {
    $expiry = $_GET['expiry'];
    if ($expiry == 'expired') {
        $query .= " AND p.expiry_date < CURDATE()";
    } elseif ($expiry == 'soon') {
        $query .= " AND p.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
    }
}

// Order by name
$query .= " ORDER BY p.name";

// Execute query
$result = $conn->query($query);

// Define the headers
$headers = [
    'product_id' => 'Product ID',
    'name' => 'Name',
    'category' => 'Category',
    'supplier_id' => 'Supplier ID',
    'supplier_name' => 'Supplier',
    'batch_no' => 'Batch No.',
    'expiry_date' => 'Expiry Date',
    'cost_price' => 'Cost Price',
    'selling_price' => 'Selling Price',
    'stock_qty' => 'Stock',
    'reorder_level' => 'Reorder Level',
    'created_at' => 'Created At'
];

// Generate filename with timestamp
$timestamp = date('Y-m-d_H-i-s');
$filename = "products_export_$timestamp";

// Set headers based on format
if ($format == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');

    // Create output stream
    $output = fopen('php://output', 'w');

    // Add UTF-8 BOM for Excel compatibility
    fputs($output, "\xEF\xBB\xBF");

    // Output the headers
    fputcsv($output, array_values($headers));

    // Output the data rows
    while ($row = $result->fetch_assoc()) {
        $data = [];
        foreach (array_keys($headers) as $field) {
            $data[] = $row[$field] ?? '';
        }
        fputcsv($output, $data);
    }

    fclose($output);
} elseif ($format == 'excel') {
    // For Excel, we'll use a simple CSV with a .xls extension
    // For a proper Excel file, you'd need a library like PhpSpreadsheet

    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');

    // Create HTML table output for Excel
    echo '<table border="1">';

    // Output headers
    echo '<tr>';
    foreach (array_values($headers) as $header) {
        echo '<th>' . htmlspecialchars($header) . '</th>';
    }
    echo '</tr>';

    // Output data rows
    while ($row = $result->fetch_assoc()) {
        echo '<tr>';
        foreach (array_keys($headers) as $field) {
            echo '<td>' . htmlspecialchars($row[$field] ?? '') . '</td>';
        }
        echo '</tr>';
    }

    echo '</table>';
}

// Log the export
$log_action = "Exported products in $format format";
$log_query = "INSERT INTO audit_logs (user_id, action) VALUES (?, ?)";
$log_stmt = $conn->prepare($log_query);
$log_stmt->bind_param("is", $_SESSION['admin_id'], $log_action);
$log_stmt->execute();

exit; // End processing after outputting the file
