<?php
require_once '../config/database.php';
checkAuth();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? 'save';

if ($action === 'save') {
    $hold_ref = 'HOLD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

    $stmt = $pdo->prepare("
        INSERT INTO held_invoices (hold_reference, customer_name, customer_phone, customer_type, 
                                   cart_data, subtotal, discount_amount, total_amount, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $hold_ref,
        $data['customer_name'] ?? null,
        $data['customer_phone'] ?? null,
        $data['customer_type'],
        json_encode($data['cart']),
        $data['subtotal'],
        $data['discount'],
        $data['total'],
        $_SESSION['user_id']
    ]);

    echo json_encode(['success' => true, 'hold_ref' => $hold_ref, 'message' => 'Invoice held!']);

} elseif ($action === 'list') {
    $stmt = $pdo->prepare("
        SELECT h.*, u.full_name as created_by_name 
        FROM held_invoices h
        JOIN users u ON h.created_by = u.id
        ORDER BY h.created_at DESC
    ");
    $stmt->execute();
    $held = $stmt->fetchAll();

    echo json_encode(['success' => true, 'held_invoices' => $held]);

} elseif ($action === 'get') {
    $hold_id = $data['hold_id'] ?? 0;
    $stmt = $pdo->prepare("SELECT * FROM held_invoices WHERE id = ?");
    $stmt->execute([$hold_id]);
    $held = $stmt->fetch();

    if ($held) {
        $held['cart_data'] = json_decode($held['cart_data'], true);
        echo json_encode(['success' => true, 'invoice' => $held]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Not found']);
    }

} elseif ($action === 'delete') {
    // Delete held invoice
    $hold_id = $data['hold_id'] ?? 0;
    $stmt = $pdo->prepare("DELETE FROM held_invoices WHERE id = ?");
    $stmt->execute([$hold_id]);
    echo json_encode(['success' => true]);
}
?>