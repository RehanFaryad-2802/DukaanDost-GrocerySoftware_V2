<?php
require_once '../config/database.php';
checkAuth();
header('Content-Type: application/json');

try {
    $stmt = $pdo->prepare("
        INSERT INTO product_variants 
        (product_id, variant_name, unit, weight_kg, retail_price, wholesale_price, wholesale_min_qty)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $_POST['product_id'],
        $_POST['variant_name'] ?? '',
        $_POST['unit'],
        $_POST['weight'] ?? 0,
        $_POST['retail_price'],
        $_POST['wholesale_price'],
        $_POST['wholesale_qty']
    ]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>