<?php
$page_title = "Edit Customer";
include_once '../includes/head.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$customer_id = (int)$_GET['id'];

// Fetch customer details
$result = $conn->query("SELECT * FROM customers WHERE customer_id = $customer_id");
if ($result->num_rows == 0) {
    $_SESSION['error'] = "Customer not found.";
    header("Location: index.php");
    exit();
}
$customer = $result->fetch_assoc();

// Handle update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $conn->real_escape_string($_POST['name']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $email = $conn->real_escape_string($_POST['email']);
    $address = $conn->real_escape_string($_POST['address']);

    $query = "UPDATE customers SET name='$name', phone='$phone', email='$email', address='$address'
              WHERE customer_id=$customer_id";

    if ($conn->query($query)) {
        $_SESSION['success'] = "Customer updated successfully.";
        header("Location: index.php");
        exit();
    } else {
        $_SESSION['error'] = "Error updating customer: " . $conn->error;
    }
}
?>

<div class="container-fluid px-4">
    <h3 class="mt-3">Edit Customer</h3>
    <form method="POST" action="">
        <div class="mb-3">
            <label>Name</label>
            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($customer['name']); ?>" required>
        </div>
        <div class="mb-3">
            <label>Phone</label>
            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($customer['phone']); ?>">
        </div>
        <div class="mb-3">
            <label>Email</label>
            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($customer['email']); ?>">
        </div>
        <div class="mb-3">
            <label>Address</label>
            <textarea name="address" class="form-control"><?php echo htmlspecialchars($customer['address']); ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Update Customer</button>
        <a href="index.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php include_once '../includes/footer.php'; ?>
