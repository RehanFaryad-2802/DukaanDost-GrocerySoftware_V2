<?php
require_once 'config/database.php';

echo "Duplicate unit names:\n";
$stmt = $pdo->query("SELECT name, COUNT(*) as count FROM units GROUP BY name HAVING count > 1");
$dups = $stmt->fetchAll();

foreach ($dups as $dup) {
    echo "Name: {$dup['name']}, Count: {$dup['count']}\n";

    // Show the units
    $stmt2 = $pdo->prepare("SELECT id, symbol FROM units WHERE name = ?");
    $stmt2->execute([$dup['name']]);
    $units = $stmt2->fetchAll();
    foreach ($units as $unit) {
        echo "  ID: {$unit['id']}, Symbol: {$unit['symbol']}\n";

        // Count products
        $stmt3 = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE unit = ?");
        $stmt3->execute([$unit['symbol']]);
        $count = $stmt3->fetchColumn();
        echo "    Products: $count\n";
    }
    echo "\n";
}

echo "Categories with name 'رول':\n";
$stmt = $pdo->prepare("SELECT id, name FROM categories WHERE name = ?");
$stmt->execute(['رول']);
$cats = $stmt->fetchAll();

foreach ($cats as $cat) {
    echo "ID: {$cat['id']}, Name: {$cat['name']}\n";

    // Count products in this category
    $stmt2 = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE category = ?");
    $stmt2->execute([$cat['name']]);
    $count = $stmt2->fetchColumn();
    echo "  Products in this category: $count\n";

    if ($count > 0) {
        $stmt3 = $pdo->prepare("SELECT name FROM products WHERE category = ?");
        $stmt3->execute([$cat['name']]);
        $products = $stmt3->fetchAll(PDO::FETCH_COLUMN);
        echo "  Products: " . implode(', ', $products) . "\n";
    }
    echo "\n";
}
?>