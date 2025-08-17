<?php
// Set page title
$page_title = "Products Management";

// Start output buffering to prevent header issues
ob_start();

// Include header
include_once '../includes/header.php';

// Set default filter values
$category = isset($_GET['category']) ? $_GET['category'] : '';
$supplier = isset($_GET['supplier']) ? $_GET['supplier'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$stock = isset($_GET['stock']) ? $_GET['stock'] : '';
$expiry = isset($_GET['expiry']) ? $_GET['expiry'] : '';

// Build query
$query = "SELECT p.*, s.name as supplier_name 
          FROM products p
          LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
          WHERE 1=1";

// Apply filters
if (!empty($category)) {
    $query .= " AND p.category = '" . $conn->real_escape_string($category) . "'";
}
if (!empty($supplier)) {
    $query .= " AND p.supplier_id = '" . $conn->real_escape_string($supplier) . "'";
}
if (!empty($search)) {
    $query .= " AND (p.name LIKE '%" . $conn->real_escape_string($search) . "%' 
                OR p.batch_no LIKE '%" . $conn->real_escape_string($search) . "%')";
}
if ($stock == 'low') {
    $query .= " AND p.stock_qty <= p.reorder_level AND p.stock_qty > 0";
} elseif ($stock == 'out') {
    $query .= " AND p.stock_qty = 0";
} elseif ($stock == 'available') {
    $query .= " AND p.stock_qty > 0";
}

if ($expiry == 'expired') {
    $query .= " AND p.expiry_date < CURDATE()";
} elseif ($expiry == 'soon') {
    $query .= " AND p.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
}

// Order by name
$query .= " ORDER BY p.name";

// Execute query
$result = $conn->query($query);

// Get categories for filter
$category_query = "SELECT DISTINCT category FROM products ORDER BY category";
$category_result = $conn->query($category_query);

// Get suppliers for filter
$supplier_query = "SELECT supplier_id, name FROM suppliers ORDER BY name";
$supplier_result = $conn->query($supplier_query);
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Products Management</h4>
    <div>
        <a href="add.php" class="btn btn-success">
            <i class="fas fa-plus-circle"></i> Add New Product
        </a>
        <div class="btn-group ms-2">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-file-export"></i> Export
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="#" id="exportCSV">CSV Format</a></li>
                <li><a class="dropdown-item" href="#" id="exportExcel">Excel Format</a></li>
            </ul>
        </div>
        <a href="#" class="btn btn-outline-primary ms-2" data-bs-toggle="modal" data-bs-target="#importModal">
            <i class="fas fa-file-import"></i> Import
        </a>
    </div>
</div>

