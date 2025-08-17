<?php
// Include header
include_once '../include/header.php';

// Get today's date in Y-m-d format
$today = date('Y-m-d');

// Query for today's total sales amount
$sales_query = "SELECT SUM(total_amount) as today_sales 
                FROM invoices 
                WHERE user_id = '$user_id' 
                AND DATE(created_at) = '$today'
                AND payment_status = 'paid'";
$sales_result = mysqli_query($conn, $sales_query);
$sales_row = mysqli_fetch_assoc($sales_result);
$today_sales = $sales_row['today_sales'] ?? 0;

// Query for today's total invoices
$invoice_query = "SELECT COUNT(*) as today_invoices 
                  FROM invoices 
                  WHERE user_id = '$user_id' 
                  AND DATE(created_at) = '$today'";
$invoice_result = mysqli_query($conn, $invoice_query);
$invoice_row = mysqli_fetch_assoc($invoice_result);
$today_invoices = $invoice_row['today_invoices'] ?? 0;

// Query for monthly sales data (for chart)
$monthly_query = "SELECT DATE(created_at) as sale_date, SUM(total_amount) as daily_total 
                  FROM invoices 
                  WHERE user_id = '$user_id' 
                  AND MONTH(created_at) = MONTH(CURRENT_DATE())
                  AND YEAR(created_at) = YEAR(CURRENT_DATE())
                  AND payment_status = 'paid'
                  GROUP BY DATE(created_at)
                  ORDER BY DATE(created_at) ASC
                  LIMIT 30";
$monthly_result = mysqli_query($conn, $monthly_query);

// Prepare data for chart
$dates = [];
$sales = [];
while ($row = mysqli_fetch_assoc($monthly_result)) {
    $dates[] = date('d M', strtotime($row['sale_date']));
    $sales[] = $row['daily_total'];
}

// Get recent invoices
$recent_invoices_query = "SELECT i.*, c.name as customer_name 
                         FROM invoices i
                         LEFT JOIN customers c ON i.customer_id = c.customer_id
                         WHERE i.user_id = '$user_id'
                         ORDER BY i.created_at DESC
                         LIMIT 5";
$recent_invoices_result = mysqli_query($conn, $recent_invoices_query);
?>

<!-- Dashboard Content -->
<h2 class="mb-4">Sales Rep Dashboard</h2>

<!-- Stats Summary -->
<div class="row">
    <div class="col-md-6 col-lg-4">
        <div class="dashboard-card bg-white">
            <h5><i class="fas fa-dollar-sign text-success me-2"></i> Today's Sales</h5>
            <h3 class="display-5"><?php echo number_format($today_sales, 2); ?></h3>
            <p class="text-muted">Total sales amount for today</p>
        </div>
    </div>

    <div class="col-md-6 col-lg-4">
        <div class="dashboard-card bg-white">
            <h5><i class="fas fa-file-invoice text-primary me-2"></i> Today's Invoices</h5>
            <h3 class="display-5"><?php echo $today_invoices; ?></h3>
            <p class="text-muted">Number of invoices generated today</p>
        </div>
    </div>

    <div class="col-md-6 col-lg-4">
        <div class="dashboard-card bg-white">
            <a href="pos.php" class="text-decoration-none">
                <h5><i class="fas fa-cash-register text-warning me-2"></i> Quick Actions</h5>
                <div class="d-grid gap-2 mt-3">
                    <a href="pos.php" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i> Create New Sale
                    </a>
                    <a href="customers.php" class="btn btn-outline-secondary">
                        <i class="fas fa-user-plus"></i> Add New Customer
                    </a>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- Sales Performance Chart -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="dashboard-card bg-white">
            <h5><i class="fas fa-chart-line text-info me-2"></i> Personal Sales Performance</h5>
            <canvas id="salesChart" width="400" height="200"></canvas>
        </div>
    </div>
</div>

<!-- Recent Invoices -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="dashboard-card bg-white">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5><i class="fas fa-history text-secondary me-2"></i> Recent Invoices</h5>
                <a href="invoices.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($recent_invoices_result) > 0): ?>
                            <?php while ($invoice = mysqli_fetch_assoc($recent_invoices_result)): ?>
                                <tr>
                                    <td><?php echo $invoice['invoice_id']; ?></td>
                                    <td><?php echo $invoice['customer_name'] ?? 'Walk-in Customer'; ?></td>
                                    <td><?php echo number_format($invoice['total_amount'], 2); ?></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($invoice['created_at'])); ?></td>
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
                                        <a href="invoice_details.php?id=<?php echo $invoice['invoice_id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="print_invoice.php?id=<?php echo $invoice['invoice_id']; ?>" class="btn btn-sm btn-secondary">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No recent invoices found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for Chart -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Data from PHP
        var dates = <?php echo json_encode($dates); ?>;
        var sales = <?php echo json_encode($sales); ?>;

        // Create chart
        var ctx = document.getElementById('salesChart').getContext('2d');
        var salesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [{
                    label: 'Daily Sales Amount',
                    data: sales,
                    backgroundColor: 'rgba(26, 188, 156, 0.2)',
                    borderColor: 'rgba(26, 188, 156, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(26, 188, 156, 1)',
                    tension: 0.4
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₵' + value;
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Sales: ₵' + context.parsed.y;
                            }
                        }
                    }
                },
                responsive: true,
                maintainAspectRatio: true
            }
        });
    });
</script>

<?php
// Include footer
include_once '../include/footer.php';
?>