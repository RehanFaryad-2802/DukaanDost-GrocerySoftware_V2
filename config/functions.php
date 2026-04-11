<?php
require_once 'database.php';

// Calculate price based on tiered pricing
function calculatePrice($pdo, $product_id, $quantity, $customer_type) {
    $stmt = $pdo->prepare("
        SELECT * FROM pricing_tiers 
        WHERE product_id = ? 
        AND customer_type = ? 
        AND min_quantity <= ? 
        AND (max_quantity >= ? OR max_quantity IS NULL)
        ORDER BY min_quantity DESC 
        LIMIT 1
    ");
    $stmt->execute([$product_id, $customer_type, $quantity, $quantity]);
    $tier = $stmt->fetch();
    
    if (!$tier) {
        return ['error' => 'No pricing tier found'];
    }
    
    // Check if it's a fixed package price
    if ($tier['package_price'] && $quantity == $tier['min_quantity']) {
        $total = $tier['package_price'];
        $unit_price = $total / $quantity;
    } else {
        $unit_price = $tier['price_per_unit'];
        $total = $unit_price * $quantity;
    }
    
    return [
        'unit_price' => round($unit_price, 2),
        'total_price' => round($total, 2),
        'tier_min' => $tier['min_quantity'],
        'tier_max' => $tier['max_quantity'] ?? '∞'
    ];
}

// Get low stock products
function getLowStockProducts($pdo) {
    $stmt = $pdo->prepare("
        SELECT * FROM products 
        WHERE current_stock <= min_stock_alert 
        AND status = 'active'
        ORDER BY current_stock ASC
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

// Get today's sales summary
function getTodaySales($pdo) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_bills,
            SUM(CASE WHEN customer_type = 'wholesale' THEN total_amount ELSE 0 END) as wholesale_sales,
            SUM(CASE WHEN customer_type = 'retail' THEN total_amount ELSE 0 END) as retail_sales,
            SUM(total_amount) as total_sales
        FROM invoices 
        WHERE DATE(created_at) = CURDATE() 
        AND payment_status = 'paid'
    ");
    $stmt->execute();
    return $stmt->fetch();
}

// Calculate profit (simple version)
function calculateProfit($pdo, $invoice_id) {
    $stmt = $pdo->prepare("
        SELECT 
            ii.product_id,
            ii.quantity,
            ii.total_price as selling_price,
            p.purchase_price
        FROM invoice_items ii
        JOIN products p ON ii.product_id = p.id
        WHERE ii.invoice_id = ?
    ");
    $stmt->execute([$invoice_id]);
    $items = $stmt->fetchAll();
    
    $profit = 0;
    foreach ($items as $item) {
        $cost = $item['quantity'] * $item['purchase_price'];
        $profit += ($item['selling_price'] - $cost);
    }
    return $profit;
}
?>