<?php
// Set page title
$page_title = "Dashboard";

// Include header
include_once '../includes/header.php';

// Get today's date and dates for various statistics
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$this_month_start = date('Y-m-01');
$this_month_end = date('Y-m-t');
$last_month_start = date('Y-m-01', strtotime('-1 month'));
$last_month_end = date('Y-m-t', strtotime('-1 month'));

// Today's sales
$today_sales_query = "SELECT COUNT(*) as count, SUM(total_amount) as total 
                      FROM invoices 
                      WHERE DATE(created_at) = ? AND payment_status = 'paid'";
$today_stmt = $conn->prepare($today_sales_query);
$today_stmt->bind_param("s", $today);
$today_stmt->execute();
$today_sales = $today_stmt->get_result()->fetch_assoc();

// Yesterday's sales
$yesterday_sales_query = "SELECT COUNT(*) as count, SUM(total_amount) as total 
                         FROM invoices 
                         WHERE DATE(created_at) = ? AND payment_status = 'paid'";
$yesterday_stmt = $conn->prepare($yesterday_sales_query);
$yesterday_stmt->bind_param("s", $yesterday);
$yesterday_stmt->execute();
$yesterday_sales = $yesterday_stmt->get_result()->fetch_assoc();

// This month's sales
$this_month_query = "SELECT COUNT(*) as count, SUM(total_amount) as total 
                     FROM invoices 
                     WHERE created_at BETWEEN ? AND ? AND payment_status = 'paid'";
$this_month_stmt = $conn->prepare($this_month_query);
$this_month_stmt->bind_param("ss", $this_month_start, $this_month_end);
$this_month_stmt->execute();
$this_month_sales = $this_month_stmt->get_result()->fetch_assoc();

// Last month's sales
$last_month_query = "SELECT COUNT(*) as count, SUM(total_amount) as total 
                     FROM invoices 
                     WHERE created_at BETWEEN ? AND ? AND payment_status = 'paid'";
$last_month_stmt = $conn->prepare($last_month_query);
$last_month_stmt->bind_param("ss", $last_month_start, $last_month_end);
$last_month_stmt->execute();
$last_month_sales = $last_month_stmt->get_result()->fetch_assoc();

// Low stock products
$low_stock_query = "SELECT COUNT(*) as count FROM products WHERE stock_qty <= reorder_level AND stock_qty > 0";
$low_stock = $conn->query($low_stock_query)->fetch_assoc();

// Out of stock products
$out_stock_query = "SELECT COUNT(*) as count FROM products WHERE stock_qty = 0";
$out_stock = $conn->query($out_stock_query)->fetch_assoc();

// Expired products
$expired_query = "SELECT COUNT(*) as count FROM products WHERE expiry_date < CURDATE()";
$expired = $conn->query($expired_query)->fetch_assoc();

// Soon to expire products (within 30 days)
$soon_expire_query = "SELECT COUNT(*) as count FROM products WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
$soon_expire = $conn->query($soon_expire_query)->fetch_assoc();

// Top selling products
$top_products_query = "SELECT p.product_id, p.name, SUM(ii.quantity) as total_qty, 
                       SUM(ii.subtotal) as total_sales
                       FROM invoice_items ii
                       JOIN products p ON ii.product_id = p.product_id
                       JOIN invoices i ON ii.invoice_id = i.invoice_id
                       WHERE i.payment_status = 'paid'
                       AND i.created_at BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE()
                       GROUP BY p.product_id
                       ORDER BY total_sales DESC
                       LIMIT 5";
$top_products = $conn->query($top_products_query);

// Top performing sales reps
$top_reps_query = "SELECT u.user_id, u.name, COUNT(i.invoice_id) as invoice_count, 
                  SUM(i.total_amount) as total_sales
                  FROM invoices i
                  JOIN users u ON i.user_id = u.user_id
                  WHERE i.payment_status = 'paid'
                  AND i.created_at BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE()
                  GROUP BY u.user_id
                  ORDER BY total_sales DESC
                  LIMIT 5";
$top_reps = $conn->query($top_reps_query);

// Daily sales data for the chart (last 14 days)
$chart_query = "SELECT DATE(created_at) as sale_date, COUNT(*) as sale_count, 
                SUM(total_amount) as daily_total
                FROM invoices
                WHERE payment_status = 'paid' 
                AND created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
                GROUP BY DATE(created_at)
                ORDER BY sale_date";
$chart_data = $conn->query($chart_query);

// Process chart data for JavaScript
$dates = [];
$sales_counts = [];
$sales_amounts = [];

