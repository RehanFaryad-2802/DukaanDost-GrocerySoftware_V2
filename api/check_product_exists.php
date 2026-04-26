<?php
require_once '../config/database.php';
header('Content-Type: application/json');

$product_name = trim($_GET['name'] ?? '');

if (empty($product_name)) {
    echo json_encode(['exists' => false]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, name, status 
        FROM products 
        WHERE name = ? OR name LIKE ?
        LIMIT 1
    ");

    // Try exact match first
    $stmt->execute([$product_name, $product_name]);
    $product = $stmt->fetch();

    if ($product) {
        echo json_encode([
            'exists' => true,
            'product_id' => $product['id'],
            'product_name' => $product['name'],
            'status' => $product['status']
        ]);
    } else {
        // Try removing extra spaces and normalize
        $normalized = preg_replace('/\s+/', ' ', trim($product_name));
        $stmt = $pdo->prepare("SELECT id, name FROM products WHERE REPLACE(name, ' ', '') = REPLACE(?, ' ', '')");
        $stmt->execute([$normalized]);
        $product = $stmt->fetch();

        if ($product) {
            echo json_encode([
                'exists' => true,
                'product_id' => $product['id'],
                'product_name' => $product['name'],
                'fuzzy_match' => true
            ]);
        } else {
            echo json_encode(['exists' => false]);
        }
    }

} catch (Exception $e) {
    echo json_encode(['exists' => false, 'error' => $e->getMessage()]);
}
?>