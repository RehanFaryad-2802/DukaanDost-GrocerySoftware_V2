<?php
require_once '../config/database.php';
checkAuth();

$invoice_id = $_GET['id'] ?? 0;

// Get invoice details
$stmt = $pdo->prepare("
    SELECT i.*, u.full_name as created_by_name
    FROM invoices i
    JOIN users u ON i.created_by = u.id
    WHERE i.id = ?
");
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    die("Invoice not found");
}

// Get invoice items
$stmt = $pdo->prepare("
    SELECT * FROM invoice_items WHERE invoice_id = ?
");
$stmt->execute([$invoice_id]);
$items = $stmt->fetchAll();

// Get store settings
$stmt = $pdo->query("SELECT * FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Generate HTML receipt for printing
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Invoice #<?php echo $invoice['invoice_no']; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400..700&display=swap"
        rel="stylesheet">
    <style>
        @page {
            size: 72mm 297mm;
            margin: 0;
        }

        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            width: 72mm;
            margin: 0;
            padding: 5px;
            box-sizing: border-box;
            font-family: 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', serif;
            font-weight: 900;
            line-height: 1.4;
        }

        .header {
            text-align: center;
            margin-bottom: 5px;
        }

        .header h2 {
            margin: 0;
            font-size: 16px;
            font-weight: bold;
        }

        .header p {
            margin: 2px 0;
            font-size: 11px;
        }

        .divider {
            border-top: 1px dashed #000;
            margin: 8px 0;
        }

        .divider-double {
            border-top: 2px solid #000;
            margin: 8px 0;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 3px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 5px 0;
        }

        th {
            text-align: left;
            border-bottom: 1px solid #000;
            padding: 3px 0;
            font-size: 11px;
            text-align: right;
            column-gap: 10px;
        }
        
        td {
            column-gap: 10px;
            padding: 3px 0;
            vertical-align: top;
            font-size: 11px;
            font-weight: 900;
            text-align: right;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .bold {
            font-weight: bold;
        }
        .text-center{
            text-align: center;
        }

        .total-row {
            font-size: 14px;
            font-weight: bold;
        }

        .footer {
            text-align: center;
            margin-top: 10px;
            font-size: 11px;
        }

        .item-name {
            font-family: "Noto Nastaliq Urdu", serif;
            font-optical-sizing: auto;
            font-weight: 900;
            word-wrap: break-word;
        }

        th:nth-child(4),
        td:nth-child(4) {
            width: 50%;
        }
        

        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
    <script>
        // Auto print when page loads
        window.onload = function () {
            window.print();

            // Close window after print dialog closes (or after timeout)
            setTimeout(function () {
                window.close();
            }, 1000);
        };

        // If window doesn't close, provide manual close option
        window.onafterprint = function () {
            setTimeout(function () {
                window.close();
            }, 500);
        };
    </script>
</head>

<body onload="window.print();">
    <div class="header">
        <h2><?php echo strtoupper($settings['store_name'] ?? 'GROCERY STORE'); ?></h2>
        <p><?php echo $settings['store_address'] ?? ''; ?></p>
        <p>Jandiala Sher Khan</p>
        <p>Phone-1: 0309-9153780</p>
        <p>Phone-2: 0303-6897661</p>
        <p>Phone-3: 0307-6264034</p>
        <?php if (!empty($settings['store_gst'])): ?>
            <p>GST: <?php echo $settings['store_gst']; ?></p>
        <?php endif; ?>
    </div>

    <div class="divider-double"></div>

    <div class="info-row">
        <span>Invoice: <?php echo $invoice['invoice_no']; ?></span>
    </div>
    <div class="info-row">
        <span>Date: <?php echo date('d-m-Y h:i A'); ?></span>
    </div>

    <div class="info-row">
    </div>


    <div class="divider"></div>

    <table>
        <thead>
            <tr>
                <th class="text-right">Amount</th>
                <th class="text-right">Rate</th>
                <th class="text-right">Qty</th>
                <th class="text-right item-col">Item</th>
            </tr>
        </thead>

        <tbody>
            <?php
            $total_items = 0;
            $item_number = 1;
            foreach ($items as $item):
                $total_items += $item['quantity'];

                // Get product unit
                $stmt = $pdo->prepare("SELECT unit FROM products WHERE id = ?");
                $stmt->execute([$item['product_id']]);
                $product = $stmt->fetch();
                $unit = $product ? $product['unit'] : 'piece';
                ?>
                <tr>
                    <td class="text-center"><?php echo number_format($item['total_price'], 0); ?></td>
                    <td class="text-center"><?php echo number_format($item['unit_price'], 0); ?></td>
                    <td class="" dir="rtl"><?php echo number_format($item['quantity'], 0) . ' ' . $unit; ?></td>
                    <td dir="rtl" class="item-name item-col">
                        #<?php echo $item_number++ . ' ' ;?> <?php echo htmlspecialchars($item['product_name']); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>

    </table>

    <div class="divider"></div>

    <div class="info-row">
        <span>Total Items: <?php echo count($items); ?></span>
    </div>


    <?php if ($invoice['discount_amount'] > 0): ?>
        <div class="info-row">
            <span>Discount:</span>
            <span>Rs. <?php echo number_format($invoice['discount_amount'], 2); ?></span>
        </div>
    <?php endif; ?>

    <div class="divider"></div>

    <div class="info-row total-row">
        <span>TOTAL:</span>
        <span>Rs. <?php echo number_format($invoice['total_amount'], 0); ?></span>
    </div>

    <div class="divider"></div>

    <div class="footer">
        <p><?php echo $settings['receipt_header'] ?? 'Thank you for shopping :)'; ?></p>
        <p><?php echo date('d-m-Y h:i A', strtotime('-30 minutes')); ?></p>
    </div>

    <div class="text-center" style="margin-top: 5px;">
        <button onclick="window.print();" style="display: none;">Print</button>
    </div>
</body>

</html>