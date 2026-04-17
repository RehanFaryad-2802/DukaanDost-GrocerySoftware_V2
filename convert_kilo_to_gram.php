<?php
require_once 'config/database.php';
checkAuth();

// Only admin can run this
if ($_SESSION['user_role'] != 'admin') {
    die("Only admin can run this.");
}

echo "<h2>🔧 Fix Pricing Tiers - Restore Original Min/Max Only (Keep Prices)</h2>";
echo "<style>
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
    th { background: #fd7e14; color: white; }
    .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
    .btn-warning { background: #fd7e14; color: white; }
    .btn-secondary { background: #6c757d; color: white; text-decoration: none; }
    .preview { background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0; }
</style>";

// Find products with unit 'کلو'
$stmt = $pdo->query("
    SELECT DISTINCT p.id, p.code, p.name, p.unit
    FROM products p
    WHERE p.unit = 'کلو'
    ORDER BY p.name
");
$products = $stmt->fetchAll();

if (count($products) === 0) {
    die("<p>❌ No products with unit 'کلو' found.</p>");
}

echo "<p>Found <strong>" . count($products) . "</strong> products with unit 'کلو'.</p>";

if (isset($_POST['fix']) && isset($_POST['selected_products'])) {
    $selected_ids = array_map('intval', $_POST['selected_products']);
    
    if (empty($selected_ids)) {
        echo "<p style='color: red;'>❌ No products selected!</p>";
    } else {
        $pdo->beginTransaction();
        
        try {
            $fixed = 0;
            
            foreach ($selected_ids as $product_id) {
                // Get product name
                $stmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                $name = $stmt->fetchColumn();
                
                // Fix Wholesale tiers - ONLY min and max, keep prices
                $stmt = $pdo->prepare("
                    UPDATE pricing_tiers 
                    SET min_quantity = 0, max_quantity = 4.999
                    WHERE product_id = ? AND customer_type = 'wholesale' 
                    AND min_quantity < 5 AND (max_quantity < 5 OR max_quantity IS NULL)
                ");
                $stmt->execute([$product_id]);
                
                $stmt = $pdo->prepare("
                    UPDATE pricing_tiers 
                    SET min_quantity = 5, max_quantity = 49.999
                    WHERE product_id = ? AND customer_type = 'wholesale' 
                    AND min_quantity >= 5 AND min_quantity < 50
                ");
                $stmt->execute([$product_id]);
                
                $stmt = $pdo->prepare("
                    UPDATE pricing_tiers 
                    SET min_quantity = 50, max_quantity = NULL
                    WHERE product_id = ? AND customer_type = 'wholesale' 
                    AND min_quantity >= 50
                ");
                $stmt->execute([$product_id]);
                
                // Fix Retail tiers - keep prices
                $stmt = $pdo->prepare("
                    UPDATE pricing_tiers 
                    SET min_quantity = 0, max_quantity = NULL
                    WHERE product_id = ? AND customer_type = 'retail'
                ");
                $stmt->execute([$product_id]);
                
                $fixed++;
                
                echo "<p style='color: green;'>✅ {$name}: Min/Max fixed (0-4.999, 5-49.999, 50+) - Prices UNCHANGED</p>";
            }
            
            $pdo->commit();
            
            echo "<hr>";
            echo "<p style='color: green; font-size: 18px;'>✅ FIX COMPLETE!</p>";
            echo "<p>📊 Products fixed: $fixed</p>";
            echo "<p style='color: #28a745;'><strong>✅ Prices were NOT changed!</strong></p>";
            echo "<br><a href='products.php' class='btn btn-secondary'>View Products</a>";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<p style='color: red;'>❌ ERROR: " . $e->getMessage() . "</p>";
        }
        
        exit;
    }
}
?>

<div class="preview">
    <strong>✅ WHAT THIS FIX DOES:</strong>
    <ul>
        <li>Wholesale tiers: Min/Max set to 0-4.999, 5-49.999, 50+</li>
        <li>Retail tiers: Min/Max set to 0+</li>
        <li><strong style='color: #28a745;'>✅ PRICES REMAIN UNCHANGED!</strong></li>
    </ul>
</div>

<form method="POST" onsubmit="return confirm('Fix min/max quantities? Prices will NOT be changed!')">
    <p><label><input type="checkbox" id="selectAll" onclick="toggleAll(this)"> <strong>Select All Products</strong></label></p>
    
    <table>
        <thead>
            <tr>
                <th width="50">Select</th>
                <th>Code</th>
                <th>Product Name</th>
                <th>Current Unit</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $p): ?>
            <tr>
                <td style="text-align: center;">
                    <input type="checkbox" name="selected_products[]" value="<?php echo $p['id']; ?>" class="product-checkbox">
                </td>
                <td><?php echo htmlspecialchars($p['code']); ?></td>
                <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
                <td><?php echo $p['unit']; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div style="margin-top: 20px;">
        <button type="submit" name="fix" value="1" class="btn btn-warning">
            🔧 Fix Min/Max Only (Keep Prices)
        </button>
        <a href="products.php" class="btn btn-secondary">Cancel</a>
    </div>
</form>

<script>
function toggleAll(source) {
    document.querySelectorAll('.product-checkbox').forEach(cb => cb.checked = source.checked);
}
</script>