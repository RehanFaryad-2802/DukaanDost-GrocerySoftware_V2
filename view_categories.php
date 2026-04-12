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
while($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Format date with AM/PM
$invoice_date = strtotime($invoice['created_at']);
$date_formatted = date('d/m/y', $invoice_date);
$time_formatted = date('g:i', $invoice_date);
$am_pm = date('A', $invoice_date);

// Calculate totals
$total_items = count($items);
$total_qty = 0;
foreach ($items as $item) {
    $total_qty += $item['quantity'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Invoice #<?php echo $invoice['invoice_no']; ?></title>
    <style>
        @page {
            size: 72mm 297mm;
            margin: 0;
        }
        body {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            width: 72mm;
            margin: 0;
            padding: 8px 5px;
            box-sizing: border-box;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            margin-bottom: 8px;
        }
        .store-name {
            font-size: 14px;
            font-weight: bold;
            margin: 0 0 3px 0;
        }
        .store-address {
            font-size: 11px;
            margin: 2px 0;
        }
        .store-phone {
            font-size: 11px;
            margin: 2px 0;
        }
        .divider {
            border-top: 1px dashed #000;
            margin: 8px 0;
        }
        .divider-solid {
            border-top: 1px solid #000;
            margin: 8px 0;
        }
        .invoice-info {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
        }
        .invoice-number {
            font-weight: bold;
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
            font-weight: bold;
        }
        td {
            padding: 3px 0;
            vertical-align: top;
            font-size: 11px;
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
        .item-number {
            width: 25px;
        }
        .item-name {
            max-width: 160px;
            word-wrap: break-word;
        }
        .item-qty {
            width: 60px;
            text-align: right;
        }
        .item-rate {
            width: 55px;
            text-align: right;
        }
        .item-amount {
            width: 70px;
            text-align: right;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin: 3px 0;
        }
        .total-payment {
            font-size: 13px;
            font-weight: bold;
        }
        .footer {
            text-align: center;
            margin-top: 15px;
            font-size: 11px;
        }
        .thank-you {
            font-size: 12px;
            margin-top: 5px;
        }
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print {
                display: none;
            }
        }
    </style>
    <script>
        window.onload = function() {
            window.print();
            setTimeout(function() {
                window.close();
            }, 1000);
        };
        
        window.onafterprint = function() {
            setTimeout(function() {
                window.close();
            }, 500);
        };
    </script>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="store-name">Faryad General Store</div>
        <div class="store-address">VTI Bazar, Faryad Karyana Store</div>
        <div class="store-address">Jandiala Sher Khan</div>
        <div class="store-phone">Phone-1: 0309-9153780</div>
    </div>
    
    <!-- Divider -->
    <div class="divider">--------------------------</div>
    
    <!-- Invoice Info -->
    <div class="invoice-info">
        <span>Invoice number: <span class="invoice-number"><?php echo $invoice['invoice_no']; ?></span></span>
        <span>Date: <?php echo $date_formatted; ?> <?php echo $time_formatted; ?> <?php echo $am_pm; ?></span>
    </div>
    
    <!-- Items Table -->
    <table>
        <thead>
            <tr>
                <th class="item-number">#</th>
                <th class="item-name">items</th>
                <th class="item-qty">Qty</th>
                <th class="item-rate">rate</th>
                <th class="item-amount">amount</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $item_number = 1;
            foreach ($items as $item): 
                // Get product unit
                $stmt = $pdo->prepare("SELECT unit FROM products WHERE id = ?");
                $stmt->execute([$item['product_id']]);
                $product = $stmt->fetch();
                $unit = $product ? $product['unit'] : 'piece';
                
                // Format item name with quantity unit
                $qty_display = number_format($item['quantity'], 0) . ' ' . $unit;
            ?>
            <tr>
                <td class="item-number">#<?php echo $item_number; ?></td>
                <td class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></td>
                <td class="item-qty"><?php echo $qty_display; ?></td>
                <td class="item-rate"><?php echo number_format($item['unit_price'], 0); ?></td>
                <td class="item-amount"><?php echo number_format($item['total_price'], 0); ?></td>
            </tr>
            <?php 
                $item_number++;
            endforeach; 
            ?>
        </tbody>
    </table>
    
    <!-- Summary -->
    <div class="divider">--------------------------</div>
    
    <div class="summary-row">
        <span>total items: <?php echo $total_items; ?></span>
        <span>Total Qty : <?php echo number_format($total_qty, 0); ?></span>
    </div>
    
    <div class="divider-solid">-----------------------------</div>
    
    <div class="summary-row total-payment">
        <span>Total Payment</span>
        <span>Rs. <?php echo number_format($invoice['total_amount'], 0); ?></span>
    </div>
    
    <div class="divider">-----------------------------</div>
    
    <!-- Footer -->
    <div class="footer">
        <div class="thank-you">Thank you for shopping :)</div>
    </div>
    
    <!-- Manual Print Button (only shows on screen, not when printing) -->
    <div class="text-center no-print" style="margin-top: 15px;">
        <button onclick="window.print();" style="padding: 8px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">
            🖨️ Print Again
        </button>
        <button onclick="window.close();" style="padding: 8px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">
            Close
        </button>
    </div>
</body>
</html>