while ($row = $chart_data->fetch_assoc()) {
    $dates[] = date('M d', strtotime($row['sale_date']));
    $sales_counts[] = $row['sale_count'];
    $sales_amounts[] = $row['daily_total'];
}

// Recent transactions
$recent_transactions_query = "SELECT i.invoice_id, i.total_amount, i.created_at, 
                            i.payment_status, u.name as staff_name, c.name as customer_name
                            FROM invoices i
                            LEFT JOIN users u ON i.user_id = u.user_id
                            LEFT JOIN customers c ON i.customer_id = c.customer_id
                            ORDER BY i.created_at DESC
                            LIMIT 10";
$recent_transactions = $conn->query($recent_transactions_query);

// Calculate sales growth percentages
$today_total = $today_sales['total'] ?? 0;
$yesterday_total = $yesterday_sales['total'] ?? 0;
$this_month_total = $this_month_sales['total'] ?? 0;
$last_month_total = $last_month_sales['total'] ?? 0;

$daily_growth = 0;
$monthly_growth = 0;

if ($yesterday_total > 0) {
    $daily_growth = (($today_total - $yesterday_total) / $yesterday_total) * 100;
}

if ($last_month_total > 0) {
    $monthly_growth = (($this_month_total - $last_month_total) / $last_month_total) * 100;
}
?>

