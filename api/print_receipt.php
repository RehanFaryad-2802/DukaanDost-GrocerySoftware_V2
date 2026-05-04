<?php
require_once '../config/database.php';
checkAuth();

$invoice_id = $_GET['id'] ?? 0;

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

$stmt = $pdo->prepare("
    SELECT * FROM invoice_items WHERE invoice_id = ?
");
$stmt->execute([$invoice_id]);
$items = $stmt->fetchAll();

$stmt = $pdo->query("SELECT * FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Invoice #<?php echo $invoice['invoice_no']; ?></title>
    <!-- <link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400..700&display=swap" rel="stylesheet"> -->
    <style>
        @page {
            size: 72mm 297mm;
            margin: 0;
        }

        :root {
            --size: 13px
        }

        /* arabic */
        @font-face {
            font-family: 'Noto Nastaliq Urdu';
            font-style: normal;
            font-weight: 400 700;
            font-display: swap;
            src: url(https://fonts.gstatic.com/s/notonastaliqurdu/v23/LhW4MUPbN-oZdNFcBy1-DJYsEoTq5pu3SvoMC9Y.woff2) format('woff2');
            unicode-range: U+0600-06FF, U+0750-077F, U+0870-088E, U+0890-0891, U+0897-08E1, U+08E3-08FF, U+200C-200E, U+2010-2011, U+204F, U+2E41, U+FB50-FDFF, U+FE70-FE74, U+FE76-FEFC, U+102E0-102FB, U+10E60-10E7E, U+10EC2-10EC4, U+10EFC-10EFF, U+1EE00-1EE03, U+1EE05-1EE1F, U+1EE21-1EE22, U+1EE24, U+1EE27, U+1EE29-1EE32, U+1EE34-1EE37, U+1EE39, U+1EE3B, U+1EE42, U+1EE47, U+1EE49, U+1EE4B, U+1EE4D-1EE4F, U+1EE51-1EE52, U+1EE54, U+1EE57, U+1EE59, U+1EE5B, U+1EE5D, U+1EE5F, U+1EE61-1EE62, U+1EE64, U+1EE67-1EE6A, U+1EE6C-1EE72, U+1EE74-1EE77, U+1EE79-1EE7C, U+1EE7E, U+1EE80-1EE89, U+1EE8B-1EE9B, U+1EEA1-1EEA3, U+1EEA5-1EEA9, U+1EEAB-1EEBB, U+1EEF0-1EEF1;
        }

        /* latin-ext */
        @font-face {
            font-family: 'Noto Nastaliq Urdu';
            font-style: normal;
            font-weight: 400 700;
            font-display: swap;
            src: url(https://fonts.gstatic.com/s/notonastaliqurdu/v23/LhW4MUPbN-oZdNFcBy1-DJYsEoTq5pu3QfoMC9Y.woff2) format('woff2');
            unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;
        }

        /* latin */
        @font-face {
            font-family: 'Noto Nastaliq Urdu';
            font-style: normal;
            font-weight: 400 700;
            font-display: swap;
            src: url(https://fonts.gstatic.com/s/notonastaliqurdu/v23/LhW4MUPbN-oZdNFcBy1-DJYsEoTq5pu3T_oM.woff2) format('woff2');
            unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2142, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
        }

        body {
            font-family: 'Courier New', monospace;
            font-size: 11px;
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
            font-size: var(--size);
            font-weight: bold;
        }

        .header p {
            margin: 2px 0;
            font-size: var(--size);
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
            line-height: 2.01;
        }

        th {
            text-align: left;
            border-bottom: 1px solid #000;
            padding: 3px 0;
            font-size: var(--size);
            text-align: right;
            column-gap: 10px;
        }

        td {
            column-gap: 10px;
            padding: 3px 0;
            vertical-align: top;
            font-size: var(--size);
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

        .text-center {
            text-align: center;
        }

        .total-row {
            font-size: var(--size);
            font-weight: bold;
        }

        .footer {
            text-align: center;
            margin-top: 10px;
            font-size: var(--size);
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
    <?php if (!empty($invoice['customer_name'])): ?>
        <h1 class="text-center" style="font-size: 35px; margin-bottom: 10px;">
            <?php echo htmlspecialchars($invoice['customer_name']); ?>
        </h1>
    <?php endif; ?>
    <?php if (!empty($invoice['customer_phone'])): ?>
        <div class="text-center">
            <span><?php echo htmlspecialchars($invoice['customer_phone']); ?></span>
        </div>
    <?php endif; ?>
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
        <span>Date: <?php echo date('d/m/Y - h:i A', strtotime($invoice['created_at'])); ?></span>
    </div>

    <div class="info-row">
    </div>

    <div class="info-row">
        <span>Total Items:
            <?php echo count($items); ?>
        </span>
    </div>
    <div class="divider"></div>

    <table>
        <thead>
            <tr>
                <th class="text-center">Amount</th>
                <th class="text-center">Rate</th>
                <th>Qty</th>
                <th class="text-right item-col">Item</th>
            </tr>
        </thead>

        <tbody>
            <?php
            $total_items = 0;
            $item_number = 1;
            foreach ($items as $item):
                $total_items += $item['quantity'];

                // First try to get display_unit from invoice_items
                $displayUnit = $item['display_unit'] ?? null;
                $displayQty = $item['display_quantity'] ?? null;

                // If not saved, fallback to product's base unit
                if (!$displayUnit) {
                    $stmt = $pdo->prepare("SELECT unit FROM products WHERE id = ?");
                    $stmt->execute([$item['product_id']]);
                    $product = $stmt->fetch();
                    $displayUnit = $product ? $product['unit'] : 'piece';
                }

                // Use display quantity if available, otherwise use actual quantity
                $qty = (float) ($displayQty ?? $item['quantity']);
                ?>
                <tr>
                    <td class="text-center"><?php echo number_format($item['total_price'], 0); ?></td>
                    <td class="text-center"><?php echo number_format($item['unit_price'], 0); ?></td>
                    <td class="" dir="rtl">
                        <?php
                        if (floor($qty) == $qty) {
                            echo $qty . ' ' . $displayUnit;
                        } else {
                            echo rtrim(rtrim((string) $qty, '0'), '.') . ' ' . $displayUnit;
                        }
                        ?>
                    </td>
                    <td dir="rtl" class="item-name item-col">
                        #<?php echo $item_number++ . ' '; ?>     <?php echo htmlspecialchars($item['product_name']); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>

    </table>

    <div class="divider"></div>

    <?php if ($invoice['discount_amount'] > 0): ?>
        <div class="info-row">
            <span>Discount:</span>
            <span>Rs. <?php echo number_format($invoice['discount_amount'], 2); ?></span>
        </div>
    <?php endif; ?>


    <div class="info-row total-row">
        <span>Rs. <?php echo number_format($invoice['total_amount'], 0); ?></span>
        <span dir="rtl">ٹوٹل:</span>
    </div>

    <div class="divider"></div>

    <div class="footer">
        <p>Printing Time: <?php echo date('d/m/Y - h:i A', strtotime('-30 minutes')); ?></p>
        <p><?php echo $settings['receipt_header'] ?? 'Thank you for shopping :)'; ?></p>
    </div>

    <div class="text-center" style="margin-top: 5px;">
        <button onclick="window.print();" style="display: none;">Print</button>
    </div>
</body>

</html>