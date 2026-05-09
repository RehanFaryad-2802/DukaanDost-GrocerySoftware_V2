<?php
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

if ($_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'manager') {
    header('Location: dashboard.php');
    exit;
}

// Handle delete package
if (isset($_GET['delete_package'])) {
    $package_id = intval($_GET['delete_package']);
    try {
        $stmt = $pdo->prepare("DELETE FROM product_packages WHERE id = ?");
        $stmt->execute([$package_id]);
        $success = "Package deleted successfully!";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get products that have packages
$stmt = $pdo->query("
    SELECT DISTINCT p.*, COUNT(pp.id) as package_count
    FROM products p
    JOIN product_packages pp ON p.id = pp.product_id
    WHERE p.is_hidden = 0
    GROUP BY p.id
    ORDER BY p.name
");
$products = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-boxes"></i> Product Packages</h1>
    <a href="products.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Back to Products
    </a>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i> <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Products with Packages (<?php echo count($products); ?>)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Code</th>
                        <th>Unit</th>
                        <th>Packages</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($products) == 0): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">
                                No products with packages found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td>
                                    <strong dir="rtl"><?php echo htmlspecialchars($product['name']); ?></strong>
                                    <?php if (!empty($product['english_name'])): ?>
                                        <br><small
                                            class="text-muted"><?php echo htmlspecialchars($product['english_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($product['code']); ?></td>
                                <td><?php echo htmlspecialchars($product['unit']); ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo $product['package_count']; ?> packages</span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary"
                                        onclick="managePackages(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name'], ENT_QUOTES); ?>')">
                                        <i class="bi bi-gear"></i> Manage
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/product_packages.php'; ?>

<?php require_once 'includes/footer.php'; ?>

</div>
</body>

</html>