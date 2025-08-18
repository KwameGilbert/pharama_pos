<?php
// Start output buffering to prevent header issues
ob_start();

// Set page title
$page_title = "Add New Product";

// Include header
include_once '../includes/head.php';

// Get suppliers for dropdown
$supplier_query = "SELECT supplier_id, name FROM suppliers ORDER BY name";
$supplier_result = $conn->query($supplier_query);

// Get categories for dropdown (for suggestions)
$category_query = "SELECT DISTINCT category FROM products ORDER BY category";
$category_result = $conn->query($category_query);

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize inputs
    $name = trim($_POST['name']);
    $category = trim($_POST['category']);
    $supplier_id = intval($_POST['supplier_id']);
    $batch_no = trim($_POST['batch_no']);
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : NULL;
    $cost_price = floatval($_POST['cost_price']);
    $selling_price = floatval($_POST['selling_price']);
    $stock_qty = intval($_POST['stock_qty']);
    $reorder_level = intval($_POST['reorder_level']);

    // Validation
    $errors = [];

    if (empty($name)) {
        $errors[] = "Product name is required";
    }

    if (empty($category)) {
        $errors[] = "Category is required";
    }

    if ($supplier_id <= 0) {
        $errors[] = "Please select a valid supplier";
    }

    if ($cost_price <= 0) {
        $errors[] = "Cost price must be greater than zero";
    }

    if ($selling_price <= 0) {
        $errors[] = "Selling price must be greater than zero";
    }

    if ($selling_price < $cost_price) {
        $errors[] = "Warning: Selling price is less than cost price";
    }

    if ($stock_qty < 0) {
        $errors[] = "Stock quantity cannot be negative";
    }

    if ($reorder_level < 0) {
        $errors[] = "Reorder level cannot be negative";
    }

    // If no errors, proceed with insertion
    if (empty($errors)) {
        // Prepare SQL statement
        $query = "INSERT INTO products (name, category, supplier_id, batch_no, expiry_date, cost_price, selling_price, stock_qty, reorder_level)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($query);

        // Bind parameters regardless of expiry date being NULL or not
        $stmt->bind_param("ssissddii", $name, $category, $supplier_id, $batch_no, $expiry_date, $cost_price, $selling_price, $stock_qty, $reorder_level);

        if ($stmt->execute()) {
            // Log action
            $product_id = $stmt->insert_id;
            $log_action = "Added new product: $name (ID: $product_id)";
            $log_query = "INSERT INTO audit_logs (user_id, action) VALUES (?, ?)";
            $log_stmt = $conn->prepare($log_query);
            $log_stmt->bind_param("is", $_SESSION['admin_id'], $log_action);
            $log_stmt->execute();

            // Set success message and redirect
            $_SESSION['success_msg'] = "Product added successfully!";
            header("Location: ./products.php");
            exit;
        } else {
            $errors[] = "Error: " . $stmt->error;
        }
    }
}
?>

