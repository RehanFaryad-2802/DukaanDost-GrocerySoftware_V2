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

    // Get pricing fallback setting
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'pricing_fallback'");
    $stmt->execute();
    $fallback_row = $stmt->fetch();
    $pricing_fallback = ($fallback_row === false) ? 'on' : ($fallback_row['setting_value'] ?? 'on');

    function findPrice($pdo, $product_id, $customer_type, $quantity)
    {
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
            $min = floatval($tier['min_quantity']);
            $max = ($tier['max_quantity'] !== null && $tier['max_quantity'] !== '' && floatval($tier['max_quantity']) > 0)
                ? floatval($tier['max_quantity'])
                : null;

            if ($quantity >= $min) {
                if ($max === null || $quantity <= $max) {
                    $unit_price = floatval($tier['price_per_unit']);
                    $tier_info = "{$min} - " . ($max ?? '∞') . " {$customer_type}";
                    break;
                }
            }
        }

        // Fallback to lowest tier if still no match
        if ($unit_price == 0 && !empty($all_tiers)) {
            $lowest = end($all_tiers); // last after DESC sort = lowest min_quantity
            // re-sort to get actual lowest
            usort($all_tiers, fn($a, $b) => floatval($a['min_quantity']) <=> floatval($b['min_quantity']));
            $lowest = $all_tiers[0];
            $unit_price = floatval($lowest['price_per_unit']);
            $tier_info = floatval($lowest['min_quantity']) . " - ∞ {$customer_type} (fallback)";
        }

        return [$unit_price, $tier_info];
    }

    [$unit_price, $tier_info] = findPrice($pdo, $product_id, $customer_type, $quantity);

    // If still no price found, try other customer type (if fallback enabled)
    if ($unit_price == 0 && $pricing_fallback === 'on') {
        $other_type = ($customer_type === 'retail') ? 'wholesale' : 'retail';
        [$unit_price, $tier_info] = findPrice($pdo, $product_id, $other_type, $quantity);
        if ($unit_price > 0) {
            $tier_info .= ' (cross-type fallback)';
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