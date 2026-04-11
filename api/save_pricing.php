<?php
require_once '../config/database.php';
checkAuth();
header('Content-Type: application/json');

try {
    $product_id = $_POST['product_id'];
    $customer_type = $_POST['customer_type'];
    $min_quantity = floatval($_POST['min_quantity']);
    $max_quantity = isset($_POST['max_quantity']) && $_POST['max_quantity'] !== '' ? floatval($_POST['max_quantity']) : null;
    $price_per_unit = floatval($_POST['price_per_unit']);
    
    $stmt = $pdo->prepare("
        INSERT INTO pricing_tiers (product_id, customer_type, min_quantity, max_quantity, price_per_unit) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$product_id, $customer_type, $min_quantity, $max_quantity, $price_per_unit]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>