<?php
require_once '../config/database.php';
checkAuth();
header('Content-Type: application/json');

$product_id = $_POST['product_id'] ?? 0;
$package_name = trim($_POST['package_name'] ?? '');
$multiplier = floatval($_POST['multiplier'] ?? 0);

if (empty($package_name) || $multiplier <= 0) {
    echo json_encode(['success' => false, 'error' => 'Package name and multiplier required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO product_packages (product_id, package_name, multiplier)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$product_id, $package_name, $multiplier]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>