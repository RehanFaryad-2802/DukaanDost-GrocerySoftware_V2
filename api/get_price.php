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
        ORDER BY min_quantity DESC
    ");
    $stmt->execute([$product_id, $customer_type]);
    $all_tiers = $stmt->fetchAll();

    $unit_price = 0;
    $tier_info = '';

    foreach ($all_tiers as $tier) {
        if ($quantity >= $tier['min_quantity']) {
            if ($tier['max_quantity'] === null || $quantity <= $tier['max_quantity']) {
                $unit_price = floatval($tier['price_per_unit']);
                $tier_info = "Tier: {$tier['min_quantity']} - " . ($tier['max_quantity'] ?? '∞') . " units";
                break;
            }
        }
    }

    if ($unit_price == 0 && !empty($all_tiers)) {
        $stmt = $pdo->prepare("
            SELECT * FROM pricing_tiers 
            WHERE product_id = ? AND customer_type = ?
            ORDER BY min_quantity ASC LIMIT 1
        ");
        $stmt->execute([$product_id, $customer_type]);
        $lowest_tier = $stmt->fetch();
        if ($lowest_tier) {
            $unit_price = floatval($lowest_tier['price_per_unit']);
            $tier_info = "Tier: {$lowest_tier['min_quantity']} - " . ($lowest_tier['max_quantity'] ?? '∞') . " units (fallback)";
        }
    }

    $total = $unit_price * $quantity;

    echo json_encode([
        'unit_price' => $unit_price,
        'total_price' => $total,
        'tier_info' => $tier_info
    ]);

} catch (Exception $e) {
    echo json_encode(['unit_price' => 0, 'total_price' => 0, 'tier_info' => '']);
}
?>