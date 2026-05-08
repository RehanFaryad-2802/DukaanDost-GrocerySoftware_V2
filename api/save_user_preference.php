<?php
require_once '../config/database.php';
header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$key = $_POST['key'] ?? '';
$value = $_POST['value'] ?? '';

if (!$key) {
    echo json_encode(['success' => false, 'error' => 'No key provided']);
    exit;
}

try {
    // Check if table exists, if not create it
    try {
        $pdo->query("SELECT 1 FROM user_preferences LIMIT 1");
    } catch (PDOException $e) {
        // Create table if not exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_preferences (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            preference_key VARCHAR(50) NOT NULL,
            preference_value VARCHAR(50) DEFAULT 'on',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_pref (user_id, preference_key)
        )");
    }

    $stmt = $pdo->prepare("INSERT INTO user_preferences (user_id, preference_key, preference_value) 
                           VALUES (?, ?, ?) 
                           ON DUPLICATE KEY UPDATE preference_value = ?");
    $stmt->execute([$user_id, $key, $value, $value]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>