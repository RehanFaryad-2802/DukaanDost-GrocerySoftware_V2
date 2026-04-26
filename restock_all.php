<?php
require_once 'config/database.php';
checkAuth();

if ($_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'manager') {
    die("Only admin and manager can perform bulk restock.");
}

$restock_value = 999999;

if (isset($_POST['confirm_restock'])) {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE products 
            SET current_stock = ? 
            WHERE status = 'active'
        ");
        $stmt->execute([$restock_value]);

        $affected = $stmt->rowCount();

        // Record stock movements
        $stmt = $pdo->prepare("
            INSERT INTO stock_movements (product_id, movement_type, quantity, reference_no, notes, created_by)
            SELECT id, 'adjustment', ?, 'RESTOCK-ALL-MAX', 'Bulk restock ALL products to 999,999', ?
            FROM products 
            WHERE status = 'active'
        ");
        $stmt->execute([$restock_value, $_SESSION['user_id']]);

        $pdo->commit();

        $success = "✅ Successfully restocked ALL $affected products to " . number_format($restock_value) . " units!";

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "❌ Error: " . $e->getMessage();
    }
}

// Get product statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_products,
        SUM(current_stock) as total_stock,
        COUNT(CASE WHEN current_stock < 100 THEN 1 END) as low_stock_count
    FROM products 
    WHERE status = 'active'
");
$stats = $stmt->fetch();

// Get sample of products
$stmt = $pdo->query("
    SELECT id, code, name, current_stock, unit
    FROM products 
    WHERE status = 'active'
    ORDER BY name
    LIMIT 10
");
$sample_products = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restock All to 999,999 - Grocery Billing</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/bootstrap-icons.css">
    <style>
        body {
            background: #f8f9fa;
            padding: 30px;
        }

        .container {
            max-width: 800px;
        }

        .card {
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .danger-zone {
            border: 2px solid #dc3545;
            background: #fff5f5;
        }

        .big-number {
            font-size: 48px;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-box-seam text-success"></i> Restock All Products</h1>
            <div>
                <a href="products.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Products
                </a>
                <a href="dashboard.php" class="btn btn-outline-primary">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </div>
        </div>

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

        <!-- Stats Card -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-4">
                        <h6 class="text-muted">Total Products</h6>
                        <div class="big-number text-primary"><?php echo number_format($stats['total_products']); ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <h6 class="text-muted">Current Total Stock</h6>
                        <div class="big-number text-info"><?php echo number_format($stats['total_stock'] ?? 0); ?></div>
                    </div>
                    <div class="col-md-4">
                        <h6 class="text-muted">Target Stock Per Product</h6>
                        <div class="big-number text-success"><?php echo number_format($restock_value); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sample Products -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-list"></i> Sample Products (showing 10)</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Current Stock</th>
                            <th>Unit</th>
                            <th>After Restock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sample_products as $p): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($p['code']); ?></td>
                                <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
                                <td>
                                    <span class="badge bg-<?php echo $p['current_stock'] < 100 ? 'warning' : 'success'; ?>">
                                        <?php echo number_format($p['current_stock']); ?>
                                    </span>
                                </td>
                                <td><?php echo $p['unit']; ?></td>
                                <td>
                                    <span class="badge bg-success">999,999</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($stats['total_products'] > 10): ?>
                <div class="card-footer text-muted">
                    <i class="bi bi-info-circle"></i> And <?php echo $stats['total_products'] - 10; ?> more products...
                </div>
            <?php endif; ?>
        </div>

        <!-- Danger Zone -->
        <div class="card danger-zone">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> DANGER ZONE</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-circle"></i>
                    <strong>Warning:</strong> This will set <strong>ALL
                        <?php echo number_format($stats['total_products']); ?> products</strong>
                    to exactly <strong><?php echo number_format($restock_value); ?></strong> units, regardless of their
                    current stock!
                </div>

                <form method="POST"
                    onsubmit="return confirm('ARE YOU ABSOLUTELY SURE?\n\nThis will set ALL <?php echo $stats['total_products']; ?> products to 999,999 units!\n\nType YES to confirm.');">
                    <div class="mb-3">
                        <label class="form-label">Type <strong>YES</strong> to confirm</label>
                        <input type="text" name="confirm_text" id="confirm_text" class="form-control" placeholder="YES"
                            required oninput="document.getElementById('restock_btn').disabled = this.value !== 'YES'">
                    </div>

                    <div class="d-grid">
                        <button type="submit" name="confirm_restock" id="restock_btn" class="btn btn-danger btn-lg"
                            disabled>
                            <i class="bi bi-box-seam"></i> RESTOCK ALL TO 999,999
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="text-center mt-4 text-muted">
            <small style="color:red;">Use with caution - This action cannot be undone!</small>
        </div>
    </div>

    <script>
        // Enable button only when user types YES
        document.getElementById('confirm_text').addEventListener('input', function () {
            document.getElementById('restock_btn').disabled = this.value !== 'YES';
        });
    </script>
</body>

</html>