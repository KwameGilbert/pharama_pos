<?php
// Include header
include_once '../include/head.php';

// Set default filter values
$category = isset($_GET['category']) ? $_GET['category'] : '';
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
    $query .= " AND p.category = '" . mysqli_real_escape_string($conn, $category) . "'";
}
if (!empty($search)) {
    $query .= " AND (p.name LIKE '%" . mysqli_real_escape_string($conn, $search) . "%' 
                OR p.batch_no LIKE '%" . mysqli_real_escape_string($conn, $search) . "%')";
}
if ($stock == 'low') {
    $query .= " AND p.stock_qty <= p.reorder_level";
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
$result = mysqli_query($conn, $query);

// Get categories for filter
$category_query = "SELECT DISTINCT category FROM products ORDER BY category";
$category_result = mysqli_query($conn, $category_query);
?>

<!-- Products Page -->
<h2 class="mb-4">Products</h2>

<!-- Filter Controls -->
<div class="dashboard-card bg-white mb-4">
    <form method="get" class="row g-3">
        <div class="col-md-3">
            <label for="search" class="form-label">Search</label>
            <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Product name or batch">
        </div>
        <div class="col-md-3">
            <label for="category" class="form-label">Category</label>
            <select class="form-select" id="category" name="category">
                <option value="">All Categories</option>
                <?php while ($cat = mysqli_fetch_assoc($category_result)): ?>
                    <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo $category == $cat['category'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['category']); ?>
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
            <div class="d-grid w-100">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter me-1"></i> Apply Filter
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Products Table -->
<div class="dashboard-card bg-white">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Batch</th>
                    <th>Stock Qty</th>
                    <th>Price</th>
                    <th>Expiry Date</th>
                    <th>Supplier</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($result) > 0): ?>
                    <?php while ($product = mysqli_fetch_assoc($result)):
                        // Check expiry status
                        $expiry_class = '';
                        $expiry_text = '';

                        if (!empty($product['expiry_date'])) {
                            $expiry_date = new DateTime($product['expiry_date']);
                            $today = new DateTime();
                            $diff = $today->diff($expiry_date);
                            $days_to_expiry = $diff->invert ? -$diff->days : $diff->days;

                            if ($days_to_expiry < 0) {
                                $expiry_class = 'text-white bg-danger';
                                $expiry_text = 'EXPIRED';
                            } elseif ($days_to_expiry <= 30) {
                                $expiry_class = 'text-dark bg-warning';
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
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td><?php echo htmlspecialchars($product['category']); ?></td>
                            <td><?php echo htmlspecialchars($product['batch_no']); ?></td>
                            <td class="<?php echo $stock_class; ?>">
                                <?php echo $product['stock_qty']; ?>
                                <?php if ($product['stock_qty'] <= $product['reorder_level'] && $product['stock_qty'] > 0): ?>
                                    <span class="badge bg-warning text-dark">Low</span>
                                <?php elseif ($product['stock_qty'] <= 0): ?>
                                    <span class="badge bg-danger">Out</span>
                                <?php endif; ?>
                            </td>
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
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center py-4">No products found matching your criteria</td>
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