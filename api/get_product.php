<?php
require_once '../config/database.php';
checkAuth();
header('Content-Type: application/json');

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if ($product) {
    echo json_encode($product);
} else {
    echo json_encode(['error' => 'Product not found']);
}
?>