<!-- Filter Section -->
<div class="dashboard-card mb-4">
    <h5 class="mb-3">Filter Products</h5>
    <form method="get" action="" class="row g-3">
        <div class="col-md-2">
            <label for="search" class="form-label">Search</label>
            <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name or batch no">
        </div>
        <div class="col-md-2">
            <label for="category" class="form-label">Category</label>
            <select class="form-select" id="category" name="category">
                <option value="">All Categories</option>
                <?php while ($cat = $category_result->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo $category == $cat['category'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['category']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label for="supplier" class="form-label">Supplier</label>
            <select class="form-select" id="supplier" name="supplier">
                <option value="">All Suppliers</option>
                <?php while ($sup = $supplier_result->fetch_assoc()): ?>
                    <option value="<?php echo $sup['supplier_id']; ?>" <?php echo $supplier == $sup['supplier_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($sup['name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label for="stock" class="form-label">Stock</label>
            <select class="form-select" id="stock" name="stock">
                <option value="">All Stock</option>
                <option value="available" <?php echo $stock == 'available' ? 'selected' : ''; ?>>Available</option>
                <option value="low" <?php echo $stock == 'low' ? 'selected' : ''; ?>>Low Stock</option>
                <option value="out" <?php echo $stock == 'out' ? 'selected' : ''; ?>>Out of Stock</option>
            </select>
        </div>
        <div class="col-md-2">
            <label for="expiry" class="form-label">Expiry</label>
            <select class="form-select" id="expiry" name="expiry">
                <option value="">All</option>
                <option value="expired" <?php echo $expiry == 'expired' ? 'selected' : ''; ?>>Expired</option>
                <option value="soon" <?php echo $expiry == 'soon' ? 'selected' : ''; ?>>Expiring Soon</option>
            </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <div class="d-grid gap-2 w-100">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <a href="products.php" class="btn btn-outline-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Products Table -->
<div class="dashboard-card">
    <div class="table-responsive">
        <table class="table table-hover display" id="productsTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Stock</th>
                    <th>Cost Price</th>
                    <th>Selling Price</th>
                    <th>Expiry Date</th>
                    <th>Supplier</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($product = $result->fetch_assoc()):
                        // Check expiry status
                        $expiry_class = '';
                        $expiry_text = '';

                        if (!empty($product['expiry_date'])) {
                            $expiry_date = new DateTime($product['expiry_date']);
                            $today = new DateTime();
                            $diff = $today->diff($expiry_date);
                            $days_to_expiry = $diff->invert ? -$diff->days : $diff->days;

                            if ($days_to_expiry < 0) {
                                $expiry_class = 'table-danger';
                                $expiry_text = 'EXPIRED';
                            } elseif ($days_to_expiry <= 30) {
                                $expiry_class = 'table-warning';
                                $expiry_text = 'Expires in ' . $days_to_expiry . ' days';
                            }
                        }

                        // Check stock status
                        $stock_class = '';
                        if ($product['stock_qty'] <= 0) {
                            $stock_class = 'text-white bg-danger';
                        } elseif ($product['stock_qty'] <= $product['reorder_level']) {
                            $stock_class = 'text-dark bg-warning';
                        }
                    ?>
                        <tr>
                            <td><?php echo $product['product_id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                <br>
                                <small class="text-muted">Batch: <?php echo htmlspecialchars($product['batch_no']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($product['category']); ?></td>
                            <td class="<?php echo $stock_class; ?>">
                                <?php echo $product['stock_qty']; ?>
                                <?php if ($product['stock_qty'] <= $product['reorder_level'] && $product['stock_qty'] > 0): ?>
                                    <span class="badge bg-warning text-dark">Low</span>
                                <?php elseif ($product['stock_qty'] <= 0): ?>
                                    <span class="badge bg-danger">Out</span>
                                <?php endif; ?>
                            </td>
                            <td>₵<?php echo number_format($product['cost_price'], 2); ?></td>
                            <td>₵<?php echo number_format($product['selling_price'], 2); ?></td>
                            <td class="<?php echo $expiry_class; ?>">
                                <?php if (!empty($product['expiry_date'])): ?>
                                    <?php echo date('M d, Y', strtotime($product['expiry_date'])); ?>
                                    <?php if (!empty($expiry_text)): ?>
                                        <span class="d-block small"><?php echo $expiry_text; ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($product['supplier_name']); ?></td>
                            <td>
                                <div class="btn-group">
                                    <a href="edit.php?id=<?php echo $product['product_id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="view.php?id=<?php echo $product['product_id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="#" class="btn btn-sm btn-danger delete-product" data-id="<?php echo $product['product_id']; ?>" data-name="<?php echo htmlspecialchars($product['name']); ?>">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center">No products found matching your criteria</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importModalLabel">Import Products</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="import.php" method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="importFile" class="form-label">Choose CSV or Excel File</label>
                        <input class="form-control" type="file" id="importFile" name="importFile" accept=".csv, .xlsx" required>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="updateExisting" name="updateExisting">
                            <label class="form-check-label" for="updateExisting">
                                Update existing products
                            </label>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <h6>Import Instructions</h6>
                        <p class="small mb-0">Ensure your file has these columns: name, category, batch_no, cost_price, selling_price, stock_qty, expiry_date (YYYY-MM-DD format), supplier_id</p>
                        <p class="small mb-0">Download a <a href="template.csv">template file</a> to get started.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Import Products</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the product: <strong id="deleteProductName"></strong>?</p>
                <p class="text-danger">This action cannot be undone and may affect sales reports.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" action="delete.php" method="post">
                    <input type="hidden" id="deleteProductId" name="product_id">
                    <button type="submit" class="btn btn-danger">Delete Product</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize DataTable
        $('#productsTable').DataTable({
            lengthMenu: [
                [10, 25, 50, -1],
                [10, 25, 50, "All"]
            ],
            pageLength: 25,
            dom: 'Bfrtip',
            buttons: [
                'copy', 'csv', 'excel', 'pdf'
            ]
        });

        // Delete Product
        document.querySelectorAll('.delete-product').forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();

                const productId = this.getAttribute('data-id');
                const productName = this.getAttribute('data-name');

                document.getElementById('deleteProductId').value = productId;
                document.getElementById('deleteProductName').textContent = productName;

                new bootstrap.Modal(document.getElementById('deleteModal')).show();
            });
        });

        // Export functionality
        document.getElementById('exportCSV').addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = 'export.php?format=csv' + getFilterParams();
        });

        document.getElementById('exportExcel').addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = 'export.php?format=excel' + getFilterParams();
        });

        // Helper function to get current filter parameters
        function getFilterParams() {
            const params = new URLSearchParams(window.location.search);
            return '&' + params.toString();
        }
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';

// End output buffering and flush
ob_end_flush();
?>