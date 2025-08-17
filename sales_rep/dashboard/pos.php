<?php
// Include header
include_once '../include/header.php';
?>

<!-- POS System -->
<h2 class="mb-4">Point of Sale</h2>

<div class="row">
    <!-- Left Section: Product Search and Cart -->
    <div class="col-lg-8">
        <div class="dashboard-card bg-white mb-3">
            <!-- Product Search -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="input-group mb-3">
                        <input type="text" id="searchProduct" class="form-control form-control-lg"
                            placeholder="Scan barcode or search product name..." autofocus>
                        <button class="btn btn-primary" type="button" id="searchBtn">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                    <div id="searchResults" class="mt-2" style="max-height: 200px; overflow-y: auto;"></div>
                </div>
            </div>

            <!-- Cart Table -->
            <div class="table-responsive">
                <table class="table table-hover" id="cartTable">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Price</th>
                            <th width="150">Quantity</th>
                            <th>Subtotal</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="cartItems">
                        <tr id="emptyCart">
                            <td colspan="5" class="text-center py-3">Cart is empty. Search products to add.</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-end fw-bold">Total:</td>
                            <td colspan="2" class="fw-bold" id="totalAmount">0.00</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Cart Actions -->
            <div class="d-flex justify-content-between mt-3">
                <button class="btn btn-danger" id="clearCart">
                    <i class="fas fa-trash"></i> Clear Cart
                </button>
                <button class="btn btn-success" id="checkoutBtn" disabled>
                    <i class="fas fa-cash-register"></i> Proceed to Checkout
                </button>
            </div>
        </div>
    </div>

    <!-- Right Section: Customer and Checkout -->
    <div class="col-lg-4">
        <!-- Customer Selection -->
        <div class="dashboard-card bg-white mb-3">
            <h5 class="mb-3"><i class="fas fa-user me-2"></i> Customer</h5>

            <div class="input-group mb-3">
                <select id="customerSelect" class="form-select">
                    <option value="0">Walk-in Customer</option>
                    <?php
                    $customer_query = "SELECT * FROM customers ORDER BY name";
                    $customer_result = mysqli_query($conn, $customer_query);
                    while ($customer = mysqli_fetch_assoc($customer_result)) {
                        echo "<option value='" . $customer['customer_id'] . "'>" .
                            htmlspecialchars($customer['name']) . " - " .
                            htmlspecialchars($customer['phone']) . "</option>";
                    }
                    ?>
                </select>
                <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#newCustomerModal">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        </div>

        <!-- Sale Information (will be shown after checkout button is clicked) -->
        <div class="dashboard-card bg-white mb-3" id="checkoutPanel" style="display: none;">
            <h5 class="mb-3"><i class="fas fa-file-invoice-dollar me-2"></i> Checkout</h5>

            <!-- Discount Option -->
            <div class="mb-3">
                <label for="discountInput" class="form-label">Discount (%)</label>
                <input type="number" class="form-control" id="discountInput" min="0" max="100" value="0">
            </div>

            <!-- Payment Method -->
            <div class="mb-3">
                <label for="paymentMethod" class="form-label">Payment Method</label>
                <select id="paymentMethod" class="form-select">
                    <option value="cash">Cash</option>
                    <option value="mobile_money">Mobile Money</option>
                    <option value="paystack">Paystack</option>
                </select>
            </div>

            <!-- Transaction Reference (for mobile money/paystack) -->
            <div class="mb-3" id="transactionRefDiv" style="display: none;">
                <label for="transactionRef" class="form-label">Transaction Reference</label>
                <input type="text" class="form-control" id="transactionRef">
            </div>

            <!-- Amount Tendered (for cash) -->
            <div class="mb-3" id="amountTenderedDiv">
                <label for="amountTendered" class="form-label">Amount Tendered</label>
                <input type="number" class="form-control" id="amountTendered" step="0.01">
                <div id="changeAmount" class="mt-2"></div>
            </div>

            <!-- Submit Button -->
            <div class="d-grid gap-2">
                <button class="btn btn-lg btn-primary" id="completePayment">
                    <i class="fas fa-check-circle"></i> Complete Sale
                </button>
            </div>
        </div>
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

