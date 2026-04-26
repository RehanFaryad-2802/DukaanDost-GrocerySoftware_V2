<?php
require_once '../config/database.php';
checkAuth();
header('Content-Type: application/json');

$product_id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT price_per_unit FROM pricing_tiers 
    WHERE product_id = ? AND customer_type = 'retail' 
    ORDER BY min_quantity ASC LIMIT 1
");
$stmt->execute([$product_id]);
$retail = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->prepare("
    SELECT price_per_unit FROM pricing_tiers 
    WHERE product_id = ? AND customer_type = 'wholesale' 
    ORDER BY min_quantity ASC LIMIT 1
");
$stmt->execute([$product_id]);
$wholesale = $stmt->fetchColumn() ?: 0;

echo json_encode(['retail' => $retail, 'wholesale' => $wholesale]);
?>