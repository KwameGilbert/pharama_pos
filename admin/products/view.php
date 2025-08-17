<?php
// Set page title
$page_title = "View Product";

// Include header
include_once '../includes/header.php';

// Check if product ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_msg'] = "No product specified";
    header("Location: products.php");
    exit;
}

$product_id = intval($_GET['id']);

// Get product details with supplier information
$query = "SELECT p.*, s.name as supplier_name, s.contact_person, s.phone, s.email
          FROM products p
          LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
          WHERE p.product_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_msg'] = "Product not found";
    header("Location: products.php");
    exit;
}

$product = $result->fetch_assoc();

// Get sales history
$sales_query = "SELECT ii.quantity, ii.price, i.created_at, i.invoice_id, u.name as sold_by, c.name as customer
                FROM invoice_items ii
                JOIN invoices i ON ii.invoice_id = i.invoice_id
                LEFT JOIN users u ON i.user_id = u.user_id
                LEFT JOIN customers c ON i.customer_id = c.customer_id
                WHERE ii.product_id = ? AND i.payment_status = 'paid'
                ORDER BY i.created_at DESC
                LIMIT 10";
$sales_stmt = $conn->prepare($sales_query);
$sales_stmt->bind_param("i", $product_id);
$sales_stmt->execute();
$sales_result = $sales_stmt->get_result();

// Calculate stats
$stats_query = "SELECT 
                COUNT(*) as sales_count, 
                SUM(ii.quantity) as units_sold, 
                SUM(ii.subtotal) as total_revenue,
                MAX(i.created_at) as last_sold
                FROM invoice_items ii
                JOIN invoices i ON ii.invoice_id = i.invoice_id
                WHERE ii.product_id = ? AND i.payment_status = 'paid'";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $product_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// Check stock status
$stock_class = '';
$stock_status = 'In Stock';

if ($product['stock_qty'] <= 0) {
    $stock_class = 'danger';
    $stock_status = 'Out of Stock';
} elseif ($product['stock_qty'] <= $product['reorder_level']) {
    $stock_class = 'warning';
    $stock_status = 'Low Stock';
}

// Check expiry status
$expiry_class = '';
$expiry_status = '';

if (!empty($product['expiry_date'])) {
    $expiry_date = new DateTime($product['expiry_date']);
    $today = new DateTime();
    $diff = $today->diff($expiry_date);
    $days_to_expiry = $diff->invert ? -$diff->days : $diff->days;

    if ($days_to_expiry < 0) {
        $expiry_class = 'danger';
        $expiry_status = 'EXPIRED';
    } elseif ($days_to_expiry <= 30) {
        $expiry_class = 'warning';
        $expiry_status = "Expires in $days_to_expiry days";
    } else {
        $expiry_status = "Expires in $days_to_expiry days";
    }
}

// Calculate profit margin
$profit = $product['selling_price'] - $product['cost_price'];
$margin = ($product['selling_price'] > 0) ? ($profit / $product['selling_price']) * 100 : 0;
?>