<div class="container-fluid px-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Add New Product</h4>
        <div>
            <a href="products.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Products
            </a>
        </div>
    </div>

    <!-- Form Card -->
    <div class="card">
        <div class="card-body">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="" class="row g-3">
                <!-- Product Name -->
                <div class="col-md-6">
                    <label for="name" class="form-label">Product Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                </div>

                <!-- Category -->
                <div class="col-md-6">
                    <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="category" name="category" list="categoryList" value="<?php echo isset($_POST['category']) ? htmlspecialchars($_POST['category']) : ''; ?>" required>
                    <datalist id="categoryList">
                        <?php while ($category = $category_result->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($category['category']); ?>">
                            <?php endwhile; ?>
                    </datalist>
                </div>

                <!-- Supplier -->
                <div class="col-md-6">
                    <label for="supplier_id" class="form-label">Supplier <span class="text-danger">*</span></label>
                    <select class="form-select" id="supplier_id" name="supplier_id" required>
                        <option value="">Select Supplier</option>
                        <?php while ($supplier = $supplier_result->fetch_assoc()): ?>
                            <option value="<?php echo $supplier['supplier_id']; ?>" <?php echo isset($_POST['supplier_id']) && $_POST['supplier_id'] == $supplier['supplier_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($supplier['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Batch Number -->
                <div class="col-md-6">
                    <label for="batch_no" class="form-label">Batch Number</label>
                    <input type="text" class="form-control" id="batch_no" name="batch_no" value="<?php echo isset($_POST['batch_no']) ? htmlspecialchars($_POST['batch_no']) : ''; ?>">
                </div>

                <!-- Expiry Date -->
                <div class="col-md-4">
                    <label for="expiry_date" class="form-label">Expiry Date</label>
                    <input type="date" class="form-control" id="expiry_date" name="expiry_date" value="<?php echo isset($_POST['expiry_date']) ? $_POST['expiry_date'] : ''; ?>">
                </div>

                <!-- Cost Price -->
                <div class="col-md-4">
                    <label for="cost_price" class="form-label">Cost Price (₵) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" class="form-control" id="cost_price" name="cost_price" value="<?php echo isset($_POST['cost_price']) ? $_POST['cost_price'] : ''; ?>" required>
                </div>

                <!-- Selling Price -->
                <div class="col-md-4">
                    <label for="selling_price" class="form-label">Selling Price (₵) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" class="form-control" id="selling_price" name="selling_price" value="<?php echo isset($_POST['selling_price']) ? $_POST['selling_price'] : ''; ?>" required>
                </div>

                <!-- Initial Stock Quantity -->
                <div class="col-md-6">
                    <label for="stock_qty" class="form-label">Initial Stock Quantity <span class="text-danger">*</span></label>
                    <input type="number" step="1" min="0" class="form-control" id="stock_qty" name="stock_qty" value="<?php echo isset($_POST['stock_qty']) ? $_POST['stock_qty'] : '0'; ?>" required>
                </div>

                <!-- Reorder Level -->
                <div class="col-md-6">
                    <label for="reorder_level" class="form-label">Reorder Level <span class="text-danger">*</span></label>
                    <input type="number" step="1" min="0" class="form-control" id="reorder_level" name="reorder_level" value="<?php echo isset($_POST['reorder_level']) ? $_POST['reorder_level'] : '5'; ?>" required>
                    <div class="form-text">Stock quantity alert threshold</div>
                </div>

                <div class="col-12 mt-4">
                    <hr>
                    <div class="d-flex justify-content-end">
                        <button type="reset" class="btn btn-outline-secondary me-2">Reset</button>
                        <button type="submit" class="btn btn-primary">Add Product</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript for form validation -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get form elements
        const form = document.querySelector('form');
        const costInput = document.getElementById('cost_price');
        const sellingInput = document.getElementById('selling_price');

        // Add event listener for form submission
        form.addEventListener('submit', function(event) {
            // Validate cost price
            if (parseFloat(costInput.value) <= 0) {
                alert('Cost price must be greater than zero');
                costInput.focus();
                event.preventDefault();
                return false;
            }

            // Validate selling price
            if (parseFloat(sellingInput.value) <= 0) {
                alert('Selling price must be greater than zero');
                sellingInput.focus();
                event.preventDefault();
                return false;
            }

            // Warning if selling price is less than cost price
            if (parseFloat(sellingInput.value) < parseFloat(costInput.value)) {
                if (!confirm('Warning: Selling price is less than cost price. Are you sure you want to continue?')) {
                    event.preventDefault();
                    return false;
                }
            }

            return true;
        });

        // Calculate profit margin
        function updateMargin() {
            const cost = parseFloat(costInput.value) || 0;
            const selling = parseFloat(sellingInput.value) || 0;

            if (cost > 0 && selling > 0) {
                const margin = ((selling - cost) / selling) * 100;
                const profit = selling - cost;

                // Display margin information somewhere if needed
                console.log(`Profit: ₵${profit.toFixed(2)}, Margin: ${margin.toFixed(2)}%`);
            }
        }

        // Add event listeners for price changes
        costInput.addEventListener('change', updateMargin);
        sellingInput.addEventListener('change', updateMargin);
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';

// End output buffering and send the output
ob_end_flush();
?>