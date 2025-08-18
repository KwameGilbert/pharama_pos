<?php
// Set page title
$page_title = "Stock History";

// Include header
include_once '../includes/head.php';

// Check if product ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="alert alert-danger">Invalid product ID.</div>';
    include_once '../includes/footer.php';
    exit;
}

$product_id = $_GET['id'];

// Get product details
$query = "SELECT * FROM products WHERE product_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="alert alert-danger">Product not found.</div>';
    include_once '../includes/footer.php';
    exit;
}

$product = $result->fetch_assoc();

// Check if stock_adjustments table exists
$check_table = $conn->query("SHOW TABLES LIKE 'stock_adjustments'");
$has_adjustments = ($check_table->num_rows > 0);

// Get stock adjustment history
$history = [];
if ($has_adjustments) {
    $history_query = "SELECT sa.*, u.name as user_name 
                     FROM stock_adjustments sa 
                     LEFT JOIN users u ON sa.user_id = u.user_id 
                     WHERE sa.product_id = ? 
                     ORDER BY sa.created_at DESC";
    $history_stmt = $conn->prepare($history_query);
    $history_stmt->bind_param("i", $product_id);
    $history_stmt->execute();
    $history_result = $history_stmt->get_result();
}

// Get sales history
$sales_query = "SELECT ii.*, i.invoice_id, i.created_at, i.payment_status, u.name as user_name 
               FROM invoice_items ii 
               JOIN invoices i ON ii.invoice_id = i.invoice_id 
               LEFT JOIN users u ON i.user_id = u.user_id 
               WHERE ii.product_id = ? 
               ORDER BY i.created_at DESC";
$sales_stmt = $conn->prepare($sales_query);
$sales_stmt->bind_param("i", $product_id);
$sales_stmt->execute();
$sales_result = $sales_stmt->get_result();
?>

<div class="row mb-4">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="inventory.php">Inventory</a></li>
                <li class="breadcrumb-item active">Stock History</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i> Stock History</h5>
                <a href="inventory.php" class="btn btn-light btn-sm">
                    <i class="fas fa-arrow-left me-1"></i> Back to Inventory
                </a>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted">Product Information</h6>
                        <table class="table table-sm">
                            <tr>
                                <th width="30%">Product Name</th>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                            </tr>
                            <tr>
                                <th>Category</th>
                                <td><?php echo htmlspecialchars($product['category']); ?></td>
                            </tr>
                            <tr>
                                <th>Batch Number</th>
                                <td><?php echo htmlspecialchars($product['batch_no']); ?></td>
                            </tr>
                            <tr>
                                <th>Current Stock</th>
                                <td><?php echo $product['stock_qty']; ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted">Price & Expiry</h6>
                        <table class="table table-sm">
                            <tr>
                                <th width="30%">Cost Price</th>
                                <td><?php echo number_format($product['cost_price'], 2); ?></td>
                            </tr>
                            <tr>
                                <th>Selling Price</th>
                                <td><?php echo number_format($product['selling_price'], 2); ?></td>
                            </tr>
                            <tr>
                                <th>Reorder Level</th>
                                <td><?php echo $product['reorder_level']; ?></td>
                            </tr>
                            <tr>
                                <th>Expiry Date</th>
                                <td><?php echo date('F d, Y', strtotime($product['expiry_date'])); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Tab navigation -->
                <ul class="nav nav-tabs" role="tablist">
                    <?php if ($has_adjustments): ?>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link active" id="adjustments-tab" data-bs-toggle="tab" href="#adjustments" role="tab" aria-controls="adjustments" aria-selected="true">
                                Stock Adjustments
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo (!$has_adjustments) ? 'active' : ''; ?>" id="sales-tab" data-bs-toggle="tab" href="#sales" role="tab" aria-controls="sales" aria-selected="false">
                            Sales History
                        </a>
                    </li>
                </ul>

                <!-- Tab content -->
                <div class="tab-content mt-3">
                    <?php if ($has_adjustments): ?>
                        <div class="tab-pane fade show active" id="adjustments" role="tabpanel" aria-labelledby="adjustments-tab">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="adjustmentsTable">
                                    <thead>
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>Adjustment Type</th>
                                            <th>Previous Stock</th>
                                            <th>Adjusted Quantity</th>
                                            <th>New Stock</th>
                                            <th>User</th>
                                            <th>Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($history_result->num_rows > 0): ?>
                                            <?php while ($row = $history_result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y h:i A', strtotime($row['created_at'])); ?></td>
                                                    <td>
                                                        <?php
                                                        switch ($row['adjustment_type']) {
                                                            case 'add':
                                                                echo '<span class="badge bg-success">Added</span>';
                                                                break;
                                                            case 'subtract':
                                                                echo '<span class="badge bg-danger">Subtracted</span>';
                                                                break;
                                                            case 'set':
                                                                echo '<span class="badge bg-primary">Set Value</span>';
                                                                break;
                                                            default:
                                                                echo $row['adjustment_type'];
                                                        }
                                                        ?>
                                                    </td>
                                                    <td><?php echo $row['previous_qty']; ?></td>
                                                    <td><?php echo $row['adjusted_qty']; ?></td>
                                                    <td><?php echo $row['new_qty']; ?></td>
                                                    <td><?php echo htmlspecialchars($row['user_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['reason']); ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center">No adjustment history available</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="tab-pane fade <?php echo (!$has_adjustments) ? 'show active' : ''; ?>" id="sales" role="tabpanel" aria-labelledby="sales-tab">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="salesTable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Invoice #</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Subtotal</th>
                                        <th>Status</th>
                                        <th>Sold By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($sales_result->num_rows > 0): ?>
                                        <?php while ($row = $sales_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y h:i A', strtotime($row['created_at'])); ?></td>
                                                <td><?php echo $row['invoice_id']; ?></td>
                                                <td><?php echo $row['quantity']; ?></td>
                                                <td><?php echo number_format($row['price'], 2); ?></td>
                                                <td><?php echo number_format($row['subtotal'], 2); ?></td>
                                                <td>
                                                    <?php
                                                    switch ($row['payment_status']) {
                                                        case 'paid':
                                                            echo '<span class="badge bg-success">Paid</span>';
                                                            break;
                                                        case 'pending':
                                                            echo '<span class="badge bg-warning">Pending</span>';
                                                            break;
                                                        case 'cancelled':
                                                            echo '<span class="badge bg-danger">Cancelled</span>';
                                                            break;
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($row['user_name']); ?></td>
                                                <td>
                                                    <a href="../sales/invoice_details.php?id=<?php echo $row['invoice_id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No sales history available</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Initialize DataTables
        $('#adjustmentsTable').DataTable({
            "pageLength": 10,
            "responsive": true,
            "order": [
                [0, 'desc']
            ]
        });

        $('#salesTable').DataTable({
            "pageLength": 10,
            "responsive": true,
            "order": [
                [0, 'desc']
            ]
        });
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>