<?php
require_once '../config/database.php';
checkAuth();
header('Content-Type: application/json');

$category_filter = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

$sql = "SELECT p.* FROM products p WHERE p.status = 'active'";
$params = [];

if ($category_filter) {
    $sql .= " AND p.category = ?";
    $params[] = $category_filter;
}

if ($search) {
    $sql .= " AND (p.name LIKE ? OR p.code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY p.category, p.name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Add prices to each product
foreach ($products as &$product) {
    // Retail price
    $stmt = $pdo->prepare("
        SELECT price_per_unit FROM pricing_tiers 
        WHERE product_id = ? AND customer_type = 'retail' 
        ORDER BY min_quantity ASC LIMIT 1
    ");
    $stmt->execute([$product['id']]);
    $product['retail_price'] = $stmt->fetchColumn() ?: 0;
    
    // Wholesale price
    $stmt = $pdo->prepare("
        SELECT price_per_unit FROM pricing_tiers 
        WHERE product_id = ? AND customer_type = 'wholesale' 
        ORDER BY min_quantity ASC LIMIT 1
    ");
    $stmt->execute([$product['id']]);
    $product['wholesale_price'] = $stmt->fetchColumn() ?: 0;
}

echo json_encode($products);
?>