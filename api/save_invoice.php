<?php
require_once '../config/database.php';
require_once '../config/functions.php';
header('Content-Type: application/json');

checkAuth();

$data = json_decode(file_get_contents('php://input'), true);

try {
    $pdo->beginTransaction();
    
    // Generate invoice number
    $stmt = $pdo->query("SELECT generate_invoice_no() as invoice_no");
    $invoice_no = $stmt->fetch()['invoice_no'];
    
    // Insert invoice header
    $stmt = $pdo->prepare("
        INSERT INTO invoices (invoice_no, customer_name, customer_phone, customer_type, 
                            subtotal, discount_amount, total_amount, payment_method, 
                            payment_status, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?)
    ");
    $stmt->execute([
        $invoice_no,
        $data['customer_name'],
        $data['customer_phone'],
        $data['customer_type'],
        $data['subtotal'],
        $data['discount'],
        $data['total'],
        $data['payment_method'],
        $_SESSION['user_id']
    ]);
    
    $invoice_id = $pdo->lastInsertId();
    
    // Insert invoice items
    foreach ($data['items'] as $item) {
        $stmt = $pdo->prepare("
            INSERT INTO invoice_items (invoice_id, product_id, product_name, 
                                     quantity, unit_price, total_price, tier_info)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $invoice_id,
            $item['product_id'],
            $item['product_name'],
            $item['quantity'],
            $item['unit_price'],
            $item['total_price'],
            $item['tier_info']
        ]);
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