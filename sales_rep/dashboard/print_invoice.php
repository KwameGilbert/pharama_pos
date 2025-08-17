<?php
// Include database connection
require_once '../../config/database.php';

// Start session for authentication
session_start();

// Check if user is logged in as sales rep
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'sales_rep') {
    header("Location: ../login/index.php");
    exit;
}

// Check if invoice ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div style='color: red; text-align: center; margin-top: 50px;'>Invalid invoice ID</div>";
    exit;
}

$invoice_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Get invoice details - only allow printing of own invoices
$invoice_query = "SELECT i.*, c.name as customer_name, c.phone as customer_phone, c.email as customer_email,
                 u.name as sales_rep_name, p.name as pharmacy_name, p.address as pharmacy_address,
                 p.phone as pharmacy_phone, p.email as pharmacy_email
                 FROM invoices i
                 LEFT JOIN customers c ON i.customer_id = c.customer_id
                 LEFT JOIN users u ON i.user_id = u.user_id
                 CROSS JOIN pharmacy p
                 WHERE i.invoice_id = '$invoice_id' AND i.user_id = '$user_id'";

$invoice_result = mysqli_query($conn, $invoice_query);

if (mysqli_num_rows($invoice_result) == 0) {
    echo "<div style='color: red; text-align: center; margin-top: 50px;'>Invoice not found or you don't have permission to view it</div>";
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

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo $invoice_id; ?> - Receipt</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            font-size: 12pt;
            color: #333;
        }

        .receipt {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .receipt-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .receipt-header h1 {
            margin: 5px 0;
            font-size: 18pt;
        }

        .receipt-header p {
            margin: 5px 0;
        }

        .invoice-details {
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
        }

        .invoice-details div {
            flex-basis: 48%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #f2f2f2;
        }

        .total-row {
            font-weight: bold;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10pt;
            color: #666;
        }

        .print-button {
            text-align: center;
            margin: 20px 0;
        }

        .print-button button {
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .print-button button:hover {
            background-color: #45a049;
        }

        .status {
            font-weight: bold;
            text-transform: uppercase;
        }

        .status.paid {
            color: green;
        }

        .status.cancelled {
            color: red;
        }

        .status.pending {
            color: orange;
        }

        @media print {
            .print-button {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="receipt">
        <div class="print-button">
            <button onclick="window.print()">Print Receipt</button>
            <button onclick="window.close()">Close</button>
        </div>

        <div class="receipt-header">
            <h1><?php echo htmlspecialchars($invoice['pharmacy_name']); ?></h1>
            <p><?php echo nl2br(htmlspecialchars($invoice['pharmacy_address'])); ?></p>
            <p>Tel: <?php echo htmlspecialchars($invoice['pharmacy_phone']); ?></p>
            <p>Email: <?php echo htmlspecialchars($invoice['pharmacy_email']); ?></p>
            <h2>RECEIPT</h2>
            <p class="status <?php echo $invoice['payment_status']; ?>"><?php echo strtoupper($invoice['payment_status']); ?></p>
        </div>

        <div class="invoice-details">
            <div>
                <p><strong>Invoice #:</strong> <?php echo $invoice_id; ?></p>
                <p><strong>Date:</strong> <?php echo date('M d, Y h:i A', strtotime($invoice['created_at'])); ?></p>
                <p><strong>Sales Rep:</strong> <?php echo htmlspecialchars($invoice['sales_rep_name']); ?></p>
            </div>
            <div>
                <p><strong>Customer:</strong> <?php echo $invoice['customer_name'] ?? 'Walk-in Customer'; ?></p>
                <?php if (!empty($invoice['customer_phone'])): ?>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($invoice['customer_phone']); ?></p>
                <?php endif; ?>
                <?php if (!empty($invoice['customer_email'])): ?>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($invoice['customer_email']); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item</th>
                    <th>Batch</th>
                    <th>Price</th>
                    <th>Qty</th>
                    <th style="text-align: right;">Amount</th>
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
                        <td style="text-align: right;">₵<?php echo number_format($item['subtotal'], 2); ?></td>
                    </tr>
                <?php endwhile; ?>
                <tr class="total-row">
                    <td colspan="5" style="text-align: right;">Total:</td>
                    <td style="text-align: right;">₵<?php echo number_format($total, 2); ?></td>
                </tr>
            </tbody>
        </table>

        <?php if ($payment): ?>
            <div style="margin-top: 20px;">
                <p><strong>Payment Method:</strong>
                    <?php
                    switch ($payment['payment_method']) {
                        case 'cash':
                            echo 'Cash';
                            break;
                        case 'mobile_money':
                            echo 'Mobile Money';
                            break;
                        case 'paystack':
                            echo 'Paystack';
                            break;
                        default:
                            echo $payment['payment_method'];
                    }
                    ?>
                </p>
                <?php if (!empty($payment['transaction_ref'])): ?>
                    <p><strong>Transaction Reference:</strong> <?php echo htmlspecialchars($payment['transaction_ref']); ?></p>
                <?php endif; ?>
                <p><strong>Payment Date:</strong> <?php echo date('M d, Y h:i A', strtotime($payment['paid_at'])); ?></p>
            </div>
        <?php endif; ?>

        <div class="footer">
            <p>Thank you for your business!</p>
            <p>For inquiries, please contact <?php echo htmlspecialchars($invoice['pharmacy_phone']); ?></p>
        </div>
    </div>
</body>

</html>