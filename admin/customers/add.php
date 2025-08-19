<?php
$page_title = "Add Customer";
include_once '../includes/head.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $conn->real_escape_string($_POST['name']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $email = $conn->real_escape_string($_POST['email']);
    $address = $conn->real_escape_string($_POST['address']);

    if (!empty($name)) {
        $query = "INSERT INTO customers (name, phone, email, address) 
                  VALUES ('$name', '$phone', '$email', '$address')";
        if ($conn->query($query)) {
            $_SESSION['success'] = "Customer added successfully.";
            header("Location: index.php");
            exit();
        } else {
            $_SESSION['error'] = "Error: " . $conn->error;
        }
    } else {
        $_SESSION['error'] = "Name is required.";
    }
}
?>

<div class="container-fluid px-4">
    <h3 class="mt-3">Add Customer</h3>
    <form method="POST" action="">
        <div class="mb-3">
            <label>Name</label>
            <input type="text" name="name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Phone</label>
            <input type="text" name="phone" class="form-control">
        </div>
        <div class="mb-3">
            <label>Email</label>
            <input type="email" name="email" class="form-control">
        </div>
        <div class="mb-3">
            <label>Address</label>
            <textarea name="address" class="form-control"></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Save Customer</button>
        <a href="index.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php include_once '../includes/footer.php'; ?>
