<?php
require_once '../config/database.php';
checkAuth();
header('Content-Type: application/json');

try {
    $id = $_POST['id'];
    $code = trim($_POST['code']);
    $name = trim($_POST['name']);
    $english_name = trim($_POST['english_name'] ?? '') ?: null;
    $category = $_POST['category'] ?: null;
    $unit = $_POST['unit'];
    $description = $_POST['description'] ?: null;
    $current_stock = floatval($_POST['current_stock'] ?? 0);
    $min_stock_alert = floatval($_POST['min_stock_alert'] ?? 10);
    $purchase_price = floatval($_POST['purchase_price'] ?? 0);
    $status = $_POST['status'] ?? 'active';

    $stmt = $pdo->prepare("SELECT id FROM products WHERE code = ? AND id != ?");
    $stmt->execute([$code, $id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Product code already exists!']);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE products SET 
            code = ?,
            name = ?,
            category = ?,
            unit = ?,
            description = ?,
            current_stock = ?,
            min_stock_alert = ?,
            purchase_price = ?,
            status = ?,
            updated_at = NOW()
        WHERE id = ?
    ");

    $result = $stmt->execute([
        $code,
        $name,
        $english_name,
        $category,
        $unit,
        $description,
        $current_stock,
        $min_stock_alert,
        $purchase_price,
        $status,
        $id
    ]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => "Product '$name' updated successfully!"]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No changes made or update failed']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>