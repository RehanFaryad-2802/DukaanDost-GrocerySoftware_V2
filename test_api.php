<?php
require_once 'config/database.php';
// simulate checkAuth
session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // fake
}

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT id, name, symbol, type, name_singular, name_plural, sub_singular, sub_plural, sub_factor FROM units ORDER BY type, name");
    $units = $stmt->fetchAll();
    echo json_encode($units);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    $defaultUnits = [
        ['id' => 1, 'name' => 'Piece', 'symbol' => 'Piece', 'type' => 'base'],
        ['id' => 2, 'name' => 'Kilogram', 'symbol' => 'kg', 'type' => 'weight'],
        ['id' => 3, 'name' => 'Gram', 'symbol' => 'g', 'type' => 'weight'],
        ['id' => 4, 'name' => 'Liter', 'symbol' => 'liter', 'type' => 'volume'],
        ['id' => 5, 'name' => 'Packet', 'symbol' => 'packet', 'type' => 'packaging'],
        ['id' => 6, 'name' => 'Dozen', 'symbol' => 'dozen', 'type' => 'packaging'],
        ['id' => 7, 'name' => 'Box', 'symbol' => 'box', 'type' => 'packaging'],
    ];
    echo json_encode($defaultUnits);
}
?>