<?php
// Set page title
$page_title = "Sales Management";

// Include header
include_once '../includes/header.php';

// Define default values for filters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$salesRep = isset($_GET['sales_rep']) ? $_GET['sales_rep'] : '';

// Build the query based on filters
$query = "SELECT i.*, u.name, c.name as customer_name 
          FROM invoices i 
          LEFT JOIN users u ON i.user_id = u.user_id 
          LEFT JOIN customers c ON i.customer_id = c.customer_id 
          WHERE i.created_at BETWEEN ? AND ?";

$params = array($startDate, $endDate);
$types = "ss";

if ($status != 'all') {
    $query .= " AND i.payment_status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($salesRep)) {
    $query .= " AND i.user_id = ?";
    $params[] = $salesRep;
    $types .= "i";
}

$query .= " ORDER BY i.created_at DESC, i.invoice_id DESC";

// Prepare and execute the query
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Get all sales reps for the filter dropdown
$salesRepQuery = "SELECT user_id, name FROM users WHERE role = 'sales_rep'";
$salesRepResult = $conn->query($salesRepQuery);
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i> Sales Management</h5>
            </div>
            <div class="card-body">
                <form class="row g-3 mb-4" method="GET">
                    <div class="col-md-3">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="all" <?php echo ($status == 'all') ? 'selected' : ''; ?>>All</option>
                            <option value="paid" <?php echo ($status == 'paid') ? 'selected' : ''; ?>>Paid</option>
                            <option value="pending" <?php echo ($status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="cancelled" <?php echo ($status == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="sales_rep" class="form-label">Sales Rep</label>
                        <select class="form-select" id="sales_rep" name="sales_rep">
                            <option value="">All Sales Reps</option>
                            <?php while ($rep = $salesRepResult->fetch_assoc()): ?>
                                <option value="<?php echo $rep['user_id']; ?>" <?php echo ($salesRep == $rep['user_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($rep['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="salesTable">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Sales Rep</th>
                                <th>Total Amount</th>
                                <th>Payment Method</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                            ?>
                                    <tr>
                                        <td><?php echo $row['invoice_id']; ?></td>
                                        <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                        <td>
                                            <?php
                                            echo !empty($row['customer_name']) ?
                                                htmlspecialchars($row['customer_name']) :
                                                '<span class="text-muted">Walk-in customer</span>';
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td><?php echo number_format($row['total_amount'], 2); ?></td>
                                        <td>
                                            <?php
                                            // Get payment method from payments table
                                            $paymentMethodQuery = "SELECT payment_method FROM payments WHERE invoice_id = ?";
                                            $pmStmt = $conn->prepare($paymentMethodQuery);
                                            $pmStmt->bind_param("i", $row['invoice_id']);
                                            $pmStmt->execute();
                                            $pmResult = $pmStmt->get_result();
                                            if ($pmResult->num_rows > 0) {
                                                $pmRow = $pmResult->fetch_assoc();
                                                echo ucfirst(str_replace('_', ' ', $pmRow['payment_method']));
                                            } else {
                                                echo "Not specified";
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($row['payment_status'] == 'paid'): ?>
                                                <span class="badge bg-success">Completed</span>
                                            <?php elseif ($row['payment_status'] == 'cancelled'): ?>
                                                <span class="badge bg-danger">Cancelled</span>
                                            <?php elseif ($row['payment_status'] == 'pending'): ?>
                                                <span class="badge bg-warning">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="invoice_details.php?id=<?php echo $row['invoice_id']; ?>" class="btn btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="print_invoice.php?id=<?php echo $row['invoice_id']; ?>" target="_blank" class="btn btn-secondary">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                                <?php if ($row['payment_status'] == 'paid'): ?>
                                                    <button type="button" class="btn btn-danger cancel-invoice" data-id="<?php echo $row['invoice_id']; ?>">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                            <?php
                                }
                            } else {
                                echo '<tr><td colspan="8" class="text-center">No sales found for the selected filters</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0">Sales Summary</h6>
                            </div>
                            <div class="card-body">
                                <?php
                                // Calculate summary statistics
                                $summaryQuery = "SELECT 
                                    COUNT(*) as total_sales,
                                    SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as completed_sales,
                                    SUM(CASE WHEN payment_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_sales,
                                    SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) as total_revenue,
                                    AVG(CASE WHEN payment_status = 'paid' THEN total_amount ELSE NULL END) as avg_sale
                                    FROM invoices 
                                    WHERE created_at BETWEEN ? AND ?";

                                $summaryParams = array($startDate, $endDate);
                                $summaryTypes = "ss";

                                if ($status != 'all') {
                                    // Map status to payment_status values
                                    $paymentStatus = $status == 'completed' ? 'paid' : $status;
                                    $summaryQuery .= " AND payment_status = ?";
                                    $summaryParams[] = $paymentStatus;
                                    $summaryTypes .= "s";
                                }

                                if (!empty($salesRep)) {
                                    $summaryQuery .= " AND user_id = ?";
                                    $summaryParams[] = $salesRep;
                                    $summaryTypes .= "i";
                                }

                                $summaryStmt = $conn->prepare($summaryQuery);
                                $summaryStmt->bind_param($summaryTypes, ...$summaryParams);
                                $summaryStmt->execute();
                                $summaryResult = $summaryStmt->get_result();
                                $summary = $summaryResult->fetch_assoc();
                                ?>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <p class="text-muted mb-1">Total Sales</p>
                                        <h4><?php echo $summary['total_sales']; ?></h4>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <p class="text-muted mb-1">Completed Sales</p>
                                        <h4><?php echo $summary['completed_sales']; ?></h4>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <p class="text-muted mb-1">Cancelled Sales</p>
                                        <h4><?php echo $summary['cancelled_sales']; ?></h4>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <p class="text-muted mb-1">Total Revenue</p>
                                        <h4><?php echo number_format($summary['total_revenue'], 2); ?></h4>
                                    </div>
                                    <div class="col-md-12">
                                        <p class="text-muted mb-1">Average Sale</p>
                                        <h4><?php echo number_format($summary['avg_sale'], 2); ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0">Payment Methods</h6>
                            </div>
                            <div class="card-body">
                                <?php
                                // Payment methods breakdown
                                $paymentQuery = "SELECT 
                                    p.payment_method,
                                    COUNT(*) as count,
                                    SUM(i.total_amount) as total
                                    FROM invoices i
                                    JOIN payments p ON i.invoice_id = p.invoice_id
                                    WHERE i.payment_status = 'paid' AND i.created_at BETWEEN ? AND ?";

                                $paymentParams = array($startDate, $endDate);
                                $paymentTypes = "ss";

                                if (!empty($salesRep)) {
                                    $paymentQuery .= " AND i.user_id = ?";
                                    $paymentParams[] = $salesRep;
                                    $paymentTypes .= "i";
                                }

                                $paymentQuery .= " GROUP BY payment_method";

                                $paymentStmt = $conn->prepare($paymentQuery);
                                $paymentStmt->bind_param($paymentTypes, ...$paymentParams);
                                $paymentStmt->execute();
                                $paymentResult = $paymentStmt->get_result();
                                ?>

                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Payment Method</th>
                                                <th>Count</th>
                                                <th>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            if ($paymentResult->num_rows > 0) {
                                                while ($payment = $paymentResult->fetch_assoc()): ?>
                                                    <tr>
                                                        <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                                                        <td><?php echo $payment['count']; ?></td>
                                                        <td><?php echo number_format($payment['total'], 2); ?></td>
                                                    </tr>
                                            <?php endwhile;
                                            } else {
                                                echo '<tr><td colspan="3" class="text-center">No payment data available</td></tr>';
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Invoice Modal -->
<div class="modal fade" id="cancelInvoiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Cancel Invoice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel this invoice? This action cannot be undone and will affect inventory.</p>
                <form id="cancelInvoiceForm" method="post" action="cancel_invoice.php">
                    <input type="hidden" name="invoice_id" id="cancelInvoiceId">
                    <div class="mb-3">
                        <label for="cancelReason" class="form-label">Reason for Cancellation</label>
                        <textarea class="form-control" id="cancelReason" name="reason" rows="3" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" form="cancelInvoiceForm" class="btn btn-danger">Cancel Invoice</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize DataTable
    $(document).ready(function() {
        $('#salesTable').DataTable({
            "pageLength": 25,
            "order": [
                [1, 'desc']
            ], // Sort by date descending
            "responsive": true
        });

        // Handle cancel invoice button
        $('.cancel-invoice').click(function() {
            let invoiceId = $(this).data('id');
            $('#cancelInvoiceId').val(invoiceId);
            $('#cancelInvoiceModal').modal('show');
        });
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>