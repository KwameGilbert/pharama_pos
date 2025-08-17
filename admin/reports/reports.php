<?php
// Set page title
$page_title = "Reports & Analytics";

// Start output buffering to prevent header issues
ob_start();

// Include header
include_once '../includes/header.php';

// Get report type from query parameter
$report_type = isset($_GET['type']) ? $_GET['type'] : 'sales';

// Get date range parameters (with defaults)
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01'); // First day of current month
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d'); // Today
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$payment_method = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';

// Get all users (sales reps) for filtering
$users_query = "SELECT user_id, name FROM users ORDER BY name";
$users_result = mysqli_query($conn, $users_query);

// Initialize variables
$report_title = '';
$chart_type = 'bar';
$chart_data = [];
$chart_labels = [];
$report_data = [];

// Generate the appropriate report based on type
switch ($report_type) {
    case 'sales':
        generateSalesReport();
        break;

    case 'low_stock':
        generateLowStockReport();
        break;

    case 'expired':
        generateExpiredReport();
        break;

    case 'products':
        generateTopProductsReport();
        break;

    case 'payments':
        generatePaymentsReport();
        break;

    case 'alerts':
        generateAlertsReport();
        break;
}

// Sales Report Function
function generateSalesReport()
{
    global $conn, $date_from, $date_to, $user_id, $report_title, $chart_type, $chart_data, $chart_labels, $report_data;

    $report_title = "Sales Report";

    // Build query with filters
    $query = "SELECT DATE(i.created_at) as sale_date, 
             SUM(i.total_amount) as daily_total,
             COUNT(*) as num_invoices
             FROM invoices i
             WHERE i.payment_status = 'paid'
             AND DATE(i.created_at) BETWEEN '$date_from' AND '$date_to'";

    if ($user_id > 0) {
        $query .= " AND i.user_id = $user_id";
    }

    $query .= " GROUP BY DATE(i.created_at) ORDER BY DATE(i.created_at)";

    $result = mysqli_query($conn, $query);

    // Get summary totals
    $summary_query = "SELECT 
                    COUNT(*) as total_invoices,
                    SUM(total_amount) as total_sales,
                    AVG(total_amount) as avg_sale
                    FROM invoices
                    WHERE payment_status = 'paid'
                    AND DATE(created_at) BETWEEN '$date_from' AND '$date_to'";

    if ($user_id > 0) {
        $summary_query .= " AND user_id = $user_id";
    }

    $summary_result = mysqli_query($conn, $summary_query);
    $summary = mysqli_fetch_assoc($summary_result);

    // Process results for chart and table
    $total_sales = 0;
    while ($row = mysqli_fetch_assoc($result)) {
        $chart_labels[] = date('M d', strtotime($row['sale_date']));
        $chart_data[] = $row['daily_total'];
        $total_sales += $row['daily_total'];

        $report_data[] = [
            'date' => date('M d, Y', strtotime($row['sale_date'])),
            'invoices' => $row['num_invoices'],
            'amount' => $row['daily_total']
        ];
    }
}

// Low Stock Report Function
function generateLowStockReport()
{
    global $conn, $report_title, $chart_type, $chart_data, $chart_labels, $report_data;

    $report_title = "Low Stock Products";
    $chart_type = 'horizontalBar';

    $query = "SELECT p.*, s.name as supplier_name
             FROM products p 
             LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
             WHERE p.stock_qty <= p.reorder_level AND p.stock_qty > 0
             ORDER BY p.stock_qty ASC, p.name ASC";

    $result = mysqli_query($conn, $query);

    while ($row = mysqli_fetch_assoc($result)) {
        $chart_labels[] = $row['name'] . ' (' . $row['batch_no'] . ')';
        $chart_data[] = $row['stock_qty'];

        $report_data[] = [
            'id' => $row['product_id'],
            'name' => $row['name'],
            'batch' => $row['batch_no'],
            'category' => $row['category'],
            'current_stock' => $row['stock_qty'],
            'reorder_level' => $row['reorder_level'],
            'supplier' => $row['supplier_name']
        ];
    }
}

