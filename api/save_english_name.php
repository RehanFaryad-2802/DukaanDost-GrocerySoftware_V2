<?php
require_once '../config/database.php';
checkAuth();
header('Content-Type: application/json');

try {
    $product_id   = intval($_POST['product_id'] ?? 0);
    $english_name = trim($_POST['english_name'] ?? '');

    if (!$product_id || !$english_name) {
        echo json_encode(['success' => false, 'error' => 'Missing data']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE products SET english_name = ? WHERE id = ?");
    $stmt->execute([$english_name, $product_id]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
