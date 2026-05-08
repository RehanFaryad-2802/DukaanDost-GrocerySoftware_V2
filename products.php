<?php
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'config/functions.php';

if (isset($_POST['add_product'])) {
    $code = trim($_POST['code']);
    $name = trim($_POST['name']);
    $category = $_POST['category'] ?: null;
    $description = $_POST['description'] ?: null;
    $unit = trim($_POST['unit'] ?: 'Piece');
    $stock = floatval($_POST['current_stock'] ?? 0);
    $min_alert = floatval($_POST['min_stock_alert'] ?? 10);
    $cost = floatval($_POST['purchase_price'] ?? 0);

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT id FROM products WHERE code = ?");
        $stmt->execute([$code]);
        if ($stmt->fetch()) {
            throw new Exception("Product code already exists!");
        }

        // Insert product
        $english_name = trim($_POST['english_name'] ?? '') ?: null;
        $stmt = $pdo->prepare("
            INSERT INTO products (code, name, english_name, description, category, unit, 
                                current_stock, min_stock_alert, purchase_price, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([$code, $name, $english_name, $description, $category, $unit, $stock, $min_alert, $cost]);
        $product_id = $pdo->lastInsertId();

        // Save packages if any
        if (isset($_POST['package_name']) && is_array($_POST['package_name'])) {
            $package_names = $_POST['package_name'];
            $package_multipliers = $_POST['package_multiplier'];

            for ($i = 0; $i < count($package_names); $i++) {
                if (!empty($package_names[$i]) && !empty($package_multipliers[$i])) {
                    $stmt = $pdo->prepare("
                        INSERT INTO product_packages (product_id, package_name, multiplier)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$product_id, $package_names[$i], $package_multipliers[$i]]);
                }
            }
        }

        $pdo->commit();
        $success = "Product '$name' added successfully!";

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error adding product: " . $e->getMessage();
    }
}

if (isset($_POST['bulk_delete']) && isset($_POST['selected_products'])) {
    $ids = array_map('intval', $_POST['selected_products']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("DELETE FROM invoice_items WHERE product_id IN ($placeholders)");
        $stmt->execute($ids);

        $stmt = $pdo->prepare("DELETE FROM stock_movements WHERE product_id IN ($placeholders)");
        $stmt->execute($ids);

        $stmt = $pdo->prepare("DELETE FROM pricing_tiers WHERE product_id IN ($placeholders)");
        $stmt->execute($ids);

        try {
            $stmt = $pdo->prepare("DELETE FROM unit_conversions WHERE product_id IN ($placeholders)");
            $stmt->execute($ids);
        } catch (Exception $e) {
        }

        $stmt = $pdo->prepare("DELETE FROM products WHERE id IN ($placeholders)");
        $stmt->execute($ids);

        $pdo->commit();
        $success = count($ids) . " products deleted permanently!";

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("DELETE FROM invoice_items WHERE product_id = ?");
        $stmt->execute([$id]);

        $stmt = $pdo->prepare("DELETE FROM stock_movements WHERE product_id = ?");
        $stmt->execute([$id]);

        $stmt = $pdo->prepare("DELETE FROM pricing_tiers WHERE product_id = ?");
        $stmt->execute([$id]);

        try {
            $stmt = $pdo->prepare("DELETE FROM unit_conversions WHERE product_id = ?");
            $stmt->execute([$id]);
        } catch (Exception $e) {
        }

        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);

        $pdo->commit();
        $success = "Product deleted successfully!";

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

$category_filter = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

$sql = "SELECT * FROM products WHERE is_hidden = 0 AND 1=1";
$params = [];

if ($category_filter) {
    $sql .= " AND category = ?";
    $params[] = $category_filter;
}

if ($search) {
    $sql .= " AND (name LIKE ? OR code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY category, name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$cats = $pdo->query("SELECT name as category FROM categories ORDER BY name")->fetchAll();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Product Management</h1>
    <div>
        <a href="categories.php" class="btn btn-info me-2"><i class="bi bi-folder"></i> Manage Categories</a>
        <a href="units.php" class="btn btn-secondary me-2"><i class="bi bi-rulers"></i> Manage Units</a>
        <button type="button" class="btn btn-primary" onclick="openAddProductModal()">
            <i class="bi bi-plus-circle"></i> Add New Product
        </button>
    </div>
</div>

<!-- Statistics Cards -->
<?php include 'includes/product_stats.php'; ?>

<?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Filters and Search -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label>Filter by Category</label>
                <select name="category" class="form-select" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php foreach ($cats as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['category']) ?>" <?= $category_filter == $cat['category'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['category']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label>Search Product</label>
                <input dir="rtl" type="text" name="search" class="form-control voice-input" placeholder="نام یا کوڈ۔۔۔"
                    value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Search
                </button>
            </div>
            <div class="col-md-3">
                <label>&nbsp;</label>
                <div>
                    <a href="products.php" class="btn btn-outline-secondary">Reset</a>
                    <button type="button" class="btn btn-danger" onclick="submitBulkDelete()" id="bulkDeleteBtn"
                        style="display: none;">
                        <i class="bi bi-trash"></i> Delete Selected (<span id="selectedCount">0</span>)
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>


<!-- Products Table -->
<?php include 'includes/product_table.php'; ?>

<!-- Modals Container -->
<div id="addProductModalContainer"></div>

<!-- Pricing Modal -->
<div class="modal fade" id="pricingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title text-white">Manage Pricing Tiers</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="pricingModalBody"></div>
        </div>
    </div>
</div>

<?php
// Include all JavaScript and modal logic
include 'includes/product_modals.php';
include 'includes/product_packages.php';
include 'includes/product_pricing_js.php';
?>

<script src="assets/js/voice_input.js"></script>

<?php require_once 'includes/footer.php'; ?>