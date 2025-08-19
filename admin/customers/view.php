<?php
$page_title = "View Customer";
include_once '../includes/head.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$customer_id = (int)$_GET['id'];

// Fetch customer details + sales stats
$query = "SELECT c.*, 
         COUNT(i.invoice_id) as purchase_count,
         SUM(CASE WHEN i.payment_status='paid' THEN i.total_amount ELSE 0 END) as total_spent,
         MAX(i.created_at) as last_purchase_date
         FROM customers c
         LEFT JOIN invoices i ON c.customer_id = i.customer_id
         WHERE c.customer_id = $customer_id
         GROUP BY c.customer_id";

$result = $conn->query($query);
if ($result->num_rows == 0) {
    $_SESSION['error'] = "Customer not found.";
    header("Location: index.php");
    exit();
}
$customer = $result->fetch_assoc();
?>

<div class="container-fluid px-4">
    <h3 class="mt-3">Customer Details</h3>
    <div class="card mb-4">
        <div class="card-body">
            <p><strong>Name:</strong> <?php echo htmlspecialchars($customer['name']); ?></p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($customer['phone']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($customer['email']); ?></p>
            <p><strong>Address:</strong> <?php echo htmlspecialchars($customer['address']); ?></p>
            <p><strong>Purchases:</strong> <?php echo $customer['purchase_count']; ?></p>
            <p><strong>Total Spent:</strong> ₵<?php echo number_format($customer['total_spent'], 2); ?></p>
            <p><strong>Last Purchase:</strong> 
                <?php echo $customer['last_purchase_date'] ? date('M d, Y', strtotime($customer['last_purchase_date'])) : 'No purchases'; ?>
            </p>
        </div>
    </div>
    <a href="index.php" class="btn btn-secondary">Back to Customers</a>
</div>

<?php include_once '../includes/footer.php'; ?>
