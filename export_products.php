<?php
require_once 'config/database.php';
checkAuth();

$stmt = $pdo->query("
    SELECT 
        p.id,
        p.code,
        p.name,
        p.description,
        p.unit,
        p.category,
        p.current_stock,
        p.min_stock_alert,
        p.purchase_price,
        p.status,
        p.created_at,
        GROUP_CONCAT(
            CONCAT(pt.customer_type, ':', pt.min_quantity, '-', 
                   COALESCE(pt.max_quantity, '∞'), '=', pt.price_per_unit)
            SEPARATOR ' | '
        ) as pricing_tiers
    FROM products p
    LEFT JOIN pricing_tiers pt ON p.id = pt.product_id
    GROUP BY p.id
    ORDER BY p.name
");
$products = $stmt->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=products_export_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');

fputcsv($output, [
    'ID',
    'Code',
    'Name',
    'Description',
    'Unit',
    'Category',
    'Current Stock',
    'Min Alert',
    'Purchase Price',
    'Status',
    'Pricing Tiers',
    'Created Date'
]);

// Add data rows
foreach ($products as $product) {
    fputcsv($output, [
        $product['id'],
        $product['code'],
        $product['name'],
        $product['description'],
        $product['unit'],
        $product['category'],
        $product['current_stock'],
        $product['min_stock_alert'],
        $product['purchase_price'],
        $product['status'],
        $product['pricing_tiers'],
        $product['created_at']
    ]);
}

fclose($output);
exit;
?>