<?php
$conn = new mysqli('localhost', 'root', '', 'grocery_billing');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Fixing User Passwords</h2>";

// Create new password hashes
$new_hash = password_hash('123456', PASSWORD_DEFAULT);
echo "<p>New hash created: " . substr($new_hash, 0, 30) . "...</p>";

$users = ['admin', 'manager', 'cashier1', 'cashier2'];

foreach ($users as $username) {
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
    $stmt->bind_param("ss", $new_hash, $username);

    if ($stmt->execute()) {
        echo "<p style='color: green;'>✅ Updated password for: $username</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to update: $username</p>";
    }
    $stmt->close();
}

// Verify the fix
echo "<h3>Verification:</h3>";
$result = $conn->query("SELECT username, password FROM users WHERE username = 'admin'");
$user = $result->fetch_assoc();

if (password_verify('123456', $user['password'])) {
    echo "<p style='color: green; font-size: 18px;'>✅✅✅ PASSWORD FIXED! You can now login with admin / 123456</p>";
    echo "<p><a href='index.php' style='font-size: 18px;'>👉 Click here to LOGIN NOW 👈</a></p>";
} else {
    echo "<p style='color: red;'>Still not working. Let's try alternative method.</p>";
}

$conn->close();
?>