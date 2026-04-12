<?php
require_once '../config/database.php';
header('Content-Type: application/json');

$mode = $_GET['mode'] ?? 'search';
$search = $_GET['q'] ?? '';
$customer_type = $_GET['customer_type'] ?? 'retail';

// Popular products mode (for Quick Products)
if ($mode === 'popular') {
    // Try to get most sold products first
    $stmt = $pdo->query("
        SELECT 
            p.id, 
            p.code, 
            p.name, 
            p.unit, 
            p.current_stock, 
            p.purchase_price,
            COUNT(ii.id) as sales_count
        FROM products p
        LEFT JOIN invoice_items ii ON p.id = ii.product_id
        WHERE p.status = 'active' AND p.current_stock > 0
        GROUP BY p.id
        HAVING sales_count > 0
        ORDER BY sales_count DESC, p.name ASC
        LIMIT 10
    ");
    $products = $stmt->fetchAll();
    
    // If no products have sales, fallback to first 10 products
    if (count($products) == 0) {
        $stmt = $pdo->query("
            SELECT 
                p.id, 
                p.code, 
                p.name, 
                p.unit, 
                p.current_stock, 
                p.purchase_price,
                0 as sales_count
            FROM products p
            WHERE p.status = 'active' AND p.current_stock > 0
            ORDER BY p.name ASC
            LIMIT 10
        ");
        $products = $stmt->fetchAll();
    }
    
    echo json_encode($products);
    exit;
}

// Regular search mode
if ($mode === 'search' || empty($mode)) {
    if (empty($search)) {
        // Empty search - return first 10 products
        $stmt = $pdo->prepare("
            SELECT id, code, name, unit, current_stock, purchase_price 
            FROM products 
            WHERE status = 'active' AND current_stock > 0
            ORDER BY name ASC
            LIMIT 10
        ");
        $stmt->execute();
    } else {
        // Search by name or code
        $stmt = $pdo->prepare("
            SELECT id, code, name, unit, current_stock, purchase_price 
            FROM products 
            WHERE (name LIKE ? OR code LIKE ?) 
            AND status = 'active' AND current_stock > 0
            ORDER BY name ASC
            LIMIT 10
        ");
        $stmt->execute(["%$search%", "%$search%"]);
    }
    $products = $stmt->fetchAll();
    echo json_encode($products);
    exit;
}
?>