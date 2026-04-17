<?php
require_once '../config/database.php';
checkAuth();
header('Content-Type: application/json');

$product_id = $_GET['product_id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT * FROM product_packages 
    WHERE product_id = ? 
    ORDER BY multiplier ASC
");
$stmt->execute([$product_id]);
$packages = $stmt->fetchAll();

echo json_encode($packages);
?>