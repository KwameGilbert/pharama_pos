<?php
// Set page title
$page_title = "Admin Dashboard";

// Start output buffering to prevent header issues
ob_start();

// Include header
include_once 'includes/header.php';

// Get today's date in Y-m-d format
$today = date('Y-m-d');
$first_day_month = date('Y-m-01'); // First day of current month

// Get total sales for today
$today_sales_query = "SELECT SUM(total_amount) as today_sales 
                      FROM invoices 
                      WHERE DATE(created_at) = '$today'
                      AND payment_status = 'paid'";
$today_sales_result = mysqli_query($conn, $today_sales_query);
$today_sales_row = mysqli_fetch_assoc($today_sales_result);
$today_sales = $today_sales_row['today_sales'] ?? 0;

// Get total sales for this month
$month_sales_query = "SELECT SUM(total_amount) as month_sales 
                      FROM invoices 
                      WHERE DATE(created_at) BETWEEN '$first_day_month' AND '$today'
                      AND payment_status = 'paid'";
$month_sales_result = mysqli_query($conn, $month_sales_query);
$month_sales_row = mysqli_fetch_assoc($month_sales_result);
$month_sales = $month_sales_row['month_sales'] ?? 0;

// Get total invoices for today
$today_invoices_query = "SELECT COUNT(*) as count FROM invoices WHERE DATE(created_at) = '$today'";
$today_invoices_result = mysqli_query($conn, $today_invoices_query);
$today_invoices_row = mysqli_fetch_assoc($today_invoices_result);
$today_invoices = $today_invoices_row['count'];

// Get total customers
$customers_query = "SELECT COUNT(*) as count FROM customers";
$customers_result = mysqli_query($conn, $customers_query);
$customers_row = mysqli_fetch_assoc($customers_result);
$total_customers = $customers_row['count'];

// Get low stock products
$low_stock_query = "SELECT COUNT(*) as count FROM products WHERE stock_qty <= reorder_level AND stock_qty > 0";
$low_stock_result = mysqli_query($conn, $low_stock_query);
$low_stock_row = mysqli_fetch_assoc($low_stock_result);
$low_stock = $low_stock_row['count'];

// Get out of stock products
$out_stock_query = "SELECT COUNT(*) as count FROM products WHERE stock_qty = 0";
$out_stock_result = mysqli_query($conn, $out_stock_query);
$out_stock_row = mysqli_fetch_assoc($out_stock_result);
$out_stock = $out_stock_row['count'];

// Get expired products
$expired_query = "SELECT COUNT(*) as count FROM products WHERE expiry_date <= CURDATE()";
$expired_result = mysqli_query($conn, $expired_query);
$expired_row = mysqli_fetch_assoc($expired_result);
$expired = $expired_row['count'];

// Get sales data for the last 7 days for chart
$days_query = "SELECT 
               DATE(created_at) as sale_date, 
               SUM(total_amount) as daily_total 
               FROM invoices 
               WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
               AND payment_status = 'paid'
               GROUP BY DATE(created_at)
               ORDER BY sale_date";
$days_result = mysqli_query($conn, $days_query);

$dates = [];
$sales_data = [];

while ($row = mysqli_fetch_assoc($days_result)) {
    $dates[] = date('d M', strtotime($row['sale_date']));
    $sales_data[] = $row['daily_total'];
}

// Get top selling products
$top_products_query = "SELECT 
                      p.name,
                      SUM(i.quantity) as total_qty,
                      SUM(i.subtotal) as total_sales
                      FROM invoice_items i
                      JOIN products p ON i.product_id = p.product_id
                      JOIN invoices inv ON i.invoice_id = inv.invoice_id
                      WHERE inv.payment_status = 'paid'
                      AND inv.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                      GROUP BY p.product_id
                      ORDER BY total_qty DESC
                      LIMIT 5";
$top_products_result = mysqli_query($conn, $top_products_query);

// Get recent invoices
$recent_invoices_query = "SELECT i.*, c.name as customer_name, u.name as sales_rep
                        FROM invoices i
                        LEFT JOIN customers c ON i.customer_id = c.customer_id
                        LEFT JOIN users u ON i.user_id = u.user_id
                        ORDER BY i.created_at DESC
                        LIMIT 10";
$recent_invoices_result = mysqli_query($conn, $recent_invoices_query);
?>

