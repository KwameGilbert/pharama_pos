<?php
// Set page title
$page_title = "Inventory Management";

// Include header
include_once '../includes/head.php';

// Get filter parameters
$category = isset($_GET['category']) ? $_GET['category'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$stock_status = isset($_GET['stock_status']) ? $_GET['stock_status'] : '';
$expiry_filter = isset($_GET['expiry']) ? $_GET['expiry'] : '';

// Build query with filters
$query = "SELECT p.*, s.name as supplier_name 
          FROM products p 
          LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id 
          WHERE 1=1";

$params = [];
$types = "";

if (!empty($category)) {
    $query .= " AND p.category = ?";
    $params[] = $category;
    $types .= "s";
}

if (!empty($search)) {
    $search = "%$search%";
    $query .= " AND (p.name LIKE ? OR p.batch_no LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $types .= "ss";
}

if (!empty($stock_status)) {
    if ($stock_status == 'low') {
        $query .= " AND p.stock_qty <= p.reorder_level AND p.stock_qty > 0";
    } elseif ($stock_status == 'out') {
        $query .= " AND p.stock_qty = 0";
    } elseif ($stock_status == 'good') {
        $query .= " AND p.stock_qty > p.reorder_level";
    }
}

if (!empty($expiry_filter)) {
    if ($expiry_filter == 'expired') {
        $query .= " AND p.expiry_date <= CURDATE()";
    } elseif ($expiry_filter == 'expiring_soon') {
        $query .= " AND p.expiry_date > CURDATE() AND p.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
    } elseif ($expiry_filter == 'valid') {
        $query .= " AND p.expiry_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
    }
}

$query .= " ORDER BY p.name ASC";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get distinct categories for filter dropdown
$categories_query = "SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category";
$categories_result = mysqli_query($conn, $categories_query);

// Get inventory summary
$summary_query = "SELECT 
                COUNT(*) as total_products,
                SUM(stock_qty) as total_stock,
                SUM(stock_qty * selling_price) as total_value,
                COUNT(CASE WHEN stock_qty <= reorder_level AND stock_qty > 0 THEN 1 END) as low_stock,
                COUNT(CASE WHEN stock_qty = 0 THEN 1 END) as out_of_stock,
                COUNT(CASE WHEN expiry_date <= CURDATE() THEN 1 END) as expired,
                COUNT(CASE WHEN expiry_date > CURDATE() AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as expiring_soon
                FROM products";
$summary_result = mysqli_query($conn, $summary_query);
$summary = mysqli_fetch_assoc($summary_result);
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-boxes me-2"></i> Inventory Management</h5>
                <div>
                    <a href="../products/products.php" class="btn btn-light btn-sm">
                        <i class="fas fa-pills me-1"></i> Products
                    </a>
                    <button class="btn btn-light btn-sm ms-2" data-bs-toggle="modal" data-bs-target="#stockAdjustModal">
                        <i class="fas fa-edit me-1"></i> Adjust Stock
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- Inventory Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="dashboard-card bg-light text-center h-100">
                            <h3 class="text-primary"><?php echo number_format($summary['total_products']); ?></h3>
                            <p class="text-muted mb-0">Total Products</p>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="dashboard-card bg-light text-center h-100">
                            <h3 class="text-primary"><?php echo number_format($summary['total_stock']); ?></h3>
                            <p class="text-muted mb-0">Total Units</p>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="dashboard-card bg-light text-center h-100">
                            <h3 class="text-primary"><?php echo number_format($summary['total_value'], 2); ?></h3>
                            <p class="text-muted mb-0">Inventory Value</p>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="dashboard-card bg-light text-center h-100">
                            <h3 class="text-warning"><?php echo number_format($summary['low_stock']); ?></h3>
                            <p class="text-muted mb-0">Low Stock</p>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="dashboard-card bg-light text-center h-100">
                            <h3 class="text-danger"><?php echo number_format($summary['out_of_stock']); ?></h3>
                            <p class="text-muted mb-0">Out of Stock</p>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="dashboard-card bg-light text-center h-100">
                            <h3 class="text-danger"><?php echo number_format($summary['expired']); ?></h3>
                            <p class="text-muted mb-0">Expired</p>
                        </div>
                    </div>
                </div>

                <!-- Filter Form -->
                <form class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label for="category" class="form-label">Category</label>
                        <select class="form-select" id="category" name="category">
                            <option value="">All Categories</option>
                            <?php while ($cat = mysqli_fetch_assoc($categories_result)): ?>
                                <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo ($category == $cat['category']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['category']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="stock_status" class="form-label">Stock Status</label>
                        <select class="form-select" id="stock_status" name="stock_status">
                            <option value="">All Stock Levels</option>
                            <option value="good" <?php echo ($stock_status == 'good') ? 'selected' : ''; ?>>Good Stock</option>
                            <option value="low" <?php echo ($stock_status == 'low') ? 'selected' : ''; ?>>Low Stock</option>
                            <option value="out" <?php echo ($stock_status == 'out') ? 'selected' : ''; ?>>Out of Stock</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="expiry" class="form-label">Expiry Status</label>
                        <select class="form-select" id="expiry" name="expiry">
                            <option value="">All Expiry Status</option>
                            <option value="expired" <?php echo ($expiry_filter == 'expired') ? 'selected' : ''; ?>>Expired</option>
                            <option value="expiring_soon" <?php echo ($expiry_filter == 'expiring_soon') ? 'selected' : ''; ?>>Expiring Soon</option>
                            <option value="valid" <?php echo ($expiry_filter == 'valid') ? 'selected' : ''; ?>>Valid</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search" placeholder="Product name or batch" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                        <a href="inventory.php" class="btn btn-secondary">
                            <i class="fas fa-undo me-1"></i> Reset
                        </a>
                    </div>
                </form>

                <!-- Inventory Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="inventoryTable">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Batch No</th>
                                <th>Supplier</th>
                                <th>Stock</th>
                                <th>Reorder Level</th>
                                <th>Cost Price</th>
                                <th>Selling Price</th>
                                <th>Expiry Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    // Determine stock status
                                    $stock_status_class = 'bg-success';
                                    $stock_status_text = 'Good';

                                    if ($row['stock_qty'] <= $row['reorder_level'] && $row['stock_qty'] > 0) {
                                        $stock_status_class = 'bg-warning';
                                        $stock_status_text = 'Low';
                                    } elseif ($row['stock_qty'] == 0) {
                                        $stock_status_class = 'bg-danger';
                                        $stock_status_text = 'Out';
                                    }

                                    // Determine expiry status
                                    $expiry_class = 'bg-success';
                                    $expiry_text = 'Valid';
                                    $today = new DateTime();
                                    $expiry = new DateTime($row['expiry_date']);
                                    $diff = $today->diff($expiry);

                                    if ($today > $expiry) {
                                        $expiry_class = 'bg-danger';
                                        $expiry_text = 'Expired';
                                    } elseif ($diff->days <= 30) {
                                        $expiry_class = 'bg-warning';
                                        $expiry_text = 'Expiring Soon';
                                    }
                            ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['category']); ?></td>
                                        <td><?php echo htmlspecialchars($row['batch_no']); ?></td>
                                        <td><?php echo htmlspecialchars($row['supplier_name']); ?></td>
                                        <td class="<?php echo ($row['stock_qty'] <= $row['reorder_level']) ? 'text-danger fw-bold' : ''; ?>">
                                            <?php echo $row['stock_qty']; ?>
                                        </td>
                                        <td><?php echo $row['reorder_level']; ?></td>
                                        <td><?php echo number_format($row['cost_price'], 2); ?></td>
                                        <td><?php echo number_format($row['selling_price'], 2); ?></td>
                                        <td>
                                            <?php
                                            echo date('M d, Y', strtotime($row['expiry_date']));
                                            if ($expiry_text != 'Valid') {
                                                echo ' <span class="badge ' . $expiry_class . '">' . $expiry_text . '</span>';
                                            }
                                            ?>
                                        </td>
                                        <td><span class="badge <?php echo $stock_status_class; ?>"><?php echo $stock_status_text; ?></span></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-primary adjust-stock"
                                                    data-id="<?php echo $row['product_id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($row['name']); ?>"
                                                    data-current="<?php echo $row['stock_qty']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="stock_history.php?id=<?php echo $row['product_id']; ?>" class="btn btn-info">
                                                    <i class="fas fa-history"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                            <?php
                                }
                            } else {
                                echo '<tr><td colspan="11" class="text-center">No products found</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Stock Adjustment Modal -->
<div class="modal fade" id="stockAdjustModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="adjustModalTitle">Adjust Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="adjust_stock.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="product_id" id="adjustProductId">

                    <div id="selectProductDiv">
                        <div class="mb-3">
                            <label for="selectProduct" class="form-label">Select Product</label>
                            <select class="form-select" id="selectProduct" name="product_select" required>
                                <option value="">-- Select Product --</option>
                                <?php
                                // Reset result pointer
                                mysqli_data_seek($result, 0);
                                while ($row = mysqli_fetch_assoc($result)) {
                                    echo '<option value="' . $row['product_id'] . '" data-current="' . $row['stock_qty'] . '">' .
                                        htmlspecialchars($row['name']) . ' (' . htmlspecialchars($row['batch_no']) . ')' . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="productName" class="form-label">Product</label>
                        <input type="text" class="form-control" id="productName" readonly>
                    </div>

                    <div class="mb-3">
                        <label for="currentStock" class="form-label">Current Stock</label>
                        <input type="number" class="form-control" id="currentStock" readonly>
                    </div>

                    <div class="mb-3">
                        <label for="adjustmentType" class="form-label">Adjustment Type</label>
                        <select class="form-select" id="adjustmentType" name="adjustment_type" required>
                            <option value="add">Add Stock</option>
                            <option value="subtract">Subtract Stock</option>
                            <option value="set">Set New Value</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="adjustmentQty" class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="adjustmentQty" name="quantity" min="1" required>
                    </div>

                    <div class="mb-3">
                        <label for="adjustmentReason" class="form-label">Reason for Adjustment</label>
                        <textarea class="form-control" id="adjustmentReason" name="reason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Adjust Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- jQuery and DataTables JS (required for $ and DataTable) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function() {
        // Initialize DataTable
        $('#inventoryTable').DataTable({
            "pageLength": 25,
            "responsive": true,
            "order": [
                [0, 'asc']
            ]
        });

        // Handle adjust stock button
        $('.adjust-stock').click(function() {
            const productId = $(this).data('id');
            const productName = $(this).data('name');
            const currentStock = $(this).data('current');

            $('#adjustProductId').val(productId);
            $('#productName').val(productName);
            $('#currentStock').val(currentStock);

            $('#selectProductDiv').hide();

            $('#stockAdjustModal').modal('show');
        });

        // Handle general adjust stock button
        $('#stockAdjustModal').on('show.bs.modal', function(e) {
            if ($(e.relatedTarget).hasClass('btn-sm')) {
                // If opened from the "Adjust Stock" button
                $('#selectProductDiv').show();
                $('#productName').val('');
                $('#currentStock').val('');
            }
        });

        // Handle product selection
        $('#selectProduct').change(function() {
            const productId = $(this).val();
            const currentStock = $(this).find(':selected').data('current');
            const productName = $(this).find(':selected').text();

            $('#adjustProductId').val(productId);
            $('#currentStock').val(currentStock);
            $('#productName').val(productName);
        });
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>