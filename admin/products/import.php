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

// Define function to validate and sanitize data
function validateProduct($product)
{
    global $conn;

    $errors = [];

    // Required fields
    if (empty($product['name'])) {
        $errors[] = "Product name is required";
    }

    if (empty($product['category'])) {
        $errors[] = "Category is required";
    }

    // Numeric fields
    if (!is_numeric($product['cost_price']) || $product['cost_price'] <= 0) {
        $errors[] = "Cost price must be a positive number";
    }

    if (!is_numeric($product['selling_price']) || $product['selling_price'] <= 0) {
        $errors[] = "Selling price must be a positive number";
    }

    if (!is_numeric($product['stock_qty']) || $product['stock_qty'] < 0) {
        $errors[] = "Stock quantity cannot be negative";
    }

    // Check supplier exists
    if (!empty($product['supplier_id'])) {
        $supplier_check = $conn->prepare("SELECT supplier_id FROM suppliers WHERE supplier_id = ?");
        $supplier_check->bind_param("i", $product['supplier_id']);
        $supplier_check->execute();
        if ($supplier_check->get_result()->num_rows === 0) {
            $errors[] = "Invalid supplier ID: " . $product['supplier_id'];
        }
    } else {
        $errors[] = "Supplier is required";
    }

    // Expiry date validation if provided
    if (!empty($product['expiry_date'])) {
        if (!DateTime::createFromFormat('Y-m-d', $product['expiry_date'])) {
            $errors[] = "Invalid expiry date format. Use YYYY-MM-DD.";
        }
    }

    return $errors;
}

// Process the import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['importFile'])) {
    $file = $_FILES['importFile'];
    $update_existing = isset($_POST['updateExisting']);

    // Check for errors
    if ($file['error'] > 0) {
        $_SESSION['error_msg'] = "File upload error: " . $file['error'];
        header("Location: products.php");
        exit;
    }

    // Check file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($extension !== 'csv' && $extension !== 'xlsx') {
        $_SESSION['error_msg'] = "Invalid file type. Please upload a CSV or Excel file.";
        header("Location: products.php");
        exit;
    }

    // Process CSV file
    if ($extension === 'csv') {
        $handle = fopen($file['tmp_name'], "r");
        if (!$handle) {
            $_SESSION['error_msg'] = "Could not open the CSV file.";
            header("Location: products.php");
            exit;
        }

        // Read headers and validate required columns
        $headers = fgetcsv($handle);
        $required_columns = ['name', 'category', 'supplier_id', 'cost_price', 'selling_price', 'stock_qty'];
        foreach ($required_columns as $column) {
            if (!in_array($column, $headers)) {
                $_SESSION['error_msg'] = "Missing required column: $column";
                header("Location: products.php");
                exit;
            }
        }

        // Map column names to indexes
        $column_indexes = [];
        foreach ($headers as $index => $header) {
            $column_indexes[$header] = $index;
        }

        // Begin transaction
        $conn->begin_transaction();

        // Prepare statements
        $insert_stmt = $conn->prepare("INSERT INTO products (name, category, supplier_id, batch_no, expiry_date, cost_price, selling_price, stock_qty, reorder_level) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $update_stmt = $conn->prepare("UPDATE products SET name = ?, category = ?, supplier_id = ?, batch_no = ?, expiry_date = ?, cost_price = ?, selling_price = ?, stock_qty = ?, reorder_level = ? WHERE product_id = ?");

        $row_count = 0;
        $success_count = 0;
        $error_count = 0;
        $errors = [];

        // Process each row
        while (($data = fgetcsv($handle)) !== FALSE) {
            $row_count++;

            // Create product array from CSV data
            $product = [
                'name' => trim($data[$column_indexes['name']]),
                'category' => trim($data[$column_indexes['category']]),
                'supplier_id' => intval($data[$column_indexes['supplier_id']]),
                'batch_no' => isset($column_indexes['batch_no']) ? trim($data[$column_indexes['batch_no']]) : '',
                'expiry_date' => isset($column_indexes['expiry_date']) && !empty($data[$column_indexes['expiry_date']]) ? trim($data[$column_indexes['expiry_date']]) : NULL,
                'cost_price' => floatval($data[$column_indexes['cost_price']]),
                'selling_price' => floatval($data[$column_indexes['selling_price']]),
                'stock_qty' => intval($data[$column_indexes['stock_qty']]),
                'reorder_level' => isset($column_indexes['reorder_level']) ? intval($data[$column_indexes['reorder_level']]) : 5
            ];

            // Validate the product
            $validation_errors = validateProduct($product);
            if (!empty($validation_errors)) {
                $errors[] = "Row $row_count: " . implode(", ", $validation_errors);
                $error_count++;
                continue;
            }

            // Check if product already exists (by name)
            $check_stmt = $conn->prepare("SELECT product_id FROM products WHERE name = ?");
            $check_stmt->bind_param("s", $product['name']);
            $check_stmt->execute();
            $result = $check_stmt->get_result();

            try {
                if ($result->num_rows > 0 && $update_existing) {
                    // Update existing product
                    $row = $result->fetch_assoc();
                    $product_id = $row['product_id'];

                    $update_stmt->bind_param("ssissddii", $product['name'], $product['category'], $product['supplier_id'], $product['batch_no'], $product['expiry_date'], $product['cost_price'], $product['selling_price'], $product['stock_qty'], $product['reorder_level'], $product_id);
                    $update_stmt->execute();

                    // Log the update
                    $log_action = "Updated product via import: " . $product['name'] . " (ID: $product_id)";
                } else if ($result->num_rows === 0) {
                    // Insert new product
                    $insert_stmt->bind_param("ssissddii", $product['name'], $product['category'], $product['supplier_id'], $product['batch_no'], $product['expiry_date'], $product['cost_price'], $product['selling_price'], $product['stock_qty'], $product['reorder_level']);
                    $insert_stmt->execute();

                    $product_id = $conn->insert_id;
                    $log_action = "Added product via import: " . $product['name'] . " (ID: $product_id)";
                } else {
                    // Skip if product exists and update_existing is false
                    $errors[] = "Row $row_count: Product already exists and update option was not selected";
                    $error_count++;
                    continue;
                }

                // Log the action
                $log_query = "INSERT INTO audit_logs (user_id, action) VALUES (?, ?)";
                $log_stmt = $conn->prepare($log_query);
                $log_stmt->bind_param("is", $_SESSION['admin_id'], $log_action);
                $log_stmt->execute();

                $success_count++;
            } catch (Exception $e) {
                $errors[] = "Row $row_count: " . $e->getMessage();
                $error_count++;
            }
        }

        fclose($handle);

        // Commit or rollback based on results
        if ($error_count === 0) {
            $conn->commit();
            $_SESSION['success_msg'] = "Import completed successfully. $success_count products processed.";
        } else {
            $conn->rollback();
            $_SESSION['error_msg'] = "Import failed with $error_count errors: " . implode("; ", $errors);
        }
    } else {
        // Excel file processing would require a library like PhpSpreadsheet
        $_SESSION['error_msg'] = "Excel file import requires PHPSpreadsheet library. Please convert to CSV and try again.";
    }

    header("Location: products.php");
    exit;
}

// If direct access without form submission
$_SESSION['error_msg'] = "Invalid request";
header("Location: products.php");
exit;