// Expired Products Report Function
function generateExpiredReport()
{
    global $conn, $report_title, $chart_type, $chart_data, $chart_labels, $report_data;

    $report_title = "Expired Products";

    $query = "SELECT p.*, s.name as supplier_name
             FROM products p 
             LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
             WHERE p.expiry_date <= CURDATE()
             ORDER BY p.expiry_date DESC, p.name ASC";

    $result = mysqli_query($conn, $query);

    // Group by months for chart
    $months_data = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $month = date('M Y', strtotime($row['expiry_date']));

        if (!isset($months_data[$month])) {
            $months_data[$month] = 0;
        }

        $months_data[$month]++;

        $report_data[] = [
            'id' => $row['product_id'],
            'name' => $row['name'],
            'batch' => $row['batch_no'],
            'category' => $row['category'],
            'expiry_date' => date('M d, Y', strtotime($row['expiry_date'])),
            'current_stock' => $row['stock_qty'],
            'supplier' => $row['supplier_name']
        ];
    }

    // Convert to chart data
    foreach ($months_data as $month => $count) {
        $chart_labels[] = $month;
        $chart_data[] = $count;
    }
}

// Top Products Report Function
function generateTopProductsReport()
{
    global $conn, $date_from, $date_to, $report_title, $chart_type, $chart_data, $chart_labels, $report_data;

    $report_title = "Top Selling Products";

    $query = "SELECT 
             p.name,
             p.product_id,
             p.category,
             SUM(i.quantity) as total_qty,
             SUM(i.subtotal) as total_sales
             FROM invoice_items i
             JOIN products p ON i.product_id = p.product_id
             JOIN invoices inv ON i.invoice_id = inv.invoice_id
             WHERE inv.payment_status = 'paid'
             AND DATE(inv.created_at) BETWEEN '$date_from' AND '$date_to'
             GROUP BY p.product_id
             ORDER BY total_qty DESC
             LIMIT 20";

    $result = mysqli_query($conn, $query);

    while ($row = mysqli_fetch_assoc($result)) {
        $chart_labels[] = $row['name'];
        $chart_data[] = $row['total_qty'];

        $report_data[] = [
            'id' => $row['product_id'],
            'name' => $row['name'],
            'category' => $row['category'],
            'quantity' => $row['total_qty'],
            'sales' => $row['total_sales']
        ];
    }
}

// Payments Report Function
function generatePaymentsReport()
{
    global $conn, $date_from, $date_to, $payment_method, $report_title, $chart_type, $chart_data, $chart_labels, $report_data;

    $report_title = "Sales by Payment Type";
    $chart_type = 'pie';

    $query = "SELECT 
             p.payment_method,
             COUNT(*) as total_count,
             SUM(p.amount) as total_amount
             FROM payments p
             JOIN invoices i ON p.invoice_id = i.invoice_id
             WHERE i.payment_status = 'paid'
             AND DATE(p.paid_at) BETWEEN '$date_from' AND '$date_to'";

    if (!empty($payment_method)) {
        $query .= " AND p.payment_method = '$payment_method'";
    }

    $query .= " GROUP BY p.payment_method";

    $result = mysqli_query($conn, $query);

    $payment_method_labels = [
        'cash' => 'Cash',
        'mobile_money' => 'Mobile Money',
        'paystack' => 'Paystack'
    ];

    $colors = [
        'cash' => 'rgb(75, 192, 192)',
        'mobile_money' => 'rgb(54, 162, 235)',
        'paystack' => 'rgb(255, 159, 64)'
    ];

    $chart_colors = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $method = $payment_method_labels[$row['payment_method']] ?? $row['payment_method'];
        $chart_labels[] = $method;
        $chart_data[] = $row['total_amount'];
        $chart_colors[] = $colors[$row['payment_method']] ?? 'rgb(153, 102, 255)';

        $report_data[] = [
            'method' => $method,
            'count' => $row['total_count'],
            'amount' => $row['total_amount']
        ];
    }
}

