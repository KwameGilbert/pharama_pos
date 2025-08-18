<?php
// Start output buffering to prevent header issues
ob_start();

// Set page title
$page_title = "Suppliers";

// Include header
include_once '../includes/head.php';

// Delete supplier functionality
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $supplier_id = (int)$_GET['delete'];

    // Check if supplier has products
    $check_products = $conn->query("SELECT COUNT(*) as count FROM products WHERE supplier_id = $supplier_id");
    $has_products = $check_products->fetch_assoc()['count'] > 0;

    if ($has_products) {
        $_SESSION['error'] = "Cannot delete supplier. There are products associated with this supplier.";
    } else {
        // Delete the supplier
        $delete_query = "DELETE FROM suppliers WHERE supplier_id = $supplier_id";
        if ($conn->query($delete_query)) {
            $_SESSION['success'] = "Supplier deleted successfully.";
        } else {
            $_SESSION['error'] = "Error deleting supplier: " . $conn->error;
        }
    }

    // Redirect to refresh page
    header("Location: index.php");
    exit();
}
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mt-3">Suppliers Management</h3>
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Supplier
        </a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php
            echo $_SESSION['error'];
            unset($_SESSION['error']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-truck me-1"></i>
            All Suppliers
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="datatablesSimple" class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Supplier Name</th>
                            <th>Contact Person</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Address</th>
                            <th>Products</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Fetch all suppliers
                        $query = "SELECT s.*, COUNT(p.product_id) as product_count 
                                  FROM suppliers s 
                                  LEFT JOIN products p ON s.supplier_id = p.supplier_id 
                                  GROUP BY s.supplier_id 
                                  ORDER BY s.name";

                        $result = $conn->query($query);

                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                        ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['contact_person']); ?></td>
                                    <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td><?php echo htmlspecialchars($row['address']); ?></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $row['product_count']; ?></span>
                                        <?php if ($row['product_count'] > 0): ?>
                                            <a href="../products/products.php?supplier=<?php echo $row['supplier_id']; ?>" class="btn btn-sm btn-outline-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <!-- Status column removed as it's not in the database schema -->
                                    <td>
                                        <a href="edit.php?id=<?php echo $row['supplier_id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="view.php?id=<?php echo $row['supplier_id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $row['supplier_id']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>

                                        <!-- Delete Modal -->
                                        <div class="modal fade" id="deleteModal<?php echo $row['supplier_id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to delete supplier <strong><?php echo htmlspecialchars($row['name']); ?></strong>?</p>
                                                        <?php if ($row['product_count'] > 0): ?>
                                                            <div class="alert alert-warning">
                                                                <i class="fas fa-exclamation-triangle"></i> This supplier has <?php echo $row['product_count']; ?> associated products. You must reassign these products before deleting.
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <a href="index.php?delete=<?php echo $row['supplier_id']; ?>" class="btn btn-danger">Delete</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                        <?php
                            }
                        } else {
                            echo '<tr><td colspan="8" class="text-center">No suppliers found</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize DataTables
    $(document).ready(function() {
        $('#datatablesSimple').DataTable({
            responsive: true
        });
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';

// End output buffering and send the output
ob_end_flush();
?>