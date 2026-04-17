<?php
error_reporting(0);
require_once '../config/database.php';
header('Content-Type: application/json');

try {
    $product_id = intval($_POST['product_id'] ?? 0);
    $quantity = floatval($_POST['quantity'] ?? 0);
    $customer_type = $_POST['customer_type'] ?? 'retail';

    if ($product_id <= 0 || $quantity <= 0) {
        echo json_encode(['unit_price' => 0, 'total_price' => 0, 'tier_info' => '']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT * FROM pricing_tiers 
        WHERE product_id = ? AND customer_type = ?
        AND min_quantity <= ? AND (max_quantity >= ? OR max_quantity IS NULL)
        ORDER BY min_quantity DESC LIMIT 1
    ");
    $stmt->execute([$product_id, $customer_type, $quantity, $quantity]);
    $tier = $stmt->fetch();

    if ($tier) {
        $unit_price = floatval($tier['price_per_unit']);
        $total = $unit_price * $quantity;
        echo json_encode([
            'unit_price' => $unit_price,
            'total_price' => $total,
            'tier_info' => "{$tier['min_quantity']} - " . ($tier['max_quantity'] ?? '∞')
        ]);
    } else {
        // Fallback to any tier
        $stmt = $pdo->prepare("
            SELECT * FROM pricing_tiers 
            WHERE product_id = ? AND customer_type = ?
            ORDER BY min_quantity ASC LIMIT 1
        ");
        $stmt->execute([$product_id, $customer_type]);
        $tier = $stmt->fetch();
        
        if ($tier) {
            $unit_price = floatval($tier['price_per_unit']);
            $total = $unit_price * $quantity;
            echo json_encode([
                'unit_price' => $unit_price,
                'total_price' => $total,
                'tier_info' => "{$tier['min_quantity']} - " . ($tier['max_quantity'] ?? '∞')
            ]);
        } else {
            echo json_encode(['unit_price' => 0, 'total_price' => 0, 'tier_info' => '']);
        }
    }
} catch (Exception $e) {
    echo json_encode(['unit_price' => 0, 'total_price' => 0, 'tier_info' => '']);
}
?>