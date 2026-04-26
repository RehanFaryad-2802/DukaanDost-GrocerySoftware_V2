<?php
require_once '../config/database.php';
checkAuth();
header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT id, name, description FROM categories ORDER BY name");
    $categories = $stmt->fetchAll();
    echo json_encode($categories);
} catch (Exception $e) {
    echo json_encode([]);
}
?>