<!-- Sale Success Modal -->
<div class="modal fade" id="saleSuccessModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Sale Completed</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <i class="fas fa-check-circle text-success display-1 mb-4"></i>
                <h4>Sale has been completed successfully!</h4>
                <p>Invoice #<span id="successInvoiceId"></span></p>
                <div class="d-flex justify-content-center mt-4">
                    <a href="#" class="btn btn-primary mx-2" id="printInvoiceBtn">
                        <i class="fas fa-print"></i> Print Receipt
                    </a>
                    <a href="index.php" class="btn btn-secondary mx-2">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a href="pos.php" class="btn btn-success mx-2">
                        <i class="fas fa-plus-circle"></i> New Sale
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for POS functionality -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Variables
        let cart = [];
        let totalAmount = 0;

        // Elements
        const searchInput = document.getElementById('searchProduct');
        const searchBtn = document.getElementById('searchBtn');
        const searchResults = document.getElementById('searchResults');
        const cartItems = document.getElementById('cartItems');
        const emptyCart = document.getElementById('emptyCart');
        const totalAmountEl = document.getElementById('totalAmount');
        const clearCartBtn = document.getElementById('clearCart');
        const checkoutBtn = document.getElementById('checkoutBtn');
        const checkoutPanel = document.getElementById('checkoutPanel');
        const paymentMethod = document.getElementById('paymentMethod');
        const transactionRefDiv = document.getElementById('transactionRefDiv');
        const amountTenderedDiv = document.getElementById('amountTenderedDiv');
        const amountTendered = document.getElementById('amountTendered');
        const changeAmount = document.getElementById('changeAmount');
        const completePayment = document.getElementById('completePayment');
        const discountInput = document.getElementById('discountInput');

        // Search Products
        searchBtn.addEventListener('click', searchProducts);
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchProducts();
            }
        });

        function searchProducts() {
            const searchTerm = searchInput.value.trim();
            if (searchTerm === '') return;

            searchResults.innerHTML = '<div class="text-center my-2"><i class="fas fa-spinner fa-spin"></i> Searching...</div>';

            // AJAX request to search products
            fetch(`product_search.php?term=${encodeURIComponent(searchTerm)}`)
                .then(response => response.json())
                .then(data => {
                    searchResults.innerHTML = '';

                    if (data.length === 0) {
                        searchResults.innerHTML = '<p class="text-center">No products found</p>';
                        return;
                    }

                    // Create result list
                    const list = document.createElement('ul');
                    list.className = 'list-group';

                    data.forEach(product => {
                        const item = document.createElement('li');
                        item.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';

                        // Check if product is out of stock
                        if (product.stock_qty <= 0) {
                            item.classList.add('text-danger');
                        }

                        item.innerHTML = `
                            <div>
                                <strong>${product.name}</strong>
                                <small class="d-block text-muted">Batch: ${product.batch_no}</small>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-primary rounded-pill me-2">₵${parseFloat(product.selling_price).toFixed(2)}</span>
                                <span class="badge ${product.stock_qty > 0 ? 'bg-success' : 'bg-danger'} rounded-pill">
                                    Stock: ${product.stock_qty}
                                </span>
                            </div>
                        `;

                        // Add to cart when clicked
                        if (product.stock_qty > 0) {
                            item.addEventListener('click', function() {
                                addToCart(product);
                                searchResults.innerHTML = '';
                                searchInput.value = '';
                                searchInput.focus();
                            });
                        }

                        list.appendChild(item);
                    });

                    searchResults.appendChild(list);
                })
                .catch(error => {
                    console.error('Error:', error);
                    searchResults.innerHTML = '<p class="text-center text-danger">Error searching products</p>';
                });
        }

        // Add product to cart
        function addToCart(product) {
            // Check if product already in cart
            const existingItem = cart.find(item => item.product_id === product.product_id);

            if (existingItem) {
                // Increment quantity if not exceeding stock
                if (existingItem.quantity < product.stock_qty) {
                    existingItem.quantity += 1;
                    existingItem.subtotal = existingItem.quantity * existingItem.price;
                } else {
                    alert('Cannot add more units. Maximum stock reached.');
                    return;
                }
            } else {
                // Add new item
                cart.push({
                    product_id: product.product_id,
                    name: product.name,
                    price: parseFloat(product.selling_price),
                    quantity: 1,
                    max_qty: product.stock_qty,
                    subtotal: parseFloat(product.selling_price)
                });
            }

            // Update cart display
            updateCartDisplay();
        }

        // Update cart display
        function updateCartDisplay() {
            if (cart.length === 0) {
                emptyCart.style.display = '';
                checkoutBtn.disabled = true;
                return;
            }

            emptyCart.style.display = 'none';
            checkoutBtn.disabled = false;

            // Clear cart items
            while (cartItems.firstChild && cartItems.firstChild !== emptyCart) {
                cartItems.removeChild(cartItems.firstChild);
            }

            // Recalculate total
            totalAmount = 0;

            // Add items to cart
            cart.forEach((item, index) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${item.name}</td>
                    <td>₵${item.price.toFixed(2)}</td>
                    <td>
                        <div class="input-group input-group-sm">
                            <button class="btn btn-outline-secondary qty-btn" data-action="decrease" data-index="${index}">-</button>
                            <input type="number" class="form-control text-center qty-input" value="${item.quantity}" min="1" max="${item.max_qty}" data-index="${index}">
                            <button class="btn btn-outline-secondary qty-btn" data-action="increase" data-index="${index}">+</button>
                        </div>
                    </td>
                    <td>₵${item.subtotal.toFixed(2)}</td>
                    <td>
                        <button class="btn btn-sm btn-danger remove-item" data-index="${index}">
                            <i class="fas fa-times"></i>
                        </button>
                    </td>
                `;

                cartItems.appendChild(tr);
                totalAmount += item.subtotal;
            });

            // Update total
            totalAmountEl.textContent = '₵' + totalAmount.toFixed(2);

            // Add event listeners to quantity buttons
            document.querySelectorAll('.qty-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const index = parseInt(this.getAttribute('data-index'));
                    const action = this.getAttribute('data-action');

                    if (action === 'increase' && cart[index].quantity < cart[index].max_qty) {
                        cart[index].quantity += 1;
                    } else if (action === 'decrease' && cart[index].quantity > 1) {
                        cart[index].quantity -= 1;
                    }

                    cart[index].subtotal = cart[index].quantity * cart[index].price;
                    updateCartDisplay();
                });
            });

            // Add event listeners to quantity inputs
            document.querySelectorAll('.qty-input').forEach(input => {
                input.addEventListener('change', function() {
                    const index = parseInt(this.getAttribute('data-index'));
                    let newQty = parseInt(this.value);

                    if (isNaN(newQty) || newQty < 1) {
                        newQty = 1;
                    } else if (newQty > cart[index].max_qty) {
                        newQty = cart[index].max_qty;
                        alert('Quantity cannot exceed available stock.');
                    }

                    cart[index].quantity = newQty;
                    cart[index].subtotal = cart[index].quantity * cart[index].price;
                    updateCartDisplay();
                });
            });

            // Add event listeners to remove buttons
            document.querySelectorAll('.remove-item').forEach(btn => {
                btn.addEventListener('click', function() {
                    const index = parseInt(this.getAttribute('data-index'));
                    cart.splice(index, 1);
                    updateCartDisplay();
                });
            });
        }

        // Clear cart
        clearCartBtn.addEventListener('click', function() {
            if (confirm('Are you sure you want to clear the cart?')) {
                cart = [];
                updateCartDisplay();
            }
        });

        // Show checkout panel
        checkoutBtn.addEventListener('click', function() {
            checkoutPanel.style.display = 'block';

            // Update amount tendered with total
            const finalTotal = calculateFinalTotal();
            amountTendered.value = finalTotal;
            updateChange();

            // Scroll to checkout panel
            checkoutPanel.scrollIntoView({
                behavior: 'smooth'
            });
        });

        // Handle payment method change
        paymentMethod.addEventListener('change', function() {
            const method = this.value;

            if (method === 'cash') {
                transactionRefDiv.style.display = 'none';
                amountTenderedDiv.style.display = 'block';
            } else {
                transactionRefDiv.style.display = 'block';
                amountTenderedDiv.style.display = 'none';
            }
        });

        // Calculate discount and final total
        function calculateFinalTotal() {
            const discount = parseFloat(discountInput.value) || 0;
            return totalAmount - (totalAmount * (discount / 100));
        }

        // Update change amount
        amountTendered.addEventListener('input', updateChange);
        discountInput.addEventListener('input', updateChange);

        function updateChange() {
            const tendered = parseFloat(amountTendered.value) || 0;
            const finalTotal = calculateFinalTotal();

            const change = tendered - finalTotal;

            if (change >= 0) {
                changeAmount.innerHTML = `<div class="alert alert-success mb-0">Change: ₵${change.toFixed(2)}</div>`;
            } else {
                changeAmount.innerHTML = `<div class="alert alert-danger mb-0">Insufficient amount: ₵${Math.abs(change).toFixed(2)} more needed</div>`;
            }
        }

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
                        // Add new customer to select dropdown
                        const option = document.createElement('option');
                        option.value = data.customer_id;
                        option.text = `${customerData.name} - ${customerData.phone}`;
                        option.selected = true;
                        document.getElementById('customerSelect').appendChild(option);

                        // Close modal and reset form
                        document.getElementById('newCustomerForm').reset();
                        bootstrap.Modal.getInstance(document.getElementById('newCustomerModal')).hide();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while adding the customer.');
                });
        });

        // Complete payment
        completePayment.addEventListener('click', function() {
            // Basic validation
            if (cart.length === 0) {
                alert('Cart is empty. Please add products before completing sale.');
                return;
            }

            const method = paymentMethod.value;
            if (method === 'cash') {
                const tendered = parseFloat(amountTendered.value) || 0;
                const finalTotal = calculateFinalTotal();

                if (tendered < finalTotal) {
                    alert('Insufficient amount tendered.');
                    return;
                }
            } else if (transactionRef.value.trim() === '') {
                alert('Please enter a transaction reference.');
                return;
            }

            // Prepare sale data
            const saleData = {
                customer_id: document.getElementById('customerSelect').value,
                products: cart.map(item => ({
                    product_id: item.product_id,
                    quantity: item.quantity,
                    price: item.price,
                    subtotal: item.subtotal
                })),
                payment: {
                    method: method,
                    transaction_ref: document.getElementById('transactionRef').value,
                    amount_tendered: parseFloat(amountTendered.value) || 0,
                    discount_percent: parseFloat(discountInput.value) || 0
                },
                total_amount: totalAmount,
                final_amount: calculateFinalTotal()
            };

            // Disable button to prevent multiple submissions
            completePayment.disabled = true;
            completePayment.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

            // AJAX request to process sale
            fetch('process_sale.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(saleData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success modal
                        document.getElementById('successInvoiceId').textContent = data.invoice_id;
                        document.getElementById('printInvoiceBtn').href = 'print_invoice.php?id=' + data.invoice_id;

                        // Reset cart
                        cart = [];
                        updateCartDisplay();

                        // Hide checkout panel
                        checkoutPanel.style.display = 'none';

                        // Show success modal
                        new bootstrap.Modal(document.getElementById('saleSuccessModal')).show();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while processing the sale.');
                })
                .finally(() => {
                    // Re-enable button
                    completePayment.disabled = false;
                    completePayment.innerHTML = '<i class="fas fa-check-circle"></i> Complete Sale';
                });
        });
    });
</script>

<?php
// Include footer
include_once '../include/footer.php';
?>