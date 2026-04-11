<?php

session_start();
date_default_timezone_set('Asia/Kolkata');

$host = 'localhost';
$dbname = 'grocery_billing';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get store settings
$stmt = $pdo->query("SELECT * FROM settings");
$settings = [];
while($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Check if user is logged in
function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
}

// Check user role permission
function hasPermission($allowed_roles) {
    if (!in_array($_SESSION['user_role'], $allowed_roles)) {
        header('Location: dashboard.php?error=unauthorized');
        exit;
    }
}
?>