// Alerts Report Function (combines low stock and expired)
function generateAlertsReport()
{
    global $conn, $report_title, $report_data;

    $report_title = "All Alerts";

    // First get low stock products
    $low_stock_query = "SELECT p.*, s.name as supplier_name, 'low_stock' as alert_type
                      FROM products p 
                      LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
                      WHERE p.stock_qty <= p.reorder_level AND p.stock_qty > 0
                      ORDER BY p.stock_qty ASC, p.name ASC";

    $low_stock_result = mysqli_query($conn, $low_stock_query);

    // Then get expired products
    $expired_query = "SELECT p.*, s.name as supplier_name, 'expired' as alert_type
                    FROM products p 
                    LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
                    WHERE p.expiry_date <= CURDATE()
                    ORDER BY p.expiry_date DESC, p.name ASC";

    $expired_result = mysqli_query($conn, $expired_query);

    // Then get products expiring soon
    $expiring_query = "SELECT p.*, s.name as supplier_name, 'expiring_soon' as alert_type
                     FROM products p 
                     LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
                     WHERE p.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                     ORDER BY p.expiry_date ASC, p.name ASC";

    $expiring_result = mysqli_query($conn, $expiring_query);

    // Process low stock products
    while ($row = mysqli_fetch_assoc($low_stock_result)) {
        $report_data[] = [
            'id' => $row['product_id'],
            'name' => $row['name'],
            'batch' => $row['batch_no'],
            'category' => $row['category'],
            'stock' => $row['stock_qty'],
            'reorder_level' => $row['reorder_level'],
            'supplier' => $row['supplier_name'],
            'alert_type' => 'Low Stock',
            'alert_details' => "Stock: {$row['stock_qty']} (Reorder at: {$row['reorder_level']})"
        ];
    }

    // Process expired products
    while ($row = mysqli_fetch_assoc($expired_result)) {
        $report_data[] = [
            'id' => $row['product_id'],
            'name' => $row['name'],
            'batch' => $row['batch_no'],
            'category' => $row['category'],
            'stock' => $row['stock_qty'],
            'reorder_level' => $row['reorder_level'],
            'supplier' => $row['supplier_name'],
            'alert_type' => 'Expired',
            'alert_details' => "Expired on: " . date('M d, Y', strtotime($row['expiry_date']))
        ];
    }

    // Process expiring soon products
    while ($row = mysqli_fetch_assoc($expiring_result)) {
        $days_left = (strtotime($row['expiry_date']) - strtotime(date('Y-m-d'))) / (60 * 60 * 24);

        $report_data[] = [
            'id' => $row['product_id'],
            'name' => $row['name'],
            'batch' => $row['batch_no'],
            'category' => $row['category'],
            'stock' => $row['stock_qty'],
            'reorder_level' => $row['reorder_level'],
            'supplier' => $row['supplier_name'],
            'alert_type' => 'Expiring Soon',
            'alert_details' => "Expires in " . round($days_left) . " days (" . date('M d, Y', strtotime($row['expiry_date'])) . ")"
        ];
    }
}
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Reports & Analytics</h4>
    <div>
        <?php if (!empty($report_data)): ?>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-download"></i> Export
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#" id="exportPDF">PDF Report</a></li>
                    <li><a class="dropdown-item" href="#" id="exportExcel">Excel Spreadsheet</a></li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Report Selection Tabs -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?php echo $report_type == 'sales' ? 'active' : ''; ?>" href="reports.php?type=sales">
            <i class="fas fa-chart-line me-2"></i>Sales Report
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $report_type == 'products' ? 'active' : ''; ?>" href="reports.php?type=products">
            <i class="fas fa-box me-2"></i>Top Products
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $report_type == 'payments' ? 'active' : ''; ?>" href="reports.php?type=payments">
            <i class="fas fa-credit-card me-2"></i>Payment Types
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $report_type == 'low_stock' ? 'active' : ''; ?>" href="reports.php?type=low_stock">
            <i class="fas fa-exclamation-triangle me-2"></i>Low Stock
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $report_type == 'expired' ? 'active' : ''; ?>" href="reports.php?type=expired">
            <i class="fas fa-calendar-times me-2"></i>Expired Products
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $report_type == 'alerts' ? 'active' : ''; ?>" href="reports.php?type=alerts">
            <i class="fas fa-bell me-2"></i>All Alerts
        </a>
    </li>
</ul>

