<?php
require_once '../config/database.php';
checkAuth();
header('Content-Type: application/json');

try {
    // Regular product addition only (no more temp_product)
    $code = $_POST['code'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $category = $_POST['category'] ?: null;
    $unit = $_POST['unit'] ?? 'piece';
    $description = $_POST['description'] ?: null;
    $stock = floatval($_POST['current_stock'] ?? 999999);
    $min_alert = floatval($_POST['min_stock_alert'] ?? 10);
    $cost = floatval($_POST['purchase_price'] ?? 0);
    $retail = floatval($_POST['retail_price'] ?? 0);
    $wholesale = floatval($_POST['wholesale_price'] ?? 0);
    $wholesale_min = floatval($_POST['wholesale_min_qty'] ?? 5);

    // Auto-fill missing tier: if only retail, use for wholesale too; if only wholesale, use for retail too
    if ($retail > 0 && $wholesale == 0) {
        $wholesale = $retail;
    } elseif ($wholesale > 0 && $retail == 0) {
        $retail = $wholesale;
    }

    if (empty($name) || empty($unit) || $retail <= 0) {
        echo json_encode(['success' => false, 'error' => 'Name, unit, and at least one price (retail or wholesale) are required']);
        exit;
    }

    if (empty($code)) {
        $code = 'PRD' . strtoupper(substr(uniqid(), -8));
    }

    $pdo->beginTransaction();

    if (!empty($category)) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO categories (name) VALUES (?)");
        $stmt->execute([$category]);
    }

    // Check if product with same name exists
    $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ?");
    $stmt->execute([$name]);
    $existing = $stmt->fetch();

    if ($existing) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Product with this name already exists!']);
        exit;
    }

    // Insert product as regular product (not temporary)
    $stmt = $pdo->prepare("
        INSERT INTO products (code, name, description, category, unit, 
                            current_stock, min_stock_alert, purchase_price, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
    ");
    $stmt->execute([$code, $name, $description, $category, $unit, $stock, $min_alert, $cost]);
    $product_id = $pdo->lastInsertId();

    // Add retail pricing tier (only if retail > 0)
    if ($retail > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO pricing_tiers (product_id, customer_type, min_quantity, max_quantity, price_per_unit) 
            VALUES (?, 'retail', 1, NULL, ?)
        ");
        $stmt->execute([$product_id, $retail]);
    }

    // Add wholesale pricing tier (only if wholesale > 0)
    if ($wholesale > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO pricing_tiers (product_id, customer_type, min_quantity, max_quantity, price_per_unit) 
            VALUES (?, 'wholesale', ?, NULL, ?)
        ");
        $stmt->execute([$product_id, $wholesale_min, $wholesale]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'product_id' => $product_id,
        'message' => 'Product added successfully!'
    ]);

} catch (Exception $e) {
    if (isset($pdo))
        $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>