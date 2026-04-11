<?php
require_once 'config/database.php';

echo "<h2>Categories in Database</h2>";

// Check if categories table exists
$stmt = $pdo->query("SHOW TABLES LIKE 'categories'");
$hasCategoriesTable = $stmt->rowCount() > 0;

if ($hasCategoriesTable) {
    echo "<h3>From categories table:</h3>";
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
    $categories = $stmt->fetchAll();
    
    if (count($categories) > 0) {
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><th>ID</th><th>Name</th><th>Created</th></tr>";
        foreach ($categories as $cat) {
            echo "<tr>";
            echo "<td>{$cat['id']}</td>";
            echo "<td>{$cat['name']}</td>";
            echo "<td>{$cat['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No categories in categories table.</p>";
    }
}

echo "<h3>From products table (categories in use):</h3>";
$stmt = $pdo->query("
    SELECT DISTINCT category, COUNT(*) as product_count 
    FROM products 
    WHERE category IS NOT NULL AND category != '' 
    GROUP BY category 
    ORDER BY category
");
$cats = $stmt->fetchAll();

if (count($cats) > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Category Name</th><th>Products Count</th></tr>";
    foreach ($cats as $cat) {
        echo "<tr>";
        echo "<td><strong>{$cat['category']}</strong></td>";
        echo "<td>{$cat['product_count']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No categories found in products table.</p>";
}

echo "<p><a href='products.php'>Back to Products</a></p>";
?>