<!-- Date Filter Controls (only for certain reports) -->
<?php if (in_array($report_type, ['sales', 'products', 'payments'])): ?>
    <div class="dashboard-card mb-4">
        <h5 class="mb-3">Filter Report</h5>
        <form method="get" action="" class="row g-3">
            <input type="hidden" name="type" value="<?php echo $report_type; ?>">

            <div class="col-md-3">
                <label for="date_from" class="form-label">Date From</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
            </div>
            <div class="col-md-3">
                <label for="date_to" class="form-label">Date To</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
            </div>

            <?php if ($report_type == 'sales'): ?>
                <div class="col-md-3">
                    <label for="user_id" class="form-label">Sales Rep</label>
                    <select class="form-select" id="user_id" name="user_id">
                        <option value="0">All Sales Reps</option>
                        <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
                            <option value="<?php echo $user['user_id']; ?>" <?php echo $user_id == $user['user_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if ($report_type == 'payments'): ?>
                <div class="col-md-3">
                    <label for="payment_method" class="form-label">Payment Method</label>
                    <select class="form-select" id="payment_method" name="payment_method">
                        <option value="">All Methods</option>
                        <option value="cash" <?php echo $payment_method == 'cash' ? 'selected' : ''; ?>>Cash</option>
                        <option value="mobile_money" <?php echo $payment_method == 'mobile_money' ? 'selected' : ''; ?>>Mobile Money</option>
                        <option value="paystack" <?php echo $payment_method == 'paystack' ? 'selected' : ''; ?>>Paystack</option>
                    </select>
                </div>
            <?php endif; ?>

            <div class="col-md-3 d-flex align-items-end">
                <div class="d-grid w-100">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filter
                    </button>
                </div>
            </div>
        </form>
    </div>
<?php endif; ?>

<!-- Report Content -->
<div class="dashboard-card">
    <h4 class="mb-4"><?php echo $report_title; ?>
        <?php if (in_array($report_type, ['sales', 'products', 'payments'])): ?>
            <span class="text-muted fs-6">
                (<?php echo date('M d, Y', strtotime($date_from)); ?> - <?php echo date('M d, Y', strtotime($date_to)); ?>)
            </span>
        <?php endif; ?>
    </h4>

    <?php if (!empty($chart_data) && !empty($chart_labels) && $report_type != 'alerts'): ?>
        <!-- Chart Display -->
        <div class="mb-5">
            <canvas id="reportChart" height="300"></canvas>
        </div>
    <?php endif; ?>

    <?php if ($report_type == 'sales'): ?>
        <!-- Sales Summary -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-center p-3">
                    <h3 class="text-primary mb-0"><?php echo number_format($summary['total_invoices'] ?? 0); ?></h3>
                    <p class="text-muted mb-0">Total Invoices</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center p-3">
                    <h3 class="text-success mb-0">₵<?php echo number_format($summary['total_sales'] ?? 0, 2); ?></h3>
                    <p class="text-muted mb-0">Total Sales</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center p-3">
                    <h3 class="text-info mb-0">₵<?php echo number_format($summary['avg_sale'] ?? 0, 2); ?></h3>
                    <p class="text-muted mb-0">Average Sale Value</p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Report Table -->
    <div class="table-responsive">
        <table class="table table-hover" id="reportTable">
            <thead>
                <tr>
                    <?php if ($report_type == 'sales'): ?>
                        <th>Date</th>
                        <th>Number of Invoices</th>
                        <th>Total Sales</th>
                    <?php elseif ($report_type == 'low_stock'): ?>
                        <th>Product ID</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Current Stock</th>
                        <th>Reorder Level</th>
                        <th>Supplier</th>
                        <th>Actions</th>
                    <?php elseif ($report_type == 'expired'): ?>
                        <th>Product ID</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Batch</th>
                        <th>Expiry Date</th>
                        <th>Current Stock</th>
                        <th>Supplier</th>
                        <th>Actions</th>
                    <?php elseif ($report_type == 'products'): ?>
                        <th>Product ID</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Quantity Sold</th>
                        <th>Total Sales</th>
                    <?php elseif ($report_type == 'payments'): ?>
                        <th>Payment Method</th>
                        <th>Number of Transactions</th>
                        <th>Total Amount</th>
                        <th>Percentage</th>
                    <?php elseif ($report_type == 'alerts'): ?>
                        <th>Alert Type</th>
                        <th>Product ID</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Current Stock</th>
                        <th>Alert Details</th>
                        <th>Supplier</th>
                        <th>Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($report_data)): ?>
                    <tr>
                        <td colspan="8" class="text-center">No data available for this report</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($report_data as $row): ?>
                        <tr>
                            <?php if ($report_type == 'sales'): ?>
                                <td><?php echo $row['date']; ?></td>
                                <td><?php echo $row['invoices']; ?></td>
                                <td>₵<?php echo number_format($row['amount'], 2); ?></td>
                            <?php elseif ($report_type == 'low_stock'): ?>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['category']); ?></td>
                                <td><span class="badge bg-warning text-dark"><?php echo $row['current_stock']; ?></span></td>
                                <td><?php echo $row['reorder_level']; ?></td>
                                <td><?php echo htmlspecialchars($row['supplier']); ?></td>
                                <td>
                                    <a href="products/edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i> Update Stock
                                    </a>
                                </td>
                            <?php elseif ($report_type == 'expired'): ?>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['category']); ?></td>
                                <td><?php echo htmlspecialchars($row['batch']); ?></td>
                                <td><span class="badge bg-danger"><?php echo $row['expiry_date']; ?></span></td>
                                <td><?php echo $row['current_stock']; ?></td>
                                <td><?php echo htmlspecialchars($row['supplier']); ?></td>
                                <td>
                                    <a href="products/edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash-alt"></i> Remove
                                    </a>
                                </td>
                            <?php elseif ($report_type == 'products'): ?>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['category']); ?></td>
                                <td><?php echo $row['quantity']; ?></td>
                                <td>₵<?php echo number_format($row['sales'], 2); ?></td>
                            <?php elseif ($report_type == 'payments'): ?>
                                <td><?php echo htmlspecialchars($row['method']); ?></td>
                                <td><?php echo $row['count']; ?></td>
                                <td>₵<?php echo number_format($row['amount'], 2); ?></td>
                                <td>
                                    <?php
                                    $total = array_sum(array_column($report_data, 'amount'));
                                    $percentage = ($total > 0) ? ($row['amount'] / $total * 100) : 0;
                                    echo number_format($percentage, 1) . '%';
                                    ?>
                                </td>
                            <?php elseif ($report_type == 'alerts'): ?>
                                <td>
                                    <?php if ($row['alert_type'] == 'Low Stock'): ?>
                                        <span class="badge bg-warning text-dark">Low Stock</span>
                                    <?php elseif ($row['alert_type'] == 'Expired'): ?>
                                        <span class="badge bg-danger">Expired</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Expiring Soon</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['category']); ?></td>
                                <td><?php echo $row['stock']; ?></td>
                                <td><?php echo $row['alert_details']; ?></td>
                                <td><?php echo htmlspecialchars($row['supplier']); ?></td>
                                <td>
                                    <a href="products/edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i> Update
                                    </a>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- JavaScript for Report Chart -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!empty($chart_data) && !empty($chart_labels) && $report_type != 'alerts'): ?>
            // Get canvas element
            var ctx = document.getElementById('reportChart').getContext('2d');

            <?php if ($report_type == 'payments' && $chart_type == 'pie'): ?>
                // Pie Chart for Payment Methods
                var reportChart = new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: <?php echo json_encode($chart_labels); ?>,
                        datasets: [{
                            data: <?php echo json_encode($chart_data); ?>,
                            backgroundColor: [
                                'rgba(75, 192, 192, 0.7)',
                                'rgba(54, 162, 235, 0.7)',
                                'rgba(255, 159, 64, 0.7)',
                                'rgba(153, 102, 255, 0.7)'
                            ],
                            borderColor: [
                                'rgba(75, 192, 192, 1)',
                                'rgba(54, 162, 235, 1)',
                                'rgba(255, 159, 64, 1)',
                                'rgba(153, 102, 255, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        var label = context.label || '';
                                        var value = context.parsed || 0;
                                        var total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        var percentage = ((value / total) * 100).toFixed(1);
                                        return label + ': ₵' + value.toLocaleString() + ' (' + percentage + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
            <?php elseif ($report_type == 'products' || $report_type == 'low_stock'): ?>
                // Horizontal Bar Chart for Products
                var reportChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($chart_labels); ?>,
                        datasets: [{
                            label: '<?php echo ($report_type == 'products') ? 'Quantity Sold' : 'Current Stock'; ?>',
                            data: <?php echo json_encode($chart_data); ?>,
                            backgroundColor: 'rgba(54, 162, 235, 0.5)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        scales: {
                            x: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            <?php else: ?>
                // Regular Bar/Line Chart for other reports
                var reportChart = new Chart(ctx, {
                    type: '<?php echo ($report_type == 'sales') ? 'line' : 'bar'; ?>',
                    data: {
                        labels: <?php echo json_encode($chart_labels); ?>,
                        datasets: [{
                            label: '<?php echo ($report_type == 'sales') ? 'Sales Amount' : 'Count'; ?>',
                            data: <?php echo json_encode($chart_data); ?>,
                            backgroundColor: 'rgba(75, 192, 192, 0.5)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 2,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                <?php if ($report_type == 'sales'): ?>
                                    ticks: {
                                        callback: function(value) {
                                            return '₵' + value.toLocaleString();
                                        }
                                    }
                                <?php endif; ?>
                            }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    <?php if ($report_type == 'sales'): ?>
                                        label: function(context) {
                                            return 'Sales: ₵' + context.parsed.y.toLocaleString();
                                        }
                                    <?php endif; ?>
                                }
                            }
                        }
                    }
                });
            <?php endif; ?>
        <?php endif; ?>

        // Initialize DataTables
        $('#reportTable').DataTable({
            dom: 'Bfrtip',
            buttons: [
                'copy', 'excel', 'pdf'
            ]
        });
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';

// End output buffering and flush
ob_end_flush();
?>