<?php
require_once '../config/database.php';
checkAuth();
header('Content-Type: application/json');

try {
    $product_id = $_POST['product_id'];
    $base_unit_id = $_POST['base_unit_id'];
    
    $stmt = $pdo->prepare("UPDATE products SET base_unit_id = ? WHERE id = ?");
    $stmt->execute([$base_unit_id, $product_id]);
    
    echo json_encode(['success' => true, 'message' => 'Base unit updated!']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>