<!-- Dashboard Stats -->
<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="dashboard-card stats-card sales-card">
            <div class="icon">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="card-body">
                <p>Today's Sales</p>
                <h3>₵<?php echo number_format($today_sales, 2); ?></h3>
                <p class="text-success small">
                    <i class="fas fa-arrow-up me-1"></i>
                    Month: ₵<?php echo number_format($month_sales, 2); ?>
                </p>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="dashboard-card stats-card invoices-card">
            <div class="icon">
                <i class="fas fa-file-invoice"></i>
            </div>
            <div class="card-body">
                <p>Today's Invoices</p>
                <h3><?php echo $today_invoices; ?></h3>
                <p class="text-muted small">
                    <i class="fas fa-calendar me-1"></i>
                    <?php echo date('l, F j, Y'); ?>
                </p>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="dashboard-card stats-card customers-card">
            <div class="icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="card-body">
                <p>Total Customers</p>
                <h3><?php echo $total_customers; ?></h3>
                <a href="customers/index.php" class="text-primary small">
                    <i class="fas fa-eye me-1"></i> View customers
                </a>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="dashboard-card stats-card alerts-card">
            <div class="icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="card-body">
                <p>Alerts</p>
                <h3><?php echo $low_stock + $out_stock + $expired; ?></h3>
                <div>
                    <span class="badge bg-warning text-dark me-1">Low Stock: <?php echo $low_stock; ?></span>
                    <span class="badge bg-danger me-1">No Stock: <?php echo $out_stock; ?></span>
                    <span class="badge bg-danger">Expired: <?php echo $expired; ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Analytics Section -->
