<?php
// Set page title
$page_title = "Invoice Details";

// Include header
include_once '../includes/header.php';

// Check if invoice ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="alert alert-danger">Invalid invoice ID.</div>';
    include_once '../includes/footer.php';
    exit;
}

$invoice_id = $_GET['id'];

// Get invoice details

$query = "SELECT i.*, 
          u.name as sales_rep_name,
          c.name as customer_name, c.phone as customer_phone, c.email as customer_email
          FROM invoices i 
          LEFT JOIN users u ON i.user_id = u.user_id
          LEFT JOIN customers c ON i.customer_id = c.customer_id
          WHERE i.invoice_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="alert alert-danger">Invoice not found.</div>';
    include_once __DIR__ . '/../includes/footer.php';
    exit;
}

$invoice = $result->fetch_assoc();

// Get invoice items
$items_query = "SELECT ii.*, p.name, p.batch_no as code
                FROM invoice_items ii
                JOIN products p ON ii.product_id = p.product_id
                WHERE ii.invoice_id = ?";
$items_stmt = $conn->prepare($items_query);
$items_stmt->bind_param("i", $invoice_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();


// Invoice logs/history feature is disabled because invoice_logs table does not exist.
// $logs_result = false;
?>

<div class="row mb-4">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="index.php">Sales</a></li>
                <li class="breadcrumb-item active">Invoice Details</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-file-invoice me-2"></i> Invoice #<?php echo isset($invoice['invoice_id']) ? $invoice['invoice_id'] : '-'; ?></h5>
                <div>
                    <a href="print_invoice.php?id=<?php echo $invoice_id; ?>" target="_blank" class="btn btn-sm btn-secondary">
                        <i class="fas fa-print me-1"></i> Print
                    </a>
                    <?php if (($invoice['payment_status'] ?? '') == 'paid'): ?>
                        <button type="button" class="btn btn-sm btn-danger cancel-invoice" data-id="<?php echo $invoice_id; ?>">
                            <i class="fas fa-times me-1"></i> Cancel Invoice
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted">Invoice Information</h6>
                        <table class="table table-sm">
                            <tr>
                                <th width="35%">Invoice Number</th>
                                <td><?php echo isset($invoice['invoice_id']) ? $invoice['invoice_id'] : '-'; ?></td>
                            </tr>
                            <tr>
                                <th>Date</th>
                                <td><?php echo isset($invoice['created_at']) ? date('F d, Y', strtotime($invoice['created_at'])) : '-'; ?></td>
                            </tr>
                            <tr>
                                <th>Time</th>
                                <td><?php echo isset($invoice['created_at']) ? date('h:i A', strtotime($invoice['created_at'])) : '-'; ?></td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    <?php if (($invoice['payment_status'] ?? '') == 'paid'): ?>
                                        <span class="badge bg-success">Paid</span>
                                    <?php elseif (($invoice['payment_status'] ?? '') == 'cancelled'): ?>
                                        <span class="badge bg-danger">Cancelled</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Payment Method</th>
                                <td><?php echo isset($invoice['payment_method']) ? ucfirst($invoice['payment_method']) : '-'; ?></td>
                            </tr>
                            <tr>
                                <th>Sales Representative</th>
                                <td><?php echo htmlspecialchars($invoice['sales_rep_name'] ?? '-'); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted">Customer Information</h6>
                        <table class="table table-sm">
                            <tr>
                                <th width="35%">Name</th>
                                <td>
                                    <?php echo !empty($invoice['customer_name']) ?
                                        htmlspecialchars($invoice['customer_name']) :
                                        '<span class="text-muted">Walk-in customer</span>'; ?>
                                </td>
                            </tr>
                            <?php if (!empty($invoice['customer_phone'])): ?>
                                <tr>
                                    <th>Phone</th>
                                    <td><?php echo htmlspecialchars($invoice['customer_phone']); ?></td>
                                </tr>
                            <?php endif; ?>
                            <?php if (!empty($invoice['customer_email'])): ?>
                                <tr>
                                    <th>Email</th>
                                    <td><?php echo htmlspecialchars($invoice['customer_email']); ?></td>
                                </tr>
                            <?php endif; ?>
                            <?php if (!empty($invoice['notes'] ?? '')): ?>
                                <tr>
                                    <th>Notes</th>
                                    <td><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>

                <h6 class="text-muted mb-3">Items</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Product Code</th>
                                <th>Product Name</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-center">Quantity</th>
                                <th class="text-end">Discount</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $counter = 1;
                            $subtotal = 0;

                            while ($item = $items_result->fetch_assoc()) {
                                $unit_price = $item['price'] ?? 0;
                                $quantity = $item['quantity'] ?? 0;
                                $discount = $item['discount'] ?? 0;
                                $itemTotal = $unit_price * $quantity - $discount;
                                $subtotal += $itemTotal;
                            ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo htmlspecialchars($item['code'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($item['name'] ?? '-'); ?></td>
                                    <td class="text-end"><?php echo number_format($unit_price, 2); ?></td>
                                    <td class="text-center"><?php echo $quantity; ?></td>
                                    <td class="text-end"><?php echo number_format($discount, 2); ?></td>
                                    <td class="text-end"><?php echo number_format($itemTotal, 2); ?></td>
                                </tr>
                            <?php
                            }
                            ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5"></td>
                                <th class="text-end">Subtotal</th>
                                <td class="text-end"><?php echo number_format($subtotal, 2); ?></td>
                            </tr>
                            <tr>
                                <td colspan="5"></td>
                                <th class="text-end">Discount</th>
                                <td class="text-end"><?php echo number_format($invoice['discount'] ?? 0, 2); ?></td>
                            </tr>
                            <tr>
                                <td colspan="5"></td>
                                <th class="text-end">Tax</th>
                                <td class="text-end"><?php echo number_format($invoice['tax'] ?? 0, 2); ?></td>
                            </tr>
                            <tr>
                                <td colspan="5"></td>
                                <th class="text-end">Total</th>
                                <td class="text-end"><strong><?php echo number_format($invoice['total_amount'] ?? 0, 2); ?></strong></td>
                            </tr>
                            <?php if (($invoice['payment_method'] ?? '') == 'cash'): ?>
                                <tr>
                                    <td colspan="5"></td>
                                    <th class="text-end">Amount Paid</th>
                                    <td class="text-end"><?php echo number_format($invoice['amount_paid'] ?? 0, 2); ?></td>
                                </tr>
                                <tr>
                                    <td colspan="5"></td>
                                    <th class="text-end">Change</th>
                                    <td class="text-end"><?php echo number_format(($invoice['amount_paid'] ?? 0) - ($invoice['total_amount'] ?? 0), 2); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tfoot>
                    </table>
                </div>

                <!-- Invoice history/logs feature is disabled because invoice_logs table does not exist. -->

                <?php if (($invoice['payment_status'] ?? '') == 'cancelled'): ?>
                    <div class="mt-4 alert alert-danger">
                        <h6>Cancellation Information</h6>
                        <p><strong>Cancelled on:</strong> <?php echo date('F d, Y h:i A', strtotime($invoice['updated_at'])); ?></p>
                        <?php if (!empty($invoice['cancel_reason'])): ?>
                            <p><strong>Reason:</strong> <?php echo nl2br(htmlspecialchars($invoice['cancel_reason'])); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Invoice Modal -->
<div class="modal fade" id="cancelInvoiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Cancel Invoice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel this invoice? This action cannot be undone and will affect inventory.</p>
                <form id="cancelInvoiceForm" method="post" action="cancel_invoice.php">
                    <input type="hidden" name="invoice_id" id="cancelInvoiceId">
                    <input type="hidden" name="redirect" value="details">
                    <div class="mb-3">
                        <label for="cancelReason" class="form-label">Reason for Cancellation</label>
                        <textarea class="form-control" id="cancelReason" name="reason" rows="3" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" form="cancelInvoiceForm" class="btn btn-danger">Cancel Invoice</button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Handle cancel invoice button
        $('.cancel-invoice').click(function() {
            let invoiceId = $(this).data('id');
            $('#cancelInvoiceId').val(invoiceId);
            $('#cancelInvoiceModal').modal('show');
        });
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>