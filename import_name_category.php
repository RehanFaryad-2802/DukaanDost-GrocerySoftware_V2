<?php
require_once 'config/database.php';
checkAuth();

// Only admin can import
if ($_SESSION['user_role'] != 'admin') {
    die("Only admin can import products.");
}

echo "<h2>Import Products - Name & Category Only</h2>";
echo "<pre style='font-family: monospace;'>";

$file = 'products_import.csv';

if (!file_exists($file)) {
    die("❌ File not found: $file<br>Please place the CSV file in the grocery folder.");
}

$handle = fopen($file, 'r');
if (!$handle) {
    die("❌ Cannot open file");
}

// Read header row
$header = fgetcsv($handle);
echo "✅ File opened successfully<br>";
echo "Found columns: " . implode(', ', $header) . "<br><br>";

// Find column indexes
$nameIndex = array_search('Product Name', $header);
$categoryIndex = array_search('Category', $header);

if ($nameIndex === false) {
    die("❌ 'Name' column not found in CSV!");
}

echo "📌 Importing only: Name and Category<br>";
echo str_repeat("-", 50) . "<br><br>";

$imported = 0;
$skipped = 0;
$rowNumber = 1;

$pdo->beginTransaction();

try {
    while (($row = fgetcsv($handle)) !== false) {
        $rowNumber++;
        
        // Extract only Name and Category
        $name = isset($row[$nameIndex]) ? trim($row[$nameIndex]) : '';
        $category = ($categoryIndex !== false && isset($row[$categoryIndex])) ? trim($row[$categoryIndex]) : '';
        
        // Skip empty names
        if (empty($name)) {
            echo "⏭️ Row $rowNumber: Skipped (empty name)<br>";
            $skipped++;
            continue;
        }
        
        // Generate unique product code
        $code = 'PRD' . strtoupper(substr(uniqid(), -8));
        
        // Check if product with same name already exists
        $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            echo "⏭️ Row $rowNumber: '$name' already exists<br>";
            $skipped++;
            continue;
        }
        
        // Insert product with default values
        $stmt = $pdo->prepare("
            INSERT INTO products (
                code, name, category, unit, 
                current_stock, min_stock_alert, purchase_price, status
            ) VALUES (?, ?, ?, 'Piece', 0, 10, 0, 'active')
        ");
        $stmt->execute([$code, $name, $category ?: null]);
        
        echo "✅ Row $rowNumber: '$name' " . ($category ? "(Category: $category)" : "(No category)") . "<br>";
        $imported++;
    }
    
    $pdo->commit();
    
    echo "<br>" . str_repeat("-", 50) . "<br>";
    echo "✅ IMPORT COMPLETED!<br>";
    echo str_repeat("-", 50) . "<br>";
    echo "📦 Total Imported: $imported products<br>";
    echo "⏭️ Skipped: $skipped products<br>";
    echo "📊 Total Rows Processed: " . ($rowNumber - 1) . "<br>";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<br>❌ ERROR: " . $e->getMessage() . "<br>";
}

fclose($handle);

echo "<br><a href='products.php' class='btn btn-success'>View All Products</a>";
?>