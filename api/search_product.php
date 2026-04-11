<?php
require_once '../config/database.php';
header('Content-Type: application/json');

$search = $_GET['q'] ?? '';
$customer_type = $_GET['customer_type'] ?? 'retail';

$stmt = $pdo->prepare("
    SELECT id, code, name, unit, current_stock, purchase_price 
    FROM products 
    WHERE (name LIKE ? OR code LIKE ?) 
    AND status = 'active'
    AND current_stock > 0
    LIMIT 10
");
$stmt->execute(["%$search%", "%$search%"]);
$products = $stmt->fetchAll();

echo json_encode($products);
?>