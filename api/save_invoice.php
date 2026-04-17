<?php
error_reporting(0);
require_once '../config/database.php';
require_once '../config/functions.php';
header('Content-Type: application/json');

checkAuth();

$data = json_decode(file_get_contents('php://input'), true);

// Check if data is valid
if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Generate invoice number
    $stmt = $pdo->query("SELECT generate_invoice_no() as invoice_no");
    $invoice_no = $stmt->fetch()['invoice_no'];
    
    // Get values with defaults
    $customer_name = $data['customer_name'] ?? '';
    $customer_phone = $data['customer_phone'] ?? '';
    $customer_type = $data['customer_type'] ?? 'retail';
    $subtotal = floatval($data['subtotal'] ?? 0);
    $discount = floatval($data['discount'] ?? 0);
    $total = floatval($data['total'] ?? 0);
    $payment_method = $data['payment_method'] ?? 'cash';
    $items = $data['items'] ?? [];
    
    // Insert invoice header
    $stmt = $pdo->prepare("
        INSERT INTO invoices (invoice_no, customer_name, customer_phone, customer_type, 
                            subtotal, discount_amount, total_amount, payment_method, 
                            payment_status, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?)
    ");
    $stmt->execute([
        $invoice_no,
        $customer_name,
        $customer_phone,
        $customer_type,
        $subtotal,
        $discount,
        $total,
        $payment_method,
        $_SESSION['user_id']
    ]);
    
    $invoice_id = $pdo->lastInsertId();
    
    // Insert invoice items
    // Insert invoice items
foreach ($items as $item) {
    $product_id = intval($item['product_id'] ?? 0);
    $product_name = $item['product_name'] ?? '';
    $quantity = floatval($item['actual_quantity'] ?? $item['quantity'] ?? 0);
    $unit_price = floatval($item['unit_price'] ?? 0);
    $total_price = floatval($item['total_price'] ?? 0);
    $tier_info = $item['tier_info'] ?? '';
    $display_unit = $item['display_unit'] ?? null;
    $display_quantity = $item['display_quantity'] ?? null;
    
    $stmt = $pdo->prepare("
        INSERT INTO invoice_items (invoice_id, product_id, product_name, 
                                 quantity, unit_price, total_price, tier_info,
                                 display_unit, display_quantity)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $invoice_id,
        $product_id,
        $product_name,
        $quantity,
        $unit_price,
        $total_price,
        $tier_info,
        $display_unit,
        $display_quantity
    ]);
    
    // Update stock
    $stmt = $pdo->prepare("UPDATE products SET current_stock = current_stock - ? WHERE id = ?");
    $stmt->execute([$quantity, $product_id]);
}
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'invoice_no' => $invoice_no,
        'invoice_id' => $invoice_id
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>