<div class="container-fluid px-4">
    <h3 class="mt-2 mb-4">Dashboard</h3>

    <!-- Sales Stats Row -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Today's Sales</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">₵<?php echo number_format($today_sales['total'] ?? 0, 2); ?></div>
                            <div class="small text-muted mt-1"><?php echo $today_sales['count'] ?? 0; ?> transactions</div>
                            <?php if ($daily_growth != 0): ?>
                                <div class="mt-2 small <?php echo $daily_growth >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    <i class="fas fa-<?php echo $daily_growth >= 0 ? 'arrow-up' : 'arrow-down'; ?> me-1"></i>
                                    <?php echo abs(round($daily_growth, 1)); ?>% from yesterday
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Monthly Sales (<?php echo date('F'); ?>)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">₵<?php echo number_format($this_month_sales['total'] ?? 0, 2); ?></div>
                            <div class="small text-muted mt-1"><?php echo $this_month_sales['count'] ?? 0; ?> transactions</div>
                            <?php if ($monthly_growth != 0): ?>
                                <div class="mt-2 small <?php echo $monthly_growth >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    <i class="fas fa-<?php echo $monthly_growth >= 0 ? 'arrow-up' : 'arrow-down'; ?> me-1"></i>
                                    <?php echo abs(round($monthly_growth, 1)); ?>% from last month
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Low Stock Alert</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $low_stock['count'] ?? 0; ?> Products</div>
                            <div class="small text-muted mt-1"><?php echo $out_stock['count'] ?? 0; ?> out of stock</div>
                            <div class="mt-2">
                                <a href="../products/products.php?stock=low" class="small text-warning">View Details <i class="fas fa-arrow-right"></i></a>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-boxes fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Expiry Alert</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $expired['count'] ?? 0; ?> Expired</div>
                            <div class="small text-muted mt-1"><?php echo $soon_expire['count'] ?? 0; ?> expiring soon</div>
                            <div class="mt-2">
                                <a href="../products/products.php?expiry=expired" class="small text-danger">View Details <i class="fas fa-arrow-right"></i></a>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-times fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart and Top Products Row -->
    <div class="row">
        <!-- Sales Chart -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Sales Overview (Last 14 Days)</h6>
                </div>
                <div class="card-body">
                    <div class="chart-area">
                        <canvas id="salesChart" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Products -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Top Selling Products (30 Days)</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-borderless table-sm">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Units</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($top_products->num_rows > 0): ?>
                                    <?php while ($product = $top_products->fetch_assoc()): ?>
                                        <tr>
                                            <td><a href="../products/view.php?id=<?php echo $product['product_id']; ?>"><?php echo htmlspecialchars($product['name']); ?></a></td>
                                            <td><?php echo $product['total_qty']; ?></td>
                                            <td>₵<?php echo number_format($product['total_sales'], 2); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center">No sales data available</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 text-center">
                        <a href="../reports.php" class="btn btn-sm btn-outline-primary">View All Reports</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Transactions and Top Staff Row -->
    <div class="row">
        <!-- Recent Transactions -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Transactions</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Staff</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recent_transactions->num_rows > 0): ?>
                                    <?php while ($transaction = $recent_transactions->fetch_assoc()): ?>
                                        <tr>
                                            <td><a href="../sales/invoice_details.php?id=<?php echo $transaction['invoice_id']; ?>"><?php echo $transaction['invoice_id']; ?></a></td>
                                            <td><?php echo date('M d, H:i', strtotime($transaction['created_at'])); ?></td>
                                            <td><?php echo !empty($transaction['customer_name']) ? htmlspecialchars($transaction['customer_name']) : '<span class="text-muted">Walk-in</span>'; ?></td>
                                            <td><?php echo htmlspecialchars($transaction['staff_name']); ?></td>
                                            <td>₵<?php echo number_format($transaction['total_amount'], 2); ?></td>
                                            <td>
                                                <?php if ($transaction['payment_status'] == 'paid'): ?>
                                                    <span class="badge bg-success">Paid</span>
                                                <?php elseif ($transaction['payment_status'] == 'cancelled'): ?>
                                                    <span class="badge bg-danger">Cancelled</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No recent transactions</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 text-center">
                        <a href="../sales/index.php" class="btn btn-sm btn-outline-primary">View All Sales</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Staff -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Top Sales Staff (30 Days)</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-borderless table-sm">
                            <thead>
                                <tr>
                                    <th>Staff</th>
                                    <th>Sales</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($top_reps->num_rows > 0): ?>
                                    <?php while ($rep = $top_reps->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($rep['name']); ?></td>
                                            <td><?php echo $rep['invoice_count']; ?></td>
                                            <td>₵<?php echo number_format($rep['total_sales'], 2); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center">No sales data available</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 text-center">
                        <a href="../users/index.php" class="btn btn-sm btn-outline-primary">View All Staff</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // Sales Chart
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('salesChart').getContext('2d');

        const salesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($dates); ?>,
                datasets: [{
                        label: 'Sales Amount (₵)',
                        data: <?php echo json_encode($sales_amounts); ?>,
                        backgroundColor: 'rgba(78, 115, 223, 0.05)',
                        borderColor: 'rgba(78, 115, 223, 1)',
                        pointRadius: 3,
                        pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                        pointBorderColor: 'rgba(78, 115, 223, 1)',
                        pointHoverRadius: 3,
                        pointHoverBackgroundColor: 'rgba(78, 115, 223, 1)',
                        pointHoverBorderColor: 'rgba(78, 115, 223, 1)',
                        pointHitRadius: 10,
                        pointBorderWidth: 2,
                        fill: true,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Number of Sales',
                        data: <?php echo json_encode($sales_counts); ?>,
                        backgroundColor: 'rgba(28, 200, 138, 0.05)',
                        borderColor: 'rgba(28, 200, 138, 1)',
                        pointRadius: 3,
                        pointBackgroundColor: 'rgba(28, 200, 138, 1)',
                        pointBorderColor: 'rgba(28, 200, 138, 1)',
                        pointHoverRadius: 3,
                        pointHoverBackgroundColor: 'rgba(28, 200, 138, 1)',
                        pointHoverBorderColor: 'rgba(28, 200, 138, 1)',
                        pointHitRadius: 10,
                        pointBorderWidth: 2,
                        fill: false,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                maintainAspectRatio: false,
                layout: {
                    padding: {
                        left: 10,
                        right: 25,
                        top: 25,
                        bottom: 0
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            maxTicksLimit: 7
                        }
                    },
                    y: {
                        position: 'left',
                        grid: {
                            color: "rgb(234, 236, 244)",
                            zeroLineColor: "rgb(234, 236, 244)",
                            drawBorder: false,
                            borderDash: [2],
                            zeroLineBorderDash: [2]
                        },
                        ticks: {
                            callback: function(value) {
                                return '₵' + value;
                            }
                        }
                    },
                    y1: {
                        position: 'right',
                        grid: {
                            display: false,
                        },
                        ticks: {
                            beginAtZero: true,
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true
                    },
                    tooltip: {
                        backgroundColor: "rgb(255,255,255)",
                        bodyColor: "#858796",
                        titleMarginBottom: 10,
                        titleColor: '#6e707e',
                        titleFontSize: 14,
                        borderColor: '#dddfeb',
                        borderWidth: 1,
                        xPadding: 15,
                        yPadding: 15,
                        displayColors: false,
                        intersect: false,
                        mode: 'index',
                        caretPadding: 10,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';

                                if (label) {
                                    label += ': ';
                                }

                                if (context.dataset.yAxisID === 'y') {
                                    label += '₵' + context.parsed.y;
                                } else {
                                    label += context.parsed.y;
                                }

                                return label;
                            }
                        }
                    }
                }
            }
        });
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>