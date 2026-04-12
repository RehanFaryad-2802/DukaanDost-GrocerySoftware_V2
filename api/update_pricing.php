<?php
require_once '../config/database.php';
checkAuth();
header('Content-Type: application/json');

try {
    $tier_id = $_POST['tier_id'];
    $customer_type = $_POST['customer_type'];
    $min_quantity = floatval($_POST['min_quantity']);
    $max_quantity = isset($_POST['max_quantity']) && $_POST['max_quantity'] !== '' ? floatval($_POST['max_quantity']) : null;
    $price_per_unit = floatval($_POST['price_per_unit']);
    
    $stmt = $pdo->prepare("
        UPDATE pricing_tiers 
        SET customer_type = ?, min_quantity = ?, max_quantity = ?, price_per_unit = ?
        WHERE id = ?
    ");
    $stmt->execute([$customer_type, $min_quantity, $max_quantity, $price_per_unit, $tier_id]);
    
    echo json_encode(['success' => true, 'message' => 'Pricing tier updated!']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>