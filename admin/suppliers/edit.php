<?php
// Start output buffering to prevent header issues
ob_start();

// Set page title
$page_title = "Edit Supplier";

// Include header
include_once '../includes/head.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Supplier ID is missing";
    header("Location: index.php");
    exit();
}

$supplier_id = (int)$_GET['id'];

// Get supplier information
$query = "SELECT * FROM suppliers WHERE supplier_id = ?";
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

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = trim($_POST['name']);
    $contact_person = trim($_POST['contact_person']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);

    // Validate inputs
    $errors = [];

    if (empty($name)) {
        $errors[] = "Supplier name is required";
    }

    if (empty($phone)) {
        $errors[] = "Phone number is required";
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }

    // Check if supplier with same name already exists (excluding current supplier)
    $check_query = "SELECT * FROM suppliers WHERE name = ? AND supplier_id != ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param('si', $name, $supplier_id);
    $stmt->execute();
    $check_result = $stmt->get_result();

    if ($check_result->num_rows > 0) {
        $errors[] = "A supplier with this name already exists";
    }

    // If no errors, update the supplier
    if (empty($errors)) {
        $update_query = "UPDATE suppliers SET 
                        name = ?,
                        contact_person = ?,
                        phone = ?,
                        email = ?,
                        address = ?
                        WHERE supplier_id = ?";

        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('sssssi', $name, $contact_person, $phone, $email, $address, $supplier_id);

        if ($stmt->execute()) {
            $_SESSION['success'] = "Supplier updated successfully";
            header("Location: index.php");
            exit();
        } else {
            $_SESSION['error'] = "Error updating supplier: " . $conn->error;
        }
    }
}
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mt-3">Edit Supplier</h3>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Suppliers
        </a>
    </div>

    <?php if (isset($errors) && !empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <form method="post" action="">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Supplier Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($supplier['name']); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="contact_person" class="form-label">Contact Person</label>
                        <input type="text" class="form-control" id="contact_person" name="contact_person" value="<?php echo htmlspecialchars($supplier['contact_person']); ?>">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
                        <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($supplier['phone']); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($supplier['email']); ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="address" class="form-label">Address</label>
                    <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($supplier['address']); ?></textarea>
                </div>

                <!-- Status field removed as it's not in the database schema -->

                <!-- Notes field removed as it's not in the database schema -->

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Supplier
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';

// End output buffering and send the output
ob_end_flush();
?>