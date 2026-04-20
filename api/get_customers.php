<?php
require_once '../config/database.php';
checkAuth();
header('Content-Type: application/json');

$stmt = $pdo->query("
    SELECT id, name, phone, customer_type 
    FROM customers 
    WHERE id != 1 OR id IS NULL
    ORDER BY name
");
$customers = $stmt->fetchAll();

echo json_encode($customers);
?>