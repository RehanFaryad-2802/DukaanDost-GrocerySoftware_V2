<?php
require_once '../config/database.php';
checkAuth();
header('Content-Type: application/json');

$id = $_GET['id'] ?? 0;

try {
    $stmt = $pdo->prepare("DELETE FROM product_packages WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>