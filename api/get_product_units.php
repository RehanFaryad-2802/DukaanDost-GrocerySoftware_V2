<?php
require_once '../config/database.php';
checkAuth();
header('Content-Type: application/json');

$product_id = $_GET['product_id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT p.*, u.name as base_unit_name, u.symbol as base_unit_symbol, u.id as base_unit_id
    FROM products p
    LEFT JOIN units u ON p.base_unit_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT 
        uc.*,
        fu.name as from_unit_name,
        fu.symbol as from_unit_symbol,
        tu.name as to_unit_name,
        tu.symbol as to_unit_symbol
    FROM unit_conversions uc
    JOIN units fu ON uc.from_unit_id = fu.id
    JOIN units tu ON uc.to_unit_id = tu.id
    WHERE uc.product_id = ?
    ORDER BY uc.conversion_factor
");
$stmt->execute([$product_id]);
$conversions = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT * FROM units 
    WHERE type IN ('packaging', 'base') 
    ORDER BY name
");
$stmt->execute();
$all_units = $stmt->fetchAll();

echo json_encode([
    'product' => $product,
    'conversions' => $conversions,
    'all_units' => $all_units
]);
?>