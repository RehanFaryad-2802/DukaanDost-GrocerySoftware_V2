<?php
require_once 'config/database.php';
checkAuth();

// Only admin can restock
if ($_SESSION['user_role'] != 'admin') {
    die("Only admin can perform bulk restock.");
}

$restock_value = 9999999;

// Handle the restock action
if (isset($_POST['confirm_restock'])) {
    try {
        $pdo->beginTransaction();
        
        // Update all products with 0 or negative stock
        $stmt = $pdo->prepare("
            UPDATE products 
            SET current_stock = ? 
            WHERE current_stock <= 0 AND status = 'active'
        ");
        $stmt->execute([$restock_value]);
        
        $affected = $stmt->rowCount();
        
        // Record stock movements
        $stmt = $pdo->prepare("
            INSERT INTO stock_movements (product_id, movement_type, quantity, reference_no, notes, created_by)
            SELECT id, 'adjustment', ?, 'RESTOCK-BULK', 'Bulk restock to 9,999,999', ?
            FROM products 
            WHERE current_stock <= 0 AND status = 'active'
        ");
        $stmt->execute([$restock_value, $_SESSION['user_id']]);
        
        $pdo->commit();
        
        $success = "✅ Successfully restocked $affected products to " . number_format($restock_value) . " units!";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "❌ Error: " . $e->getMessage();
    }
}

// Count products with 0 or negative stock
$stmt = $pdo->query("
    SELECT COUNT(*) as count, 
           SUM(CASE WHEN current_stock = 0 THEN 1 ELSE 0 END) as zero_stock,
           SUM(CASE WHEN current_stock < 0 THEN 1 ELSE 0 END) as negative_stock
    FROM products 
    WHERE current_stock <= 0 AND status = 'active'
");
$stats = $stmt->fetch();

// Get list of products that will be restocked
$stmt = $pdo->query("
    SELECT id, code, name, current_stock, unit
    FROM products 
    WHERE current_stock <= 0 AND status = 'active'
    ORDER BY name
    LIMIT 50
");
$products = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Restock - Grocery Billing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background: #f8f9fa;
            padding: 30px;
        }
        .container {
            max-width: 900px;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .warning-text {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h2 class="mb-0">
                    <i class="bi bi-box-seam"></i> Bulk Restock Tool
                </h2>
            </div>
            <div class="card-body">
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Stats -->
                <div class="stats-card">
                    <div class="row text-center">
                        <div class="col-md-4">
                            <h3><?php echo $stats['count']; ?></h3>
                            <p>Products with 0 or negative stock</p>
                        </div>
                        <div class="col-md-4">
                            <h3><?php echo $stats['zero_stock']; ?></h3>
                            <p>Zero Stock</p>
                        </div>
                        <div class="col-md-4">
                            <h3><?php echo $stats['negative_stock']; ?></h3>
                            <p>Negative Stock</p>
                        </div>
                    </div>
                </div>
                
                <?php if ($stats['count'] > 0): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Warning:</strong> This will set stock to <strong><?php echo number_format($restock_value); ?></strong> for ALL products with 0 or negative stock!
                    </div>
                    
                    <!-- Products to be restocked -->
                    <h5 class="mt-4">Products to be Restocked (showing first 50):</h5>
                    <div style="max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px;">
                        <table class="table table-sm table-striped mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Current Stock</th>
                                    <th>Unit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $p): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($p['code']); ?></td>
                                    <td><?php echo htmlspecialchars($p['name']); ?></td>
                                    <td class="warning-text"><?php echo $p['current_stock']; ?></td>
                                    <td><?php echo $p['unit']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (count($products) == 0): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">No products need restocking!</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($stats['count'] > 50): ?>
                        <p class="text-muted mt-2">... and <?php echo $stats['count'] - 50; ?> more products</p>
                    <?php endif; ?>
                    
                    <!-- Confirm Form -->
                    <form method="POST" class="mt-4" onsubmit="return confirm('Are you SURE you want to restock <?php echo $stats['count']; ?> products to <?php echo number_format($restock_value); ?> units each?');">
                        <div class="d-grid gap-2">
                            <button type="submit" name="confirm_restock" class="btn btn-success btn-lg">
                                <i class="bi bi-check-circle"></i> Confirm Restock (<?php echo $stats['count']; ?> products)
                            </button>
                        </div>
                    </form>
                    
                <?php else: ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle-fill"></i>
                        <strong>All good!</strong> No products have 0 or negative stock.
                    </div>
                <?php endif; ?>
                
                <div class="mt-4">
                    <a href="products.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Products
                    </a>
                    <a href="dashboard.php" class="btn btn-outline-primary">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-3 text-muted">
            <small>Bulk Restock Tool v1.0 | Use with caution</small>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>