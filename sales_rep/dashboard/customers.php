<?php
// Include header
include_once '../include/head.php';

// Set search parameter
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query
$query = "SELECT * FROM customers WHERE 1=1";

// Apply search filter
if (!empty($search)) {
    $query .= " AND (name LIKE '%" . mysqli_real_escape_string($conn, $search) . "%' 
               OR phone LIKE '%" . mysqli_real_escape_string($conn, $search) . "%'
               OR email LIKE '%" . mysqli_real_escape_string($conn, $search) . "%')";
}

// Order by name
$query .= " ORDER BY name";

// Execute query
$result = mysqli_query($conn, $query);
?>

<!-- Customers Page -->
<h2 class="mb-4">Customers</h2>

<!-- Search Controls -->
<div class="dashboard-card bg-white mb-4">
    <form method="get" class="row g-3">
        <div class="col-md-8">
            <label for="search" class="form-label">Search Customer</label>
            <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, phone or email">
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <div class="d-grid w-100">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i> Search
                </button>
            </div>
        </div>
    </form>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#newCustomerModal">
            <i class="fas fa-user-plus"></i> Add New Customer
        </button>
    </div>
</div>

<!-- Customers Table -->
<div class="dashboard-card bg-white">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Address</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($result) > 0): ?>
                    <?php while ($customer = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><?php echo $customer['customer_id']; ?></td>
                            <td><?php echo htmlspecialchars($customer['name']); ?></td>
                            <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                            <td><?php echo htmlspecialchars($customer['email'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($customer['address'] ?? ''); ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-info view-customer" data-id="<?php echo $customer['customer_id']; ?>">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center py-4">No customers found matching your criteria</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- New Customer Modal -->
<div class="modal fade" id="newCustomerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="newCustomerForm">
                    <div class="mb-3">
                        <label for="customerName" class="form-label">Customer Name</label>
                        <input type="text" class="form-control" id="customerName" required>
                    </div>
                    <div class="mb-3">
                        <label for="customerPhone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="customerPhone" required>
                    </div>
                    <div class="mb-3">
                        <label for="customerEmail" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="customerEmail">
                    </div>
                    <div class="mb-3">
                        <label for="customerAddress" class="form-label">Address</label>
                        <textarea class="form-control" id="customerAddress" rows="2"></textarea>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Save Customer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Customer Details Modal -->
<div class="modal fade" id="customerDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Customer Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Name</label>
                            <div id="customerDetailName"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Phone</label>
                            <div id="customerDetailPhone"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Email</label>
                            <div id="customerDetailEmail"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Address</label>
                            <div id="customerDetailAddress"></div>
                        </div>
                    </div>
                </div>

                <h6 class="border-bottom pb-2">Purchase History</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="customerPurchaseHistory">
                            <tr>
                                <td colspan="5" class="text-center">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // New customer form submission
        document.getElementById('newCustomerForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const customerData = {
                name: document.getElementById('customerName').value,
                phone: document.getElementById('customerPhone').value,
                email: document.getElementById('customerEmail').value,
                address: document.getElementById('customerAddress').value
            };

            // AJAX request to add customer
            fetch('add_customer.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(customerData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Customer added successfully');
                        window.location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while adding the customer.');
                });
        });

        // View customer details
        document.querySelectorAll('.view-customer').forEach(button => {
            button.addEventListener('click', function() {
                const customerId = this.getAttribute('data-id');

                // Clear previous content
                document.getElementById('customerDetailName').textContent = '';
                document.getElementById('customerDetailPhone').textContent = '';
                document.getElementById('customerDetailEmail').textContent = '';
                document.getElementById('customerDetailAddress').textContent = '';
                document.getElementById('customerPurchaseHistory').innerHTML = '<tr><td colspan="5" class="text-center">Loading...</td></tr>';

                // Fetch customer details
                fetch('get_customer.php?id=' + customerId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const customer = data.customer;
                            document.getElementById('customerDetailName').textContent = customer.name;
                            document.getElementById('customerDetailPhone').textContent = customer.phone;
                            document.getElementById('customerDetailEmail').textContent = customer.email || 'N/A';
                            document.getElementById('customerDetailAddress').textContent = customer.address || 'N/A';

                            // Create purchase history HTML
                            const purchaseHistory = document.getElementById('customerPurchaseHistory');
                            purchaseHistory.innerHTML = '';

                            if (data.purchases && data.purchases.length > 0) {
                                data.purchases.forEach(purchase => {
                                    const row = document.createElement('tr');

                                    // Format date
                                    const date = new Date(purchase.created_at);
                                    const formattedDate = date.toLocaleDateString() + ' ' + date.toLocaleTimeString();

                                    // Create status badge
                                    let statusBadge;
                                    if (purchase.payment_status === 'paid') {
                                        statusBadge = '<span class="badge bg-success">Paid</span>';
                                    } else if (purchase.payment_status === 'cancelled') {
                                        statusBadge = '<span class="badge bg-danger">Cancelled</span>';
                                    } else {
                                        statusBadge = '<span class="badge bg-warning text-dark">Pending</span>';
                                    }

                                    row.innerHTML = `
                                        <td>${purchase.invoice_id}</td>
                                        <td>${formattedDate}</td>
                                        <td>₵${parseFloat(purchase.total_amount).toFixed(2)}</td>
                                        <td>${statusBadge}</td>
                                        <td>
                                            <a href="invoice_details.php?id=${purchase.invoice_id}" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    `;

                                    purchaseHistory.appendChild(row);
                                });
                            } else {
                                purchaseHistory.innerHTML = '<tr><td colspan="5" class="text-center">No purchase history found</td></tr>';
                            }

                            // Show the modal
                            new bootstrap.Modal(document.getElementById('customerDetailsModal')).show();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while fetching customer details.');
                    });
            });
        });
    });
</script>

<?php
// Include footer
include_once '../include/footer.php';
?>