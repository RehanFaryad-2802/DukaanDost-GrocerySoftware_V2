<?php
// Handle add category
if (isset($_POST['add_category'])) {
    $name = trim($_POST['category_name']);
    if (!empty($name)) {
        $success = "Category '$name' added!";
    }
}

// Handle rename category
if (isset($_POST['rename_category'])) {
    $old_name = $_POST['old_name'];
    $new_name = trim($_POST['new_name']);
    
    if (!empty($old_name) && !empty($new_name)) {
        $stmt = $pdo->prepare("UPDATE products SET category = ? WHERE category = ?");
        $stmt->execute([$new_name, $old_name]);
        $success = "Category renamed from '$old_name' to '$new_name'!";
    }
}

// Handle delete category (set to NULL)
if (isset($_GET['delete_category'])) {
    $cat = $_GET['delete_category'];
    $stmt = $pdo->prepare("UPDATE products SET category = NULL WHERE category = ?");
    $stmt->execute([$cat]);
    $success = "Category '$cat' removed from all products!";
}

// Get all unique categories with stats
$stmt = $pdo->query("
    SELECT 
        category,
        COUNT(*) as product_count,
        SUM(current_stock) as total_stock,
        SUM(current_stock * purchase_price) as stock_value
    FROM products 
    WHERE category IS NOT NULL AND category != ''
    GROUP BY category 
    ORDER BY category
");
$categories = $stmt->fetchAll();
?>
<?php
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'config/functions.php';

// Handle Add Product
if (isset($_POST['add_product'])) {
    $code = $_POST['code'];
    $name = $_POST['name'];
    $category = $_POST['category'] ?: null;
    $unit = $_POST['unit'];
    $description = $_POST['description'] ?: null;
    $stock = floatval($_POST['current_stock'] ?? 0);
    $min_alert = floatval($_POST['min_stock_alert'] ?? 10);
    $cost = floatval($_POST['purchase_price'] ?? 0);
    $retail = floatval($_POST['retail_price'] ?? 0);
    $wholesale = floatval($_POST['wholesale_price'] ?? 0);
    $wholesale_min = floatval($_POST['wholesale_min_qty'] ?? 5);
    
    try {
        $pdo->beginTransaction();
        
        // Insert product
        $stmt = $pdo->prepare("
            INSERT INTO products (code, name, description, unit, category, current_stock, min_stock_alert, purchase_price, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([$code, $name, $description, $unit, $category, $stock, $min_alert, $cost]);
        $product_id = $pdo->lastInsertId();
        
        // Add retail tier
        if ($retail > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO pricing_tiers (product_id, customer_type, min_quantity, max_quantity, price_per_unit) 
                VALUES (?, 'retail', 1, NULL, ?)
            ");
            $stmt->execute([$product_id, $retail]);
        }
        
        // Add wholesale tier
        if ($wholesale > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO pricing_tiers (product_id, customer_type, min_quantity, max_quantity, price_per_unit) 
                VALUES (?, 'wholesale', ?, NULL, ?)
            ");
            $stmt->execute([$product_id, $wholesale_min, $wholesale]);
        }
        
        $pdo->commit();
        $success = "Product '$name' added successfully!";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error adding product: " . $e->getMessage();
    }
}

// Handle single delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM pricing_tiers WHERE product_id = ?");
    $stmt->execute([$id]);
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $success = "Product deleted successfully!";
}

// Check if we're viewing categories
if (isset($_GET['view']) && $_GET['view'] == 'categories') {
    if (file_exists('categories_content.php')) {
        include 'categories_content.php';
    } else {
        echo "<div class='alert alert-danger'>categories_content.php not found!</div>";
    }
    require_once 'includes/footer.php';
    exit;
}

// Rest of your products.php code...
?>
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-folder"></i> Category Management
    </h1>
    <div>
        <a href="products.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back to Products
        </a>
        <button type="button" class="btn btn-primary" onclick="openAddProductModal()">
    <i class="bi bi-plus-circle"></i> Add New Product
</button>
    </div>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">All Categories</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Category Name</th>
                            <th>Products</th>
                            <th>Total Stock</th>
                            <th>Stock Value</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($cat['category']); ?></strong>
                            </td>
                            <td>
                                <span class="badge bg-info"><?php echo $cat['product_count']; ?> products</span>
                            </td>
                            <td>
                                <?php echo number_format($cat['total_stock'] ?? 0, 2); ?>
                            </td>
                            <td>
                                <strong>Rs. <?php echo number_format($cat['stock_value'] ?? 0, 2); ?></strong>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="products.php?category=<?php echo urlencode($cat['category']); ?>" 
                                       class="btn btn-outline-primary" title="View Products">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <button class="btn btn-outline-warning" 
                                            onclick="renameCategory('<?php echo htmlspecialchars($cat['category'], ENT_QUOTES); ?>')"
                                            title="Rename">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <a href="?view=categories&delete_category=<?php echo urlencode($cat['category']); ?>" 
                                       class="btn btn-outline-danger"
                                       onclick="return confirm('Remove category \'<?php echo htmlspecialchars($cat['category'], ENT_QUOTES); ?>\' from all products?')"
                                       title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (count($categories) == 0): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">
                                <i class="bi bi-folder-x" style="font-size: 2rem;"></i>
                                <p class="mt-2">No categories yet.</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                    Add Your First Category
                                </button>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Uncategorized Products Card -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-warning">
                <h5 class="mb-0">Uncategorized Products</h5>
            </div>
            <div class="card-body p-0">
                <?php
                $stmt = $pdo->query("
                    SELECT id, name, code, current_stock 
                    FROM products 
                    WHERE category IS NULL OR category = ''
                    LIMIT 20
                ");
                $uncategorized = $stmt->fetchAll();
                ?>
                
                <?php if (count($uncategorized) > 0): ?>
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Code</th>
                            <th>Stock</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($uncategorized as $p): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($p['name']); ?></td>
                            <td><?php echo $p['code']; ?></td>
                            <td><?php echo $p['current_stock']; ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="assignCategory(<?php echo $p['id']; ?>)">
                                    Assign Category
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="text-muted p-3 mb-0">All products have categories assigned!</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Category Name</label>
                        <input type="text" name="category_name" class="form-control" required autofocus>
                    </div>
                    <p class="text-muted small">
                        <i class="bi bi-info-circle"></i> 
                        Category will be available for selection when adding/editing products.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Rename Category Modal -->
<div class="modal fade" id="renameCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="old_name" id="old_category_name">
                <div class="modal-header">
                    <h5 class="modal-title">Rename Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>New Category Name</label>
                        <input type="text" name="new_name" id="new_category_name" class="form-control" required>
                    </div>
                    <p class="text-muted small">
                        This will update all products in this category.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="rename_category" class="btn btn-warning">Rename</button>
                </div>
            </form>
        </div>
    </div>
    <!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle"></i> Add New Product
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Product Code *</label>
                            <input type="text" name="code" class="form-control" required>
                            <small class="text-muted">Barcode or unique identifier</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Product Name *</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Category</label>
                            <select name="category" class="form-select">
                                <option value="">Select Category</option>
                                <?php
                                $cats = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetchAll();
                                foreach ($cats as $cat) {
                                    echo "<option value='" . htmlspecialchars($cat['category']) . "'>" . htmlspecialchars($cat['category']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Unit *</label>
                            <select name="unit" class="form-select" required>
                                <option value="kg">Kilogram (kg)</option>
                                <option value="g">Gram (g)</option>
                                <option value="liter">Liter</option>
                                <option value="ml">Milliliter (ml)</option>
                                <option value="piece">Piece</option>
                                <option value="packet">Packet</option>
                                <option value="dozen">Dozen</option>
                                <option value="sack">Sack/Bag</option>
                            </select>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label>Initial Stock</label>
                            <input type="number" name="current_stock" class="form-control" step="0.001" value="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label>Min Stock Alert</label>
                            <input type="number" name="min_stock_alert" class="form-control" step="0.001" value="10">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label>Purchase Price (Cost) *</label>
                            <input type="number" name="purchase_price" class="form-control" step="0.01" value="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Retail Price (per unit) *</label>
                            <input type="number" name="retail_price" class="form-control" step="0.01" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Wholesale Price (per unit)</label>
                            <input type="number" name="wholesale_price" class="form-control" step="0.01" value="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Min Wholesale Quantity</label>
                            <input type="number" name="wholesale_min_qty" class="form-control" step="0.001" value="5">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_product" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Product
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
</div>

<script>
function renameCategory(oldName) {
    document.getElementById('old_category_name').value = oldName;
    document.getElementById('new_category_name').value = oldName;
    new bootstrap.Modal(document.getElementById('renameCategoryModal')).show();
}

function assignCategory(productId) {
    // Open a modal to select category for this product
    alert('Assign category to product ID: ' + productId);
    // You can implement a modal with category dropdown here
}
</script>