<?php
// Set page title
$page_title = "Customers";

// Include header
include_once '../includes/head.php';

// Delete customer functionality
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $customer_id = (int)$_GET['delete'];

    // Check if customer has orders
    $check_orders = $conn->query("SELECT COUNT(*) as count FROM invoices WHERE customer_id = $customer_id");
    $has_orders = $check_orders->fetch_assoc()['count'] > 0;

    if ($has_orders) {
        $_SESSION['error'] = "Cannot delete customer. There are sales records associated with this customer.";
    } else {
        // Delete the customer
        $delete_query = "DELETE FROM customers WHERE customer_id = $customer_id";
        if ($conn->query($delete_query)) {
            $_SESSION['success'] = "Customer deleted successfully.";
        } else {
            $_SESSION['error'] = "Error deleting customer: " . $conn->error;
        }
    }

    // Redirect to refresh page
    header("Location: index.php");
    exit();
}
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mt-3">Customers Management</h3>
       
        ass="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Customer
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
            <i class="fas fa-users me-1"></i>
            All Customers
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="datatablesSimple" class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Address</th>
                            <th>Purchases</th>
                            <th>Total Spent</th>
                            <th>Last Purchase</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Fetch all customers with additional statistics
                        $query = "SELECT c.*, 
                                 COUNT(i.invoice_id) as purchase_count,
                                 SUM(CASE WHEN i.payment_status = 'paid' THEN i.total_amount ELSE 0 END) as total_spent,
                                 MAX(i.created_at) as last_purchase_date
                                 FROM customers c
                                 LEFT JOIN invoices i ON c.customer_id = i.customer_id
                                 GROUP BY c.customer_id
                                 ORDER BY c.name";

                        $result = $conn->query($query);

                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                        ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td><?php echo htmlspecialchars($row['address']); ?></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $row['purchase_count']; ?></span>
                                        <?php if ($row['purchase_count'] > 0): ?>
                                            <a href="../sales/index.php?customer=<?php echo $row['customer_id']; ?>" class="btn btn-sm btn-outline-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td>₵<?php echo number_format($row['total_spent'], 2); ?></td>
                                    <td>
                                        <?php
                                        if ($row['last_purchase_date']) {
                                            echo date('M d, Y', strtotime($row['last_purchase_date']));
                                        } else {
                                            echo '<span class="text-muted">No purchases</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <a href="edit.php?id=<?php echo $row['customer_id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="view.php?id=<?php echo $row['customer_id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $row['customer_id']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>

                                        <!-- Delete Modal -->
                                        <div class="modal fade" id="deleteModal<?php echo $row['customer_id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to delete customer <strong><?php echo htmlspecialchars($row['name']); ?></strong>?</p>
                                                        <?php if ($row['purchase_count'] > 0): ?>
                                                            <div class="alert alert-warning">
                                                                <i class="fas fa-exclamation-triangle"></i> This customer has <?php echo $row['purchase_count']; ?> associated sales records. You cannot delete customers with sales history.
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <a href="index.php?delete=<?php echo $row['customer_id']; ?>" class="btn btn-danger" <?php echo $row['purchase_count'] > 0 ? 'disabled' : ''; ?>>Delete</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                        <?php
                            }
                        } else {
                            echo '<tr><td colspan="8" class="text-center">No customers found</td></tr>';
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
   document.addEventListener("DOMContentLoaded", function() {
        $('#datatablesSimple').DataTable({
            responsive: true
        });
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>