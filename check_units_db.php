<?php
require_once 'config/database.php';

$stmt = $pdo->query('SELECT COUNT(*) FROM units');
echo 'Units count: ' . $stmt->fetchColumn() . PHP_EOL;

$stmt = $pdo->query('SELECT name FROM units LIMIT 5');
$rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo 'Sample units: ' . implode(', ', $rows) . PHP_EOL;
?>