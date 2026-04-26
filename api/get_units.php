<?php
require_once '../config/database.php';
checkAuth();
header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT id, name, symbol, type FROM units ORDER BY type, name");
    $units = $stmt->fetchAll();
    echo json_encode($units);
} catch (Exception $e) {
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