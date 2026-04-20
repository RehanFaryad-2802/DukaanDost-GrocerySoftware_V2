<?php
require_once '../config/database.php';
checkAuth();
header('Content-Type: application/json');

$search = $_GET['q'] ?? '';

$stmt = $pdo->prepare("
    SELECT id, name, phone, customer_type 
    FROM customers 
    WHERE name LIKE ? OR phone LIKE ?
    ORDER BY name
    LIMIT 10
");
$stmt->execute(["%$search%", "%$search%"]);
$customers = $stmt->fetchAll();

echo json_encode($customers);
?>