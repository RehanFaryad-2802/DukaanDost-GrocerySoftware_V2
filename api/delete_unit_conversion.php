<?php
require_once '../config/database.php';
checkAuth();
header('Content-Type: application/json');

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("DELETE FROM unit_conversions WHERE id = ?");
$stmt->execute([$id]);

echo json_encode(['success' => true]);
?>