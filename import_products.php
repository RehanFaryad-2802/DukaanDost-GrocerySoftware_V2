<?php
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Only admin and manager can import
if ($_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'manager') {
    header('Location: dashboard.php');
    exit;
}

$step = $_GET['step'] ?? 1;
$preview_data = [];
$import_stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
$headers = [];
$sample_rows = [];

// Step 1: Upload CSV
if ($step == 1 && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $handle = fopen($file['tmp_name'], 'r');
        if ($handle) {
            // Read headers
            $headers = fgetcsv($handle);
            
            // Read up to 5 sample rows for preview
            $row_count = 0;
            while (($row = fgetcsv($handle)) !== false && $row_count < 5) {
                $sample_rows[] = array_combine($headers, $row);
                $row_count++;
            }
            fclose($handle);
            
            // Store in session for later processing
            $_SESSION['import_file'] = $file['tmp_name'];
            $_SESSION['import_headers'] = $headers;
            
            $step = 2;
        } else {
            $error = "Could not read CSV file.";
        }
    } else {
        $error = "File upload error: " . $file['error'];
    }
}

// Step 2: Map columns and import
if ($step == 2 && isset($_POST['confirm_import'])) {
    $mapping = $_POST['mapping'] ?? [];
    $update_existing = isset($_POST['update_existing']);
    $skip_empty_name = isset($_POST['skip_empty_name']);
    
    if (isset($_SESSION['import_file']) && file_exists($_SESSION['import_file'])) {
        $handle = fopen($_SESSION['import_file'], 'r');
        if ($handle) {
            $headers = fgetcsv($handle);
            
            $pdo->beginTransaction();
            
            try {
                while (($row = fgetcsv($handle)) !== false) {
                    $data = array_combine($headers, $row);
                    
                    // Map columns to fields
                    $code = $mapping['code'] ? trim($data[$mapping['code']] ?? '') : '';
                    $name = $mapping['name'] ? trim($data[$mapping['name']] ?? '') : '';
                    $category = $mapping['category'] ? trim($data[$mapping['category']] ?? '') : '';
                    $unit = $mapping['unit'] ? trim($data[$mapping['unit']] ?? '') : 'Piece';
                    $description = $mapping['description'] ? trim($data[$mapping['description']] ?? '') : '';
                    $cost = $mapping['purchase_price'] ? floatval($data[$mapping['purchase_price']] ?? 0) : 0;
                    $retail = $mapping['retail_price'] ? floatval($data[$mapping['retail_price']] ?? 0) : 0;
                    $wholesale = $mapping['wholesale_price'] ? floatval($data[$mapping['wholesale_price']] ?? 0) : 0;
                    $stock = $mapping['stock'] ? floatval($data[$mapping['stock']] ?? 0) : 999999;
                    
                    // Skip if name is empty
                    if ($skip_empty_name && empty($name)) {
                        $import_stats['skipped']++;
                        continue;
                    }
                    
                    // Generate code if not provided
                    if (empty($code)) {
                        $code = 'PRD' . strtoupper(substr(uniqid(), -8));
                    }
                    
                    // Check if category exists, create if not
                    if (!empty($category)) {
                        $stmt = $pdo->prepare("INSERT IGNORE INTO categories (name) VALUES (?)");
                        $stmt->execute([$category]);
                    }
                    
                    // Check if product exists
                    $stmt = $pdo->prepare("SELECT id FROM products WHERE code = ? OR name = ?");
                    $stmt->execute([$code, $name]);
                    $existing = $stmt->fetch();
                    
                    if ($existing && $update_existing) {
                        // Update existing product
                        $stmt = $pdo->prepare("
                            UPDATE products SET 
                                name = ?, category = ?, unit = ?, description = ?,
                                current_stock = ?, purchase_price = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$name, $category, $unit, $description, $stock, $cost, $existing['id']]);
                        $product_id = $existing['id'];
                        
                        // Delete old pricing tiers
                        $stmt = $pdo->prepare("DELETE FROM pricing_tiers WHERE product_id = ?");
                        $stmt->execute([$product_id]);
                        
                        $import_stats['updated']++;
                    } elseif (!$existing) {
                        // Insert new product
                        $stmt = $pdo->prepare("
                            INSERT INTO products (code, name, description, category, unit, 
                                                current_stock, min_stock_alert, purchase_price, status) 
                            VALUES (?, ?, ?, ?, ?, ?, 10, ?, 'active')
                        ");
                        $stmt->execute([$code, $name, $description, $category, $unit, $stock, $cost]);
                        $product_id = $pdo->lastInsertId();
                        $import_stats['created']++;
                    } else {
                        $import_stats['skipped']++;
                        continue;
                    }
                    
                    // Add retail pricing
                    if ($retail > 0) {
                        $stmt = $pdo->prepare("
                            INSERT INTO pricing_tiers (product_id, customer_type, min_quantity, max_quantity, price_per_unit) 
                            VALUES (?, 'retail', 1, NULL, ?)
                        ");
                        $stmt->execute([$product_id, $retail]);
                    }
                    
                    // Add wholesale pricing
                    if ($wholesale > 0) {
                        $stmt = $pdo->prepare("
                            INSERT INTO pricing_tiers (product_id, customer_type, min_quantity, max_quantity, price_per_unit) 
                            VALUES (?, 'wholesale', 5, NULL, ?)
                        ");
                        $stmt->execute([$product_id, $wholesale]);
                    }
                }
                
                $pdo->commit();
                $step = 3;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $import_stats['errors'][] = $e->getMessage();
            }
            
            fclose($handle);
            unlink($_SESSION['import_file']);
            unset($_SESSION['import_file'], $_SESSION['import_headers']);
        }
    }
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-upload"></i> Import Products</h1>
    <a href="products.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Back to Products
    </a>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($step == 1): ?>
    <!-- Step 1: Upload CSV -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Step 1: Upload CSV File</h5>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" action="?step=1">
                <div class="mb-3">
                    <label>Select CSV File *</label>
                    <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                    <small class="text-muted">File should be UTF-8 encoded CSV</small>
                </div>
                
                <hr>
                <h6>Expected CSV Format:</h6>
                <pre class="bg-light p-3 rounded">Name,Code,Category,Unit,Description,Purchase Price,Retail Price,Wholesale Price,Stock
Sugar,SUG001,Groceries,kg,Premium Sugar,130,150,140,999999
Rice,RIC001,Groceries,kg,Basmati Rice,180,200,190,999999</pre>
                
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-arrow-right"></i> Upload & Continue
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($step == 2): ?>
    <!-- Step 2: Map Columns -->
    <div class="card">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0">Step 2: Map Columns & Import</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> 
                Found <?php echo count($sample_rows); ?> sample rows in your CSV.
            </div>
            
            <h6>Sample Data Preview:</h6>
            <div class="table-responsive mb-4" style="max-height: 200px;">
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <?php foreach ($headers as $header): ?>
                            <th><?php echo htmlspecialchars($header); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sample_rows as $row): ?>
                        <tr>
                            <?php foreach ($headers as $header): ?>
                            <td><?php echo htmlspecialchars(substr($row[$header] ?? '', 0, 50)); ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <form method="POST" action="?step=2">
                <h6>Map CSV Columns to Product Fields:</h6>
                <div class="row">
                    <?php
                    $fields = [
                        'name' => 'Product Name *',
                        'code' => 'Product Code',
                        'category' => 'Category',
                        'unit' => 'Unit',
                        'description' => 'Description',
                        'purchase_price' => 'Purchase Price',
                        'retail_price' => 'Retail Price',
                        'wholesale_price' => 'Wholesale Price',
                        'stock' => 'Initial Stock'
                    ];
                    
                    foreach ($fields as $key => $label):
                    ?>
                    <div class="col-md-4 mb-3">
                        <label><?php echo $label; ?></label>
                        <select name="mapping[<?php echo $key; ?>]" class="form-select">
                            <option value="">-- Ignore --</option>
                            <?php foreach ($headers as $header): ?>
                            <option value="<?php echo htmlspecialchars($header); ?>" 
                                <?php echo (stripos($header, $key) !== false || stripos($header, str_replace('_', ' ', $key)) !== false) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($header); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <hr>
                <h6>Import Options:</h6>
                <div class="mb-3">
                    <label class="form-check">
                        <input type="checkbox" name="update_existing" value="1" class="form-check-input" checked>
                        <span class="form-check-label">Update existing products (matched by code or name)</span>
                    </label>
                </div>
                <div class="mb-3">
                    <label class="form-check">
                        <input type="checkbox" name="skip_empty_name" value="1" class="form-check-input" checked>
                        <span class="form-check-label">Skip rows with empty product name</span>
                    </label>
                </div>
                
                <div class="mt-4">
                    <button type="submit" name="confirm_import" class="btn btn-success btn-lg">
                        <i class="bi bi-check-circle"></i> Confirm & Import Products
                    </button>
                    <a href="import_products.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($step == 3): ?>
    <!-- Step 3: Import Results -->
    <div class="card">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="bi bi-check-circle"></i> Import Complete</h5>
        </div>
        <div class="card-body">
            <div class="row text-center">
                <div class="col-md-4">
                    <div class="big-number text-success"><?php echo $import_stats['created']; ?></div>
                    <p>Products Created</p>
                </div>
                <div class="col-md-4">
                    <div class="big-number text-warning"><?php echo $import_stats['updated']; ?></div>
                    <p>Products Updated</p>
                </div>
                <div class="col-md-4">
                    <div class="big-number text-secondary"><?php echo $import_stats['skipped']; ?></div>
                    <p>Skipped</p>
                </div>
            </div>
            
            <?php if (!empty($import_stats['errors'])): ?>
            <div class="alert alert-danger mt-3">
                <strong>Errors:</strong>
                <ul>
                    <?php foreach ($import_stats['errors'] as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <div class="mt-4">
                <a href="products.php" class="btn btn-primary">
                    <i class="bi bi-box"></i> View Products
                </a>
                <a href="import_products.php" class="btn btn-outline-primary">
                    <i class="bi bi-upload"></i> Import More
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
.big-number { font-size: 48px; font-weight: bold; }
</style>

<?php require_once 'includes/footer.php'; ?>