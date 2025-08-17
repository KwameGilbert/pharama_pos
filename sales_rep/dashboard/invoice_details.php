<?php
// Include header
include_once '../include/header.php';

// Check if invoice ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='alert alert-danger'>Invalid invoice ID</div>";
    include_once '../include/footer.php';
    exit;
}

$invoice_id = (int)$_GET['id'];

// Get invoice details
$invoice_query = "SELECT i.*, c.name as customer_name, c.phone as customer_phone, c.email as customer_email,
                 u.name as sales_rep_name
                 FROM invoices i
                 LEFT JOIN customers c ON i.customer_id = c.customer_id
                 LEFT JOIN users u ON i.user_id = u.user_id
                 WHERE i.invoice_id = '$invoice_id'";

$invoice_result = mysqli_query($conn, $invoice_query);

if (mysqli_num_rows($invoice_result) == 0) {
    echo "<div class='alert alert-danger'>Invoice not found</div>";
    include_once '../include/footer.php';
    exit;
}

$invoice = mysqli_fetch_assoc($invoice_result);

// Get invoice items
$items_query = "SELECT ii.*, p.name as product_name, p.batch_no
                FROM invoice_items ii
                LEFT JOIN products p ON ii.product_id = p.product_id
                WHERE ii.invoice_id = '$invoice_id'";

$items_result = mysqli_query($conn, $items_query);

// Get payment details
$payment_query = "SELECT * FROM payments WHERE invoice_id = '$invoice_id'";
$payment_result = mysqli_query($conn, $payment_query);
$payment = mysqli_fetch_assoc($payment_result);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Invoice #<?php echo $invoice_id; ?></h2>
    <div>
        <a href="invoices.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to Invoices
        </a>
        <a href="print_invoice.php?id=<?php echo $invoice_id; ?>" class="btn btn-primary ms-2">
            <i class="fas fa-print"></i> Print Receipt
        </a>
        <?php if ($invoice['payment_status'] != 'cancelled'): ?>
            <button type="button" class="btn btn-danger ms-2" data-bs-toggle="modal" data-bs-target="#cancelModal">
                <i class="fas fa-times-circle"></i> Cancel Invoice
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <!-- Invoice Summary -->
    <div class="col-md-4">
        <div class="dashboard-card bg-white mb-4">
            <h5 class="mb-3">Invoice Summary</h5>
            <table class="table table-borderless">
                <tr>
                    <th>Date:</th>
                    <td><?php echo date('M d, Y h:i A', strtotime($invoice['created_at'])); ?></td>
                </tr>
                <tr>
                    <th>Status:</th>
                    <td>
                        <?php if ($invoice['payment_status'] == 'paid'): ?>
                            <span class="badge bg-success">Paid</span>
                        <?php elseif ($invoice['payment_status'] == 'cancelled'): ?>
                            <span class="badge bg-danger">Cancelled</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Pending</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Sales Rep:</th>
                    <td><?php echo htmlspecialchars($invoice['sales_rep_name']); ?></td>
                </tr>
                <tr>
                    <th>Total Amount:</th>
                    <td>₵<?php echo number_format($invoice['total_amount'], 2); ?></td>
                </tr>
            </table>
        </div>

        <!-- Payment Information -->
        <?php if ($payment): ?>
            <div class="dashboard-card bg-white mb-4">
                <h5 class="mb-3">Payment Information</h5>
                <table class="table table-borderless">
                    <tr>
                        <th>Payment Method:</th>
                        <td>
                            <?php
                            switch ($payment['payment_method']) {
                                case 'cash':
                                    echo '<i class="fas fa-money-bill text-success me-1"></i> Cash';
                                    break;
                                case 'mobile_money':
                                    echo '<i class="fas fa-mobile-alt text-primary me-1"></i> Mobile Money';
                                    break;
                                case 'paystack':
                                    echo '<i class="fas fa-credit-card text-info me-1"></i> Paystack';
                                    break;
                                default:
                                    echo $payment['payment_method'];
                            }
                            ?>
                        </td>
                    </tr>
                    <?php if (!empty($payment['transaction_ref'])): ?>
                        <tr>
                            <th>Transaction Reference:</th>
                            <td><?php echo htmlspecialchars($payment['transaction_ref']); ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Payment Date:</th>
                        <td><?php echo date('M d, Y h:i A', strtotime($payment['paid_at'])); ?></td>
                    </tr>
                </table>
            </div>
        <?php endif; ?>

        <!-- Customer Information -->
        <div class="dashboard-card bg-white">
            <h5 class="mb-3">Customer Information</h5>
            <?php if ($invoice['customer_id']): ?>
                <table class="table table-borderless">
                    <tr>
                        <th>Name:</th>
                        <td><?php echo htmlspecialchars($invoice['customer_name']); ?></td>
                    </tr>
                    <?php if (!empty($invoice['customer_phone'])): ?>
                        <tr>
                            <th>Phone:</th>
                            <td><?php echo htmlspecialchars($invoice['customer_phone']); ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if (!empty($invoice['customer_email'])): ?>
                        <tr>
                            <th>Email:</th>
                            <td><?php echo htmlspecialchars($invoice['customer_email']); ?></td>
                        </tr>
                    <?php endif; ?>
                </table>
            <?php else: ?>
                <p class="text-muted">Walk-in Customer</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Invoice Items -->
    <div class="col-md-8">
        <div class="dashboard-card bg-white">
            <h5 class="mb-3">Invoice Items</h5>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th>Batch</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $counter = 1;
                        $total = 0;
                        while ($item = mysqli_fetch_assoc($items_result)):
                            $total += $item['subtotal'];
                        ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($item['batch_no']); ?></td>
                                <td>₵<?php echo number_format($item['price'], 2); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td class="text-end">₵<?php echo number_format($item['subtotal'], 2); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="5" class="text-end">Total:</th>
                            <th class="text-end">₵<?php echo number_format($total, 2); ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Invoice Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Cancel Invoice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel this invoice? This action cannot be undone.</p>
                <p><strong>Note:</strong> This action requires manager approval.</p>

                <form id="cancelForm">
                    <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">
                    <div class="mb-3">
                        <label for="cancelReason" class="form-label">Reason for Cancellation</label>
                        <textarea class="form-control" id="cancelReason" name="reason" rows="3" required></textarea>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-danger">Request Cancellation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Cancel invoice form submission
        document.getElementById('cancelForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = {
                invoice_id: <?php echo $invoice_id; ?>,
                reason: document.getElementById('cancelReason').value
            };

            fetch('cancel_invoice.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Cancellation request submitted for manager approval.');
                        window.location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while submitting the cancellation request.');
                });
        });
    });
</script>

<?php
// Include footer
include_once '../include/footer.php';
?>