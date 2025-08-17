<?php
// Set page title
$page_title = "Product Sales History";

// Include header
include_once '../includes/header.php';

// Check if product ID is provided
if (!isset($_GET['product_id']) || !is_numeric($_GET['product_id'])) {
    echo '<div class="alert alert-danger">Invalid product ID.</div>';
    include_once '../includes/footer.php';
    exit;
}

$product_id = intval($_GET['product_id']);

// Get product details
$product_query = "SELECT * FROM products WHERE product_id = ?";
$product_stmt = $conn->prepare($product_query);
$product_stmt->bind_param("i", $product_id);
$product_stmt->execute();
$product_result = $product_stmt->get_result();

if ($product_result->num_rows === 0) {
    echo '<div class="alert alert-danger">Product not found.</div>';
    include_once '../includes/footer.php';
    exit;
}

$product = $product_result->fetch_assoc();

// Set default filter values
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-90 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get sales history with filters
$query = "SELECT ii.invoice_item_id, ii.quantity, ii.price, ii.subtotal, 
          i.invoice_id, i.created_at, i.payment_status,
          u.name as sold_by, c.name as customer_name
          FROM invoice_items ii
          JOIN invoices i ON ii.invoice_id = i.invoice_id
          LEFT JOIN users u ON i.user_id = u.user_id
          LEFT JOIN customers c ON i.customer_id = c.customer_id
          WHERE ii.product_id = ? AND i.created_at BETWEEN ? AND ?
          ORDER BY i.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("iss", $product_id, $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

// Get sales summary
$summary_query = "SELECT 
                 COUNT(DISTINCT i.invoice_id) as total_invoices,
                 SUM(ii.quantity) as total_quantity,
                 SUM(ii.subtotal) as total_revenue,
                 AVG(ii.price) as average_price
                 FROM invoice_items ii
                 JOIN invoices i ON ii.invoice_id = i.invoice_id
                 WHERE ii.product_id = ? AND i.payment_status = 'paid' AND i.created_at BETWEEN ? AND ?";

$summary_stmt = $conn->prepare($summary_query);
$summary_stmt->bind_param("iss", $product_id, $startDate, $endDate);
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc();
?>

<div class="container-fluid px-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Sales History for <?php echo htmlspecialchars($product['name']); ?></h4>
        <div>
            <a href="view.php?id=<?php echo $product_id; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Product
            </a>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">

                <div class="col-md-4">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                </div>

                <div class="col-md-4">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                </div>

                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Apply Filter</button>
                    <a href="sales_history.php?product_id=<?php echo $product_id; ?>" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Card -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card bg-light">
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <h5><?php echo number_format($summary['total_invoices']); ?></h5>
                            <p class="text-muted mb-0">Total Sales</p>
                        </div>
                        <div class="col-md-3">
                            <h5><?php echo number_format($summary['total_quantity']); ?></h5>
                            <p class="text-muted mb-0">Units Sold</p>
                        </div>
                        <div class="col-md-3">
                            <h5>₵<?php echo number_format($summary['total_revenue'], 2); ?></h5>
                            <p class="text-muted mb-0">Total Revenue</p>
                        </div>
                        <div class="col-md-3">
                            <h5>₵<?php echo number_format($summary['average_price'], 2); ?></h5>
                            <p class="text-muted mb-0">Average Price</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales History Table -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Sales Records</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="salesHistoryTable">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Sold By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <a href="../sales/invoice_details.php?id=<?php echo $row['invoice_id']; ?>" class="text-primary">
                                            #<?php echo $row['invoice_id']; ?>
                                        </a>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                    <td><?php echo !empty($row['customer_name']) ? htmlspecialchars($row['customer_name']) : '<span class="text-muted">Walk-in customer</span>'; ?></td>
                                    <td><?php echo $row['quantity']; ?></td>
                                    <td>₵<?php echo number_format($row['price'], 2); ?></td>
                                    <td>₵<?php echo number_format($row['subtotal'], 2); ?></td>
                                    <td>
                                        <?php if ($row['payment_status'] == 'paid'): ?>
                                            <span class="badge bg-success">Paid</span>
                                        <?php elseif ($row['payment_status'] == 'cancelled'): ?>
                                            <span class="badge bg-danger">Cancelled</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['sold_by']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">No sales records found for this product in the selected date range.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize DataTable
    $(document).ready(function() {
        $('#salesHistoryTable').DataTable({
            "pageLength": 25,
            "order": [
                [1, 'desc']
            ], // Sort by date descending
            "responsive": true
        });
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>