<?php
// Include header
include_once '../include/head.php';

// Set default filter values
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01'); // First day of current month
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d'); // Today
$status = isset($_GET['status']) ? $_GET['status'] : '';
$customer = isset($_GET['customer']) ? $_GET['customer'] : '';

// Build the query with user_id condition to only show their invoices
$query = "SELECT i.*, c.name as customer_name, p.payment_method 
          FROM invoices i
          LEFT JOIN customers c ON i.customer_id = c.customer_id
          LEFT JOIN payments p ON i.invoice_id = p.invoice_id
          WHERE i.user_id = '$user_id'";

// Apply filters
if (!empty($date_from)) {
    $query .= " AND DATE(i.created_at) >= '" . mysqli_real_escape_string($conn, $date_from) . "'";
}
if (!empty($date_to)) {
    $query .= " AND DATE(i.created_at) <= '" . mysqli_real_escape_string($conn, $date_to) . "'";
}
if (!empty($status)) {
    $query .= " AND i.payment_status = '" . mysqli_real_escape_string($conn, $status) . "'";
}
if (!empty($customer)) {
    $query .= " AND (c.name LIKE '%" . mysqli_real_escape_string($conn, $customer) . "%' OR c.phone LIKE '%" . mysqli_real_escape_string($conn, $customer) . "%')";
}

// Order by latest first
$query .= " ORDER BY i.created_at DESC";

// Execute query
$result = mysqli_query($conn, $query);

// Get customer list for filter dropdown
$customer_query = "SELECT customer_id, name, phone FROM customers ORDER BY name";
$customer_result = mysqli_query($conn, $customer_query);
?>

<!-- Invoices Page -->
<h2 class="mb-4">My Invoices</h2>

<!-- Filter Controls -->
<div class="dashboard-card bg-white mb-4">
    <form method="get" class="row g-3">
        <div class="col-md-3">
            <label for="date_from" class="form-label">Date From</label>
            <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
        </div>
        <div class="col-md-3">
            <label for="date_to" class="form-label">Date To</label>
            <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
        </div>
        <div class="col-md-2">
            <label for="status" class="form-label">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">All Statuses</option>
                <option value="paid" <?php echo $status == 'paid' ? 'selected' : ''; ?>>Paid</option>
                <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
        </div>
        <div class="col-md-2">
            <label for="customer" class="form-label">Customer</label>
            <input type="text" class="form-control" id="customer" name="customer" value="<?php echo htmlspecialchars($customer); ?>" placeholder="Name or Phone">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <div class="d-grid w-100">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter me-1"></i> Apply Filter
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Invoices Table -->
<div class="dashboard-card bg-white">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Amount</th>
                    <th>Payment Method</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($result) > 0): ?>
                    <?php while ($invoice = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><?php echo $invoice['invoice_id']; ?></td>
                            <td><?php echo date('M d, Y h:i A', strtotime($invoice['created_at'])); ?></td>
                            <td><?php echo $invoice['customer_name'] ?? 'Walk-in Customer'; ?></td>
                            <td>₵<?php echo number_format($invoice['total_amount'], 2); ?></td>
                            <td>
                                <?php
                                switch ($invoice['payment_method']) {
                                    case 'cash':
                                        echo '<i class="fas fa-money-bill text-success me-1"></i> Cash';
                                        break;
                                    case 'mobile_money':
                                        echo '<i class="fas fa-mobile-alt text-primary me-1"></i> Mobile Money';
                                        break;
                                    case 'paystack':
                                        echo '<i class="fas fa-credit-card text-info me-1"></i> Paystack';
                                        break;
                                    default:
                                        echo $invoice['payment_method'] ?? 'N/A';
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($invoice['payment_status'] == 'paid'): ?>
                                    <span class="badge bg-success">Paid</span>
                                <?php elseif ($invoice['payment_status'] == 'cancelled'): ?>
                                    <span class="badge bg-danger">Cancelled</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="invoice_details.php?id=<?php echo $invoice['invoice_id']; ?>" class="btn btn-sm btn-info" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="print_invoice.php?id=<?php echo $invoice['invoice_id']; ?>" class="btn btn-sm btn-secondary" title="Print">
                                    <i class="fas fa-print"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-4">No invoices found matching your criteria</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// Include footer
include_once '../include/footer.php';
?>