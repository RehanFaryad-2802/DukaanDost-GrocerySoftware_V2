<?php
require_once 'config/database.php';
checkAuth();

// Only admin can import
if ($_SESSION['user_role'] != 'admin') {
    die("Only admin can import products.");
}

// Set UTF-8 encoding for database connection
$pdo->exec("SET NAMES utf8mb4");
$pdo->exec("SET CHARACTER SET utf8mb4");
$pdo->exec("SET COLLATION_CONNECTION = 'utf8mb4_unicode_ci'");

echo "<h2>Importing Products from CSV</h2>";
echo "<pre style='font-family: Arial, sans-serif;'>";

$file = 'products_import.csv';

if (!file_exists($file)) {
    die("❌ File not found: $file<br>Please save the Excel as CSV UTF-8 first.");
}

$handle = fopen($file, 'r');
if (!$handle) {
    die("❌ Cannot open file");
}

// Skip header row
$header = fgetcsv($handle);

echo "✅ File opened successfully<br>";
echo "Found columns: " . implode(', ', $header) . "<br><br>";

$imported = 0;
$skipped = 0;
$errors = [];

// First, fix database collation
try {
    $pdo->exec("ALTER TABLE products CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("ALTER TABLE pricing_tiers CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✅ Database collation fixed<br><br>";
} catch (Exception $e) {
    // Ignore if already converted
}

// Start transaction
$pdo->beginTransaction();

try {
    while (($row = fgetcsv($handle)) !== false) {
        // Map CSV columns to array
        $data = array_combine($header, $row);
        
        // Extract product details with proper encoding
        $name = trim($data['Product Name'] ?? '');
        $barcode = trim($data['Barcode'] ?? '');
        $description = trim($data['Description'] ?? '');
        $category = trim($data['Category'] ?? 'General');
        
        // Skip if no name
        if (empty($name)) {
            $skipped++;
            continue;
        }
        
        // Determine unit
        $unit = 'piece';
        if (strpos($name, 'کلو') !== false || strpos($category, 'دال') !== false || 
            strpos($category, 'چاول') !== false || strpos($category, 'چینی') !== false) {
            $unit = 'kg';
        } elseif (strpos($category, 'آئل') !== false || strpos($category, 'گھی') !== false) {
            $unit = 'liter';
        }
        
        // Get prices
        $purchase_price = floatval($data['Purchase Price'] ?? 0);
        $wholesale_price = floatval($data['Wholesale Price'] ?? 0);
        $min_wholesale_qty = floatval($data['Minimum Wholesale Qty'] ?? 5);
        $sale_price = floatval($data['Sale Price'] ?? 0);
        
        // If no sale price, use wholesale + 10%
        if ($sale_price <= 0 && $wholesale_price > 0) {
            $sale_price = $wholesale_price * 1.1;
        }
        
        // If still no price, use default
        if ($sale_price <= 0) {
            $sale_price = 100;
        }
        
        // Generate product code
        $code = !empty($barcode) ? $barcode : 'PRD' . rand(10000, 99999);
        
        // Check if product already exists - use COLLATE to fix collation issue
        $stmt = $pdo->prepare("
            SELECT id FROM products 
            WHERE code = :code 
            OR name = :name COLLATE utf8mb4_unicode_ci
        ");
        $stmt->execute(['code' => $code, 'name' => $name]);
        $exists = $stmt->fetch();
        
        if ($exists) {
            echo "⏭️ Skipping: $name (already exists)<br>";
            $skipped++;
            continue;
        }
        
        // Insert product
        $stmt = $pdo->prepare("
            INSERT INTO products (code, name, description, unit, category, 
                                current_stock, min_stock_alert, purchase_price, status) 
            VALUES (:code, :name, :description, :unit, :category, :stock, :alert, :purchase, 'active')
        ");
        
        $initial_stock = 100;
        $min_alert = 10;
        
        $stmt->execute([
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'unit' => $unit,
            'category' => $category,
            'stock' => $initial_stock,
            'alert' => $min_alert,
            'purchase' => $purchase_price
        ]);
        
        $product_id = $pdo->lastInsertId();
        
        // Add retail pricing tier
        if ($sale_price > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO pricing_tiers (product_id, customer_type, min_quantity, max_quantity, price_per_unit) 
                VALUES (:pid, 'retail', 1, NULL, :price)
            ");
            $stmt->execute(['pid' => $product_id, 'price' => $sale_price]);
        }
        
        // Add wholesale pricing tier
        if ($wholesale_price > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO pricing_tiers (product_id, customer_type, min_quantity, max_quantity, price_per_unit) 
                VALUES (:pid, 'wholesale', :min_qty, NULL, :price)
            ");
            $stmt->execute([
                'pid' => $product_id, 
                'min_qty' => $min_wholesale_qty, 
                'price' => $wholesale_price
            ]);
        }
        
        echo "✅ Imported: $name - Rs. $sale_price<br>";
        $imported++;
    }
    
    $pdo->commit();
    
    echo "<br>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br>";
    echo "✅ IMPORT COMPLETED!<br>";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br>";
    echo "📦 Total Imported: $imported products<br>";
    echo "⏭️ Skipped: $skipped products<br>";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ ERROR: " . $e->getMessage() . "<br>";
}

fclose($handle);

echo "<br><a href='products.php' class='btn btn-success' style='display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; margin-right: 10px;'>View All Products</a>";
echo "<a href='import_products.php' class='btn btn-primary' style='display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Import Remaining Products</a>";
?>