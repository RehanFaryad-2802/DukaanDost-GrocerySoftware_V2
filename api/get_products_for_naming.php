<?php
require_once '../config/database.php';
checkAuth();
header('Content-Type: application/json');

$offset = intval($_GET['offset'] ?? 0);
$limit = intval($_GET['limit'] ?? 30);
$missing = isset($_GET['missing']); // only missing english_name

if ($limit > 100)
    $limit = 100;

$where = $missing
    ? "WHERE status='active' AND (english_name IS NULL OR english_name = '')"
    : "WHERE status='active'";

$stmt = $pdo->prepare("SELECT id, name, category FROM products $where ORDER BY id ASC LIMIT $limit OFFSET $offset");
$stmt->execute();
echo json_encode($stmt->fetchAll());
