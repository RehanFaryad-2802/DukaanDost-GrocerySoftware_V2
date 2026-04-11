<?php
require_once '../config/database.php';
require_once '../config/functions.php';
header('Content-Type: application/json');

$product_id = $_POST['product_id'] ?? 0;
$quantity = $_POST['quantity'] ?? 0;
$customer_type = $_POST['customer_type'] ?? 'retail';

$result = calculatePrice($pdo, $product_id, $quantity, $customer_type);
echo json_encode($result);
?>