<?php
require_once '../config/database.php';
header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['value' => 'on']); // Default to on
    exit;
}

$user_id = $_SESSION['user_id'];
$key = $_GET['key'] ?? '';

if (!$key) {
    echo json_encode(['value' => 'on']);
    exit;
}

$stmt = $pdo->prepare("SELECT preference_value FROM user_preferences WHERE user_id = ? AND preference_key = ?");
$stmt->execute([$user_id, $key]);
$result = $stmt->fetch();

// Return 'on' as default if not set
echo json_encode(['value' => $result['preference_value'] ?? 'on']);
?>