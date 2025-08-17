<?php
// Start output buffering to prevent header issues
ob_start();

// Set page title
$page_title = "Supplier Details";

// Include header
include_once '../includes/header.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Supplier ID is missing";
    header("Location: index.php");
    exit();
}

$supplier_id = (int)$_GET['id'];

// Get supplier information
$query = "SELECT s.*, COUNT(p.product_id) as product_count 
          FROM suppliers s 
          LEFT JOIN products p ON s.supplier_id = p.supplier_id 
          WHERE s.supplier_id = ?
          GROUP BY s.supplier_id";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $supplier_id);
$stmt->execute();
$result = $stmt->get_result();

// Check if supplier exists
if ($result->num_rows === 0) {
    $_SESSION['error'] = "Supplier not found";
    header("Location: index.php");
    exit();
}

$supplier = $result->fetch_assoc();

// Get products from this supplier
$products_query = "SELECT p.*, 
                  CASE WHEN p.expiry_date < CURDATE() THEN 'expired'
                       WHEN p.stock_qty <= p.reorder_level THEN 'low'
                       ELSE 'normal' END as stock_status
                  FROM products p
                  WHERE p.supplier_id = ?
                  ORDER BY p.name";

$stmt = $conn->prepare($products_query);
$stmt->bind_param('i', $supplier_id);
$stmt->execute();
$products_result = $stmt->get_result();
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mt-3">Supplier Details</h3>
        <div>
            <a href="edit.php?id=<?php echo $supplier_id; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Suppliers
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-info-circle me-1"></i>
                    Supplier Information
                </div>
                <div class="card-body">
                    <table class="table">
                        <tbody>
                            <tr>
                                <th width="30%">Supplier Name</th>
                                <td><?php echo htmlspecialchars($supplier['name']); ?></td>
                            </tr>
                            <tr>
                                <th>Contact Person</th>
                                <td><?php echo htmlspecialchars($supplier['contact_person']); ?></td>
                            </tr>
                            <tr>
                                <th>Phone</th>
                                <td><?php echo htmlspecialchars($supplier['phone']); ?></td>
                            </tr>
                            <tr>
                                <th>Email</th>
                                <td><?php echo htmlspecialchars($supplier['email']); ?></td>
                            </tr>
                            <tr>
                                <th>Address</th>
                                <td><?php echo htmlspecialchars($supplier['address']); ?></td>
                            </tr>
                            <!-- Status field removed as it's not in the database schema -->
                            <tr>
                                <th>Products</th>
                                <td><span class="badge bg-info"><?php echo $supplier['product_count']; ?></span></td>
                            </tr>
                            <!-- Notes field removed as it's not in the database schema -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-clipboard-list me-1"></i>
                    Activity Summary
                </div>
                <div class="card-body">
                    <?php
                    // Purchase history
                    $purchase_query = "SELECT COUNT(*) as total_purchases,
                                      SUM(p.cost_price * p.stock_qty) as total_purchase_value
                                      FROM products p
                                      WHERE p.supplier_id = ?";

                    $stmt = $conn->prepare($purchase_query);
                    $stmt->bind_param('i', $supplier_id);
                    $stmt->execute();
                    $purchase_data = $stmt->get_result()->fetch_assoc();
                    ?>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card bg-primary text-white h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="me-3">
                                            <div class="text-white-75">Total Products</div>
                                            <div class="display-6"><?php echo $supplier['product_count']; ?></div>
                                        </div>
                                        <i class="fas fa-boxes fa-2x text-white-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <div class="card bg-success text-white h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="me-3">
                                            <div class="text-white-75">Inventory Value</div>
                                            <div class="display-6">₵<?php echo number_format($purchase_data['total_purchase_value'], 2); ?></div>
                                        </div>
                                        <i class="fas fa-money-bill-wave fa-2x text-white-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Latest activity or additional info here -->
                    <div class="alert alert-info">
                        <h5 class="alert-heading">Supply History</h5>
                        <p>This supplier has been providing products since <?php echo date('F j, Y', strtotime($supplier['created_at'])); ?>.</p>
                        <hr>
                        <p class="mb-0">Contact them at <?php echo htmlspecialchars($supplier['phone']); ?> for new orders or inquiries.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-pills me-1"></i>
            Products from this Supplier
        </div>
        <div class="card-body">
            <?php if ($products_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Batch #</th>
                                <th>Expiry Date</th>
                                <th>Stock</th>
                                <th>Cost Price</th>
                                <th>Selling Price</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($product = $products_result->fetch_assoc()): ?>
                                <tr class="<?php echo ($product['stock_status'] == 'expired') ? 'table-danger' : (($product['stock_status'] == 'low') ? 'table-warning' : ''); ?>">
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['category']); ?></td>
                                    <td><?php echo htmlspecialchars($product['batch_no']); ?></td>
                                    <td>
                                        <?php
                                        $expiry_date = new DateTime($product['expiry_date']);
                                        $now = new DateTime();
                                        echo $expiry_date->format('M d, Y');

                                        if ($expiry_date < $now) {
                                            echo ' <span class="badge bg-danger">Expired</span>';
                                        } elseif ($expiry_date->diff($now)->days < 30) {
                                            echo ' <span class="badge bg-warning">Expiring soon</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        echo $product['stock_qty'];
                                        if ($product['stock_qty'] <= $product['reorder_level']) {
                                            echo ' <span class="badge bg-warning">Low stock</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>₵<?php echo number_format($product['cost_price'], 2); ?></td>
                                    <td>₵<?php echo number_format($product['selling_price'], 2); ?></td>
                                    <td>
                                        <a href="../products/view.php?id=<?php echo $product['product_id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="../products/edit.php?id=<?php echo $product['product_id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted">No products available from this supplier.</p>
                <a href="../products/add.php?supplier_id=<?php echo $supplier_id; ?>" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add New Product from this Supplier
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';

// End output buffering and send the output
ob_end_flush();
?>