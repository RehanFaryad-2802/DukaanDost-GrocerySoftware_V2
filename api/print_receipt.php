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

// Get invoice items
$stmt = $pdo->prepare("
    SELECT * FROM invoice_items WHERE invoice_id = ?
");
$stmt->execute([$invoice_id]);
$items = $stmt->fetchAll();

// Generate ESC/POS commands for 72mm thermal printer
header('Content-Type: text/plain; charset=UTF-8');

// ESC/POS Commands
$output = "";
$output .= "\x1B\x40"; // Initialize printer
$output .= "\x1B\x61\x01"; // Center alignment
$output .= "\x1B\x21\x30"; // Double height text
$output .= strtoupper($settings['store_name']) . "\n";
$output .= "\x1B\x21\x00"; // Normal text
$output .= $settings['store_address'] . "\n";
$output .= "Phone: " . $settings['store_phone'] . "\n";
if ($settings['store_gst']) {
    $output .= "GST: " . $settings['store_gst'] . "\n";
}
$output .= str_repeat("=", 48) . "\n";
$output .= "\x1B\x61\x00"; // Left alignment

$output .= "Invoice: " . $invoice['invoice_no'] . "\n";
$output .= "Date: " . date('d-m-Y h:i A', strtotime($invoice['created_at'])) . "\n";
$output .= "Customer: " . ($invoice['customer_name'] ?: 'Walk-in') . "\n";
$output .= "Type: " . strtoupper($invoice['customer_type']) . "\n";
$output .= str_repeat("-", 48) . "\n";

// Column headers
$output .= sprintf("%-20s %6s %8s %10s\n", "Item", "Qty", "Rate", "Amount");
$output .= str_repeat("-", 48) . "\n";

foreach ($items as $item) {
    $name = substr($item['product_name'], 0, 18);
    $output .= sprintf("%-18s %6.2f %8.2f %10.2f\n", 
        $name, 
        $item['quantity'], 
        $item['unit_price'], 
        $item['total_price']
    );
    if ($item['tier_info']) {
        $output .= "   (" . $item['tier_info'] . ")\n";
    }
}

$output .= str_repeat("-", 48) . "\n";
$output .= sprintf("%-34s %10.2f\n", "Subtotal:", $invoice['subtotal']);
if ($invoice['discount_amount'] > 0) {
    $output .= sprintf("%-34s %10.2f\n", "Discount:", $invoice['discount_amount']);
}
$output .= "\x1B\x21\x30"; // Double height
$output .= sprintf("%-34s %10.2f\n", "TOTAL:", $invoice['total_amount']);
$output .= "\x1B\x21\x00"; // Normal text
$output .= str_repeat("=", 48) . "\n";

$output .= "\x1B\x61\x01"; // Center
$output .= $settings['receipt_header'] . "\n";
$output .= $settings['receipt_footer'] . "\n";
$output .= "Served by: " . $invoice['created_by_name'] . "\n";
$output .= "\n\n\n\n";
$output .= "\x1D\x56\x41\x03"; // Cut paper

echo $output;
?>