<div class="container-fluid px-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Product Details</h4>
        <div>
            <a href="edit.php?id=<?php echo $product_id; ?>" class="btn btn-primary me-2">
                <i class="fas fa-edit"></i> Edit Product
            </a>
            <a href="products.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Products
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Product Details Card -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><?php echo htmlspecialchars($product['name']); ?></h5>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th style="width: 35%;">Product ID:</th>
                                    <td><?php echo $product['product_id']; ?></td>
                                </tr>
                                <tr>
                                    <th>Category:</th>
                                    <td><?php echo htmlspecialchars($product['category']); ?></td>
                                </tr>
                                <tr>
                                    <th>Batch Number:</th>
                                    <td><?php echo htmlspecialchars($product['batch_no']); ?></td>
                                </tr>
                                <tr>
                                    <th>Stock Status:</th>
                                    <td><span class="badge bg-<?php echo $stock_class; ?>"><?php echo $stock_status; ?></span></td>
                                </tr>
                                <tr>
                                    <th>Current Stock:</th>
                                    <td><?php echo $product['stock_qty']; ?></td>
                                </tr>
                                <tr>
                                    <th>Reorder Level:</th>
                                    <td><?php echo $product['reorder_level']; ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th style="width: 35%;">Cost Price:</th>
                                    <td>₵<?php echo number_format($product['cost_price'], 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Selling Price:</th>
                                    <td>₵<?php echo number_format($product['selling_price'], 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Profit:</th>
                                    <td>₵<?php echo number_format($profit, 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Margin:</th>
                                    <td><?php echo number_format($margin, 2); ?>%</td>
                                </tr>
                                <tr>
                                    <th>Expiry Date:</th>
                                    <td>
                                        <?php if (!empty($product['expiry_date'])): ?>
                                            <?php echo date('M d, Y', strtotime($product['expiry_date'])); ?>
                                            <?php if (!empty($expiry_status)): ?>
                                                <span class="badge bg-<?php echo $expiry_class; ?>"><?php echo $expiry_status; ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Created:</th>
                                    <td><?php echo date('M d, Y', strtotime($product['created_at'])); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Supplier Information -->
                    <div class="mt-4">
                        <h6 class="border-bottom pb-2">Supplier Information</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($product['supplier_name']); ?></p>
                                <p><strong>Contact Person:</strong> <?php echo htmlspecialchars($product['contact_person']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($product['phone']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($product['email']); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="mt-4">
                        <h6 class="border-bottom pb-2">Quick Actions</h6>
                        <div class="d-flex gap-2">
                            <a href="adjust_stock.php?id=<?php echo $product_id; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-plus-minus"></i> Adjust Stock
                            </a>
                            <a href="stock_history.php?id=<?php echo $product_id; ?>" class="btn btn-outline-info">
                                <i class="fas fa-history"></i> Stock History
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats and Sales History -->
        <div class="col-md-4">
            <!-- Stats Card -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Product Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <h5><?php echo $stats['sales_count'] ?: 0; ?></h5>
                            <p class="text-muted">Sales Transactions</p>
                        </div>
                        <div class="col-6 mb-3">
                            <h5><?php echo $stats['units_sold'] ?: 0; ?></h5>
                            <p class="text-muted">Units Sold</p>
                        </div>
                        <div class="col-6">
                            <h5>₵<?php echo number_format($stats['total_revenue'] ?: 0, 2); ?></h5>
                            <p class="text-muted">Total Revenue</p>
                        </div>
                        <div class="col-6">
                            <h5><?php echo $stats['last_sold'] ? date('M d, Y', strtotime($stats['last_sold'])) : 'N/A'; ?></h5>
                            <p class="text-muted">Last Sold</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sales History Card -->
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Recent Sales</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if ($sales_result->num_rows > 0): ?>
                            <?php while ($sale = $sales_result->fetch_assoc()): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">Invoice #<?php echo $sale['invoice_id']; ?></h6>
                                        <small><?php echo date('M d, Y', strtotime($sale['created_at'])); ?></small>
                                    </div>
                                    <p class="mb-1">
                                        <span class="badge bg-primary"><?php echo $sale['quantity']; ?> units</span> at
                                        <strong>₵<?php echo number_format($sale['price'], 2); ?></strong> each
                                    </p>
                                    <small class="text-muted">
                                        Sold by: <?php echo htmlspecialchars($sale['sold_by']); ?> |
                                        Customer: <?php echo htmlspecialchars($sale['customer']) ?: 'Walk-in'; ?>
                                    </small>
                                </div>
                            <?php endwhile; ?>
                            <div class="list-group-item text-center">
                                <a href="sales_history.php?product_id=<?php echo $product_id; ?>" class="btn btn-sm btn-outline-secondary">View All Sales</a>
                            </div>
                        <?php else: ?>
                            <div class="list-group-item text-center text-muted">
                                No sales history found for this product
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>