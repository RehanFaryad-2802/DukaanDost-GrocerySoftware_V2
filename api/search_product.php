<?php
require_once '../config/database.php';
header('Content-Type: application/json');

$mode = $_GET['mode'] ?? 'search';
$search = $_GET['q'] ?? '';
$customer_type = $_GET['customer_type'] ?? 'retail';

// Popular products mode (for Quick Products) - Get top selling products
if ($mode === 'popular') {
    // Get top 20 most sold products based on invoice items
    $stmt = $pdo->prepare("
        SELECT 
            p.id, 
            p.code, 
            p.name, 
            p.unit, 
            p.current_stock, 
            p.purchase_price,
            p.status,
            COALESCE(SUM(ii.quantity), 0) as total_sold
        FROM products p
        LEFT JOIN invoice_items ii ON p.id = ii.product_id
        WHERE p.status = 'active' AND p.current_stock > 0
        GROUP BY p.id
        ORDER BY p.sort_order ASC, total_sold DESC, p.name ASC
        LIMIT 12
    ");
    $stmt->execute();
    $products = $stmt->fetchAll();

    echo json_encode($products);
    exit;
}

// Regular search mode
if ($mode === 'search' || empty($mode)) {
    if (empty($search)) {
        $stmt = $pdo->prepare("
            SELECT 
                p.id, 
                p.code, 
                p.name, 
                p.unit, 
                p.current_stock,
                p.min_stock_alert,
                p.purchase_price,
                p.status
            FROM products p
            WHERE p.status = 'active' AND p.current_stock > 0
            ORDER BY name ASC
            LIMIT 50
        ");
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                p.id, 
                p.code, 
                p.name, 
                p.unit, 
                p.current_stock,
                p.min_stock_alert,
                p.purchase_price,
                p.status
            FROM products p
            WHERE (p.name LIKE ? OR p.code LIKE ? OR p.english_name LIKE ?) 
            AND p.status = 'active' AND p.current_stock > 0
            ORDER BY name ASC
            LIMIT 50
        ");
        $stmt->execute(["%$search%", "%$search%", "%$search%"]);
    }
    $products = $stmt->fetchAll();

    // Add pricing information for each product
    foreach ($products as &$product) {
        $stmt2 = $pdo->prepare("
            SELECT price_per_unit FROM pricing_tiers 
            WHERE product_id = ? AND customer_type = 'retail' 
            ORDER BY min_quantity ASC LIMIT 1
        ");
        $stmt2->execute([$product['id']]);
        $retail = $stmt2->fetchColumn();
        $product['pricing_tiers'] = $retail ? "Retail: Rs.$retail" : "No price set";
    }

    echo json_encode($products);
    exit;
}
?>