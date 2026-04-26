<?php
$conn = new mysqli('localhost', 'root', '', 'grocery_billing');

$result = $conn->query("SELECT id, username, role, status FROM users");
echo "<h3>Users in database:</h3>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID</th><th>Username</th><th>Role</th><th>Status</th></tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['username'] . "</td>";
    echo "<td>" . $row['role'] . "</td>";
    echo "<td>" . $row['status'] . "</td>";
    echo "</tr>";
}
echo "</table>";

$username = 'admin';
$stmt = $conn->prepare("SELECT password FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

echo "<h3>Password verification test:</h3>";
if (password_verify('123456', $user['password'])) {
    echo "✅ Password '123456' works with admin!";
} else {
    echo "❌ Password doesn't match. Hash in DB: " . substr($user['password'], 0, 20) . "...";
}

$conn->close();
?>