<div class="row">
    <!-- Sales Trends Chart -->
    <div class="col-lg-8 mb-4">
        <div class="dashboard-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0"><i class="fas fa-chart-line me-2 text-primary"></i> Sales Trends</h5>
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="weekBtn">Week</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary active" id="monthBtn">Month</button>
                </div>
            </div>
            <canvas id="salesChart" height="300"></canvas>
        </div>
    </div>

    <!-- Top Selling Products -->
    <div class="col-lg-4 mb-4">
        <div class="dashboard-card">
            <h5 class="mb-3"><i class="fas fa-award me-2 text-success"></i> Top Selling Products</h5>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Qty Sold</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($product = mysqli_fetch_assoc($top_products_result)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo $product['total_qty']; ?></td>
                                <td>₵<?php echo number_format($product['total_sales'], 2); ?></td>
                            </tr>
                        <?php endwhile; ?>

                        <?php if (mysqli_num_rows($top_products_result) == 0): ?>
                            <tr>
                                <td colspan="3" class="text-center">No sales data available</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="text-center mt-3">
                <a href="reports.php?type=products" class="btn btn-outline-primary btn-sm">View Full Report</a>
            </div>
        </div>
    </div>
</div>

<!-- Alerts Section -->
<div class="row mb-4">
    <!-- Low Stock & Expired Products -->
    <div class="col-lg-12">
        <div class="dashboard-card">
            <ul class="nav nav-tabs" id="alertsTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="low-stock-tab" data-bs-toggle="tab" data-bs-target="#low-stock" type="button" role="tab" aria-controls="low-stock" aria-selected="true">
                        Low Stock <span class="badge bg-warning text-dark ms-1"><?php echo $low_stock; ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="expired-tab" data-bs-toggle="tab" data-bs-target="#expired" type="button" role="tab" aria-controls="expired" aria-selected="false">
                        Expired Products <span class="badge bg-danger ms-1"><?php echo $expired; ?></span>
                    </button>
                </li>
            </ul>
            <div class="tab-content p-3" id="alertsTabContent">
                <div class="tab-pane fade show active" id="low-stock" role="tabpanel" aria-labelledby="low-stock-tab">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>Current Stock</th>
                                    <th>Reorder Level</th>
                                    <th>Category</th>
                                    <th>Supplier</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $low_stock_products_query = "SELECT p.*, s.name as supplier_name
                                                           FROM products p 
                                                           LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
                                                           WHERE p.stock_qty <= p.reorder_level AND p.stock_qty > 0
                                                           ORDER BY p.stock_qty ASC
                                                           LIMIT 5";
                                $low_stock_products_result = mysqli_query($conn, $low_stock_products_query);

                                if (mysqli_num_rows($low_stock_products_result) > 0) {
                                    while ($product = mysqli_fetch_assoc($low_stock_products_result)) {
                                        echo '<tr>';
                                        echo '<td>' . htmlspecialchars($product['name']) . '</td>';
                                        echo '<td><span class="badge bg-warning text-dark">' . $product['stock_qty'] . '</span></td>';
                                        echo '<td>' . $product['reorder_level'] . '</td>';
                                        echo '<td>' . htmlspecialchars($product['category']) . '</td>';
                                        echo '<td>' . htmlspecialchars($product['supplier_name']) . '</td>';
                                        echo '<td><a href="products/edit.php?id=' . $product['product_id'] . '" class="btn btn-sm btn-primary">Update Stock</a></td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="6" class="text-center">No low stock products</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($low_stock > 5): ?>
                        <div class="text-center mt-3">
                            <a href="reports.php?type=low_stock" class="btn btn-outline-warning">View All Low Stock Products</a>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="tab-pane fade" id="expired" role="tabpanel" aria-labelledby="expired-tab">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>Batch No</th>
                                    <th>Expiry Date</th>
                                    <th>Stock Qty</th>
                                    <th>Category</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $expired_products_query = "SELECT p.* FROM products p 
                                                         WHERE p.expiry_date <= CURDATE()
                                                         ORDER BY p.expiry_date DESC
                                                         LIMIT 5";
                                $expired_products_result = mysqli_query($conn, $expired_products_query);

                                if (mysqli_num_rows($expired_products_result) > 0) {
                                    while ($product = mysqli_fetch_assoc($expired_products_result)) {
                                        echo '<tr>';
                                        echo '<td>' . htmlspecialchars($product['name']) . '</td>';
                                        echo '<td>' . htmlspecialchars($product['batch_no']) . '</td>';
                                        echo '<td><span class="badge bg-danger">' . date('M d, Y', strtotime($product['expiry_date'])) . '</span></td>';
                                        echo '<td>' . $product['stock_qty'] . '</td>';
                                        echo '<td>' . htmlspecialchars($product['category']) . '</td>';
                                        echo '<td><a href="products/edit.php?id=' . $product['product_id'] . '" class="btn btn-sm btn-danger">Remove</a></td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="6" class="text-center">No expired products</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($expired > 5): ?>
                        <div class="text-center mt-3">
                            <a href="reports.php?type=expired" class="btn btn-outline-danger">View All Expired Products</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Invoices Section -->
<div class="row">
    <div class="col-lg-12">
        <div class="dashboard-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="fas fa-file-invoice me-2 text-secondary"></i> Recent Invoices</h5>
                <a href="sales/invoices.php" class="btn btn-sm btn-outline-primary">View All Invoices</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Sales Rep</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($invoice = mysqli_fetch_assoc($recent_invoices_result)): ?>
                            <tr>
                                <td><?php echo $invoice['invoice_id']; ?></td>
                                <td><?php echo date('M d, Y h:i A', strtotime($invoice['created_at'])); ?></td>
                                <td><?php echo $invoice['customer_name'] ?? 'Walk-in Customer'; ?></td>
                                <td><?php echo htmlspecialchars($invoice['sales_rep']); ?></td>
                                <td>₵<?php echo number_format($invoice['total_amount'], 2); ?></td>
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
                                    <a href="sales/invoice_details.php?id=<?php echo $invoice['invoice_id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="sales/print_invoice.php?id=<?php echo $invoice['invoice_id']; ?>" class="btn btn-sm btn-secondary">
                                        <i class="fas fa-print"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>

                        <?php if (mysqli_num_rows($recent_invoices_result) == 0): ?>
                            <tr>
                                <td colspan="7" class="text-center">No recent invoices found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for Charts -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sales Chart
        var ctx = document.getElementById('salesChart').getContext('2d');
        var salesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($dates); ?>,
                datasets: [{
                    label: 'Daily Sales',
                    data: <?php echo json_encode($sales_data); ?>,
                    backgroundColor: 'rgba(52, 152, 219, 0.2)',
                    borderColor: 'rgba(52, 152, 219, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(52, 152, 219, 1)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 1,
                    pointRadius: 4,
                    tension: 0.4
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₵' + value.toLocaleString();
                            }
                        },
                        title: {
                            display: true,
                            text: 'Sales Amount (₵)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Sales: ₵' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                responsive: true,
                maintainAspectRatio: true
            }
        });

        // Initialize DataTables
        if ($.fn.DataTable) {
            $('table.display').DataTable();
        }

        // Tab functionality
        document.querySelectorAll('#alertsTab button').forEach(function(button) {
            button.addEventListener('click', function() {
                document.querySelectorAll('#alertsTab button').forEach(function(btn) {
                    btn.classList.remove('active');
                });
                this.classList.add('active');

                document.querySelectorAll('.tab-pane').forEach(function(pane) {
                    pane.classList.remove('show', 'active');
                });

                const target = this.getAttribute('data-bs-target').substring(1);
                document.getElementById(target).classList.add('show', 'active');
            });
        });
    });
</script>

<?php
// Include footer
include_once 'includes/footer.php';

// End output buffering and flush
ob_end_flush();
?>