<?php
// Include database connection
require_once '../../config/database.php';

// Check if invoice ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo 'Invalid invoice ID.';
    exit;
}

$invoice_id = $_GET['id'];

// Get invoice details

$query = "SELECT i.*, 
          u.name as sales_rep_name,
          c.name as customer_name, c.phone as customer_phone, c.email as customer_email,
          p.name as pharmacy_name, p.address as pharmacy_address, p.phone as pharmacy_phone, 
          p.email as pharmacy_email, p.logo
          FROM invoices i 
          LEFT JOIN users u ON i.user_id = u.user_id
          LEFT JOIN customers c ON i.customer_id = c.customer_id
          LEFT JOIN pharmacy p ON p.pharmacy_id = 1
          WHERE i.invoice_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo 'Invoice not found.';
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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo isset($invoice['invoice_id']) ? $invoice['invoice_id'] : '-'; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .invoice-header {
            border-bottom: 2px solid #ddd;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }

        .invoice-title {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
            color: #333;
        }

        .invoice-details {
            margin-bottom: 30px;
        }

        .customer-details,
        .invoice-summary {
            margin-bottom: 20px;
        }

        .table-items th {
            background-color: #f8f9fa;
        }

        .total-row {
            font-weight: bold;
        }

        .invoice-footer {
            margin-top: 50px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
            font-size: 0.8em;
            color: #777;
            text-align: center;
        }

        .print-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
        }

        .status-cancelled {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 72px;
            color: rgba(220, 53, 69, 0.5);
            padding: 20px;
            border: 10px solid rgba(220, 53, 69, 0.5);
            border-radius: 10px;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 5px;
            pointer-events: none;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 80%;
            text-align: center;
        }

        @media print {
            .print-button {
                display: none !important;
            }

            .invoice-container {
                width: 100%;
                max-width: none;
                padding: 0;
                margin: 0;
            }
        }
    </style>
</head>

<body>
    <?php if (($invoice['payment_status'] ?? '') == 'cancelled'): ?>
        <div class="status-cancelled">Cancelled</div>
    <?php endif; ?>

    <div class="invoice-container position-relative">
        <div class="invoice-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="invoice-title"><?php echo htmlspecialchars($invoice['pharmacy_name']); ?></h1>
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($invoice['pharmacy_address'])); ?></p>
                    <p class="mb-0">Phone: <?php echo htmlspecialchars($invoice['pharmacy_phone']); ?></p>
                    <p class="mb-0">Email: <?php echo htmlspecialchars($invoice['pharmacy_email']); ?></p>
                    <?php if (!empty($invoice['pharmacy_reg'])): ?>
                        <p class="mb-0">Reg No: <?php echo htmlspecialchars($invoice['pharmacy_reg']); ?></p>
                    <?php endif; ?>
                </div>
                <div class="col-md-6 text-md-end">
                    <h2>INVOICE</h2>
                    <h3>#<?php echo isset($invoice['invoice_id']) ? $invoice['invoice_id'] : '-'; ?></h3>
                    <p class="mb-0"><strong>Date:</strong> <?php echo isset($invoice['created_at']) ? date('F d, Y', strtotime($invoice['created_at'])) : '-'; ?></p>
                    <p class="mb-0"><strong>Time:</strong> <?php echo isset($invoice['created_at']) ? date('h:i A', strtotime($invoice['created_at'])) : '-'; ?></p>
                    <?php if (($invoice['payment_status'] ?? '') == 'cancelled'): ?>
                        <p class="mb-0 text-danger"><strong>CANCELLED</strong></p>
                        <p class="mb-0 text-danger"><small>on <?php echo isset($invoice['updated_at']) ? date('M d, Y h:i A', strtotime($invoice['updated_at'])) : '-'; ?></small></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="invoice-details">
            <div class="row">
                <div class="col-md-6 customer-details">
                    <h5>Bill To:</h5>
                    <p class="mb-0">
                        <?php echo !empty($invoice['customer_name']) ?
                            htmlspecialchars($invoice['customer_name']) :
                            'Walk-in customer'; ?>
                    </p>
                    <?php if (!empty($invoice['customer_phone'])): ?>
                        <p class="mb-0">Phone: <?php echo htmlspecialchars($invoice['customer_phone']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($invoice['customer_email'])): ?>
                        <p class="mb-0">Email: <?php echo htmlspecialchars($invoice['customer_email']); ?></p>
                    <?php endif; ?>
                </div>
                <div class="col-md-6 text-md-end invoice-summary">
                    <p class="mb-1"><strong>Payment Method:</strong> <?php echo isset($invoice['payment_method']) ? ucfirst($invoice['payment_method']) : '-'; ?></p>
                    <p class="mb-1"><strong>Sales Rep:</strong> <?php echo htmlspecialchars($invoice['sales_rep_name'] ?? '-'); ?></p>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-items">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-center">Qty</th>
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
                            <td>
                                <?php echo htmlspecialchars($item['name']); ?><br>
                                <small class="text-muted"><?php echo htmlspecialchars($item['code']); ?></small>
                            </td>
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
                        <td colspan="4"></td>
                        <th class="text-end">Subtotal</th>
                        <td class="text-end"><?php echo number_format($subtotal, 2); ?></td>
                    </tr>
                    <tr>
                        <td colspan="4"></td>
                        <th class="text-end">Discount</th>
                        <td class="text-end"><?php echo number_format($invoice['discount'] ?? 0, 2); ?></td>
                    </tr>
                    <tr>
                        <td colspan="4"></td>
                        <th class="text-end">Tax</th>
                        <td class="text-end"><?php echo number_format($invoice['tax'] ?? 0, 2); ?></td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="4"></td>
                        <th class="text-end">Total</th>
                        <td class="text-end"><?php echo number_format($invoice['total_amount'] ?? 0, 2); ?></td>
                    </tr>
                    <?php if (($invoice['payment_method'] ?? '') == 'cash'): ?>
                        <tr>
                            <td colspan="4"></td>
                            <th class="text-end">Amount Paid</th>
                            <td class="text-end"><?php echo number_format($invoice['amount_paid'] ?? 0, 2); ?></td>
                        </tr>
                        <tr>
                            <td colspan="4"></td>
                            <th class="text-end">Change</th>
                            <td class="text-end"><?php echo number_format(($invoice['amount_paid'] ?? 0) - ($invoice['total_amount'] ?? 0), 2); ?></td>
                        </tr>
                    <?php endif; ?>
                </tfoot>
            </table>
        </div>

        <?php if (!empty($invoice['notes'] ?? '')): ?>
            <div class="mt-4">
                <h6>Notes:</h6>
                <p><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
            </div>
        <?php endif; ?>

        <?php if (($invoice['payment_status'] ?? '') == 'cancelled' && !empty($invoice['cancel_reason'])): ?>
            <div class="mt-4 alert alert-danger">
                <h6>Cancellation Reason:</h6>
                <p><?php echo nl2br(htmlspecialchars($invoice['cancel_reason'])); ?></p>
            </div>
        <?php endif; ?>

        <div class="invoice-footer">
            <p>Thank you for your business!</p>
            <p>This invoice was generated by Pharmacy POS System.</p>
            <p>Printed on: <?php echo date('F d, Y h:i A'); ?></p>
        </div>
    </div>

    <button class="btn btn-primary print-button" onclick="window.print();">
        Print Invoice
    </button>

    <script>
        // Auto print when page loads
        window.onload = function() {
            // Delay to ensure proper rendering
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>

</html>