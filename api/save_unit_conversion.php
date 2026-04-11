<?php
require_once '../config/database.php';
checkAuth();
header('Content-Type: application/json');

try {
    $product_id = $_POST['product_id'];
    $from_unit_id = $_POST['from_unit_id'];
    $conversion_factor = floatval($_POST['conversion_factor']);
    $conversion_name = $_POST['conversion_name'] ?: null;
    $is_wholesale = isset($_POST['is_wholesale_unit']) ? 1 : 0;
    $wholesale_price = $_POST['wholesale_price'] ?: null;
    $retail_price = $_POST['retail_price'] ?: null;
    $min_wholesale_qty = floatval($_POST['min_wholesale_qty'] ?? 1);
    
    // Get base unit ID
    $stmt = $pdo->prepare("SELECT base_unit_id FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    $to_unit_id = $product['base_unit_id'];
    
    if (!$to_unit_id) {
        // Default to piece
        $stmt = $pdo->prepare("SELECT id FROM units WHERE symbol = 'pc' LIMIT 1");
        $stmt->execute();
        $to_unit_id = $stmt->fetchColumn();
    }
    
    // Check if conversion already exists
    $stmt = $pdo->prepare("
        SELECT id FROM unit_conversions 
        WHERE product_id = ? AND from_unit_id = ?
    ");
    $stmt->execute([$product_id, $from_unit_id]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'This unit conversion already exists!']);
        exit;
    }
    
    // Insert conversion
    $stmt = $pdo->prepare("
        INSERT INTO unit_conversions 
        (product_id, from_unit_id, to_unit_id, conversion_name, conversion_factor, 
         is_wholesale_unit, wholesale_price, retail_price, min_wholesale_qty)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $product_id,
        $from_unit_id,
        $to_unit_id,
        $conversion_name,
        $conversion_factor,
        $is_wholesale,
        $wholesale_price,
        $retail_price,
        $min_wholesale_qty
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Unit conversion added successfully!']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>