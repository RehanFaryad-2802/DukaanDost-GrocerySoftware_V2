<?php
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'config/functions.php';

// Handle Add Product
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

        // Check if code already exists
        $stmt = $pdo->prepare("SELECT id FROM products WHERE code = ?");
        $stmt->execute([$code]);
        if ($stmt->fetch()) {
            throw new Exception("Product code already exists!");
        }

        // Insert product
        $stmt = $pdo->prepare("
            INSERT INTO products (code, name, description, category, unit, 
                                current_stock, min_stock_alert, purchase_price, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([$code, $name, $description, $category, $unit, $stock, $min_alert, $cost]);
        $product_id = $pdo->lastInsertId();

        // ========== ADD PACKAGE SAVING CODE HERE ==========
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
        // ========== END PACKAGE SAVING CODE ==========

        $pdo->commit();
        $success = "Product '$name' added successfully!";

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error adding product: " . $e->getMessage();
    }
}

// Handle bulk delete
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
            // Table might not exist
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

        // Delete from invoice_items
        $stmt = $pdo->prepare("DELETE FROM invoice_items WHERE product_id = ?");
        $stmt->execute([$id]);

        // Delete from stock_movements
        $stmt = $pdo->prepare("DELETE FROM stock_movements WHERE product_id = ?");
        $stmt->execute([$id]);

        // Delete from pricing_tiers
        $stmt = $pdo->prepare("DELETE FROM pricing_tiers WHERE product_id = ?");
        $stmt->execute([$id]);

        // Delete from unit_conversions if exists
        try {
            $stmt = $pdo->prepare("DELETE FROM unit_conversions WHERE product_id = ?");
            $stmt->execute([$id]);
        } catch (Exception $e) {
            // Table might not exist
        }

        // Finally delete the product
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);

        $pdo->commit();
        $success = "Product deleted successfully!";

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// Handle category filter
$category_filter = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

$sql = "SELECT * FROM products WHERE 1=1";
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
        <a href="categories.php" class="btn btn-info me-2">
            <i class="bi bi-folder"></i> Manage Categories
        </a>
        <a href="units.php" class="btn btn-secondary me-2">
            <i class="bi bi-rulers"></i> Manage Units
        </a>
        <button type="button" class="btn btn-primary" onclick="openAddProductModal()">
            <i class="bi bi-plus-circle"></i> Add New Product
        </button>
    </div>
</div>
<!-- Statistics Cards - Compact -->
<div class="row">
    <div class="col-md-4">
        <div class="card border-primary">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-box text-primary"></i> Total Products</span>
                    <span class="badge bg-primary fs-6">
                        <?php
                        $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'");
                        echo $stmt->fetchColumn();
                        ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-success">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-tags text-success"></i> Total Categories</span>
                    <span class="badge bg-success fs-6">
                        <?php
                        $stmt = $pdo->query("SELECT COUNT(*) FROM categories");
                        echo $stmt->fetchColumn();
                        ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-info">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-rulers text-info"></i> Total Units</span>
                    <span class="badge bg-info fs-6">
                        <?php
                        try {
                            $stmt = $pdo->query("SELECT COUNT(*) FROM units");
                            echo $stmt->fetchColumn();
                        } catch (Exception $e) {
                            echo "7";
                        }
                        ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
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

<!-- Filters and Search -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label>Filter by Category</label>
                <select name="category" class="form-select" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php foreach ($cats as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo $category_filter == $cat['category'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['category']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label>Search Product</label>
                <input dir="rtl" type="text" name="search" class="form-control" placeholder="نام یا کوڈ۔۔۔"
                    value="<?php echo htmlspecialchars($search); ?>">
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
<style>
    .table thead th {
        background-color: #000000 !important;
        color: #ffffff !important;
        font-weight: 600;
    }
</style>
<!-- Products Table -->
<form id="bulkDeleteForm" method="POST" onsubmit="return confirm('Delete selected products? This cannot be undone!');">
    <input type="hidden" name="bulk_delete" value="1">

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th width="40"><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)"></th>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th>Stock</th>
                        <th>Cost</th>
                        <th>Retail</th>
                        <th>Wholesale</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product):
                        // Get retail price - find the lowest min_quantity retail tier
                        $stmt = $pdo->prepare("
                            SELECT price_per_unit FROM pricing_tiers 
                            WHERE product_id = ? AND customer_type = 'retail' 
                            ORDER BY min_quantity ASC 
                            LIMIT 1
                        ");
                        $stmt->execute([$product['id']]);
                        $retail = $stmt->fetchColumn() ?: 0;

                        $stmt = $pdo->prepare("
                            SELECT price_per_unit FROM pricing_tiers 
                            WHERE product_id = ? AND customer_type = 'wholesale' 
                            ORDER BY min_quantity ASC 
                            LIMIT 1
                        ");
                        $stmt->execute([$product['id']]);
                        $wholesale = $stmt->fetchColumn() ?: 0;

                        $stock_class = $product['current_stock'] <= $product['min_stock_alert'] ? 'danger' : 'success';
                        ?>
                        <tr>
                            <td><input type="checkbox" name="selected_products[]" value="<?php echo $product['id']; ?>"
                                    class="product-checkbox" onchange="updateBulkDeleteBtn()"></td>
                            <td><small><?php echo htmlspecialchars($product['code']); ?></small></td>
                            <td dir="rtl"><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($product['category'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($product['unit'] ?: 'Piece'); ?></td>
                            <td><span
                                    class="badge bg-<?php echo $stock_class; ?>"><?php echo $product['current_stock']; ?></span>
                            </td>
                            <td>Rs. <?php echo number_format($product['purchase_price'] ?? 0, 0); ?></td>
                            <td>Rs. <?php echo number_format($retail, 0); ?></td>
                            <td>Rs. <?php echo number_format($wholesale, 0); ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <?php if ($_SESSION['user_role'] == 'admin' || $_SESSION['user_role'] == 'manager'): ?>
                                        <button type="button" class="btn btn-outline-primary"
                                            onclick="editProduct(<?php echo $product['id']; ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-success"
                                            onclick="managePricing(<?php echo $product['id']; ?>)">
                                            <i class="bi bi-tag"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary"
                                            onclick="managePackages(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name'], ENT_QUOTES); ?>')"
                                            title="Packages">
                                            <i class="bi bi-boxes"></i>
                                        </button>
                                        <a href="products.php?delete=<?php echo $product['id']; ?>"
                                            class="btn btn-outline-danger" onclick="return confirm('Delete this product?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">View Only</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (count($products) == 0): ?>
                        <tr>
                            <td colspan="10" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                                <p class="mt-2">No products found.</p>
                                <button type="button" class="btn btn-primary" onclick="openAddProductModal()">
                                    <i class="bi bi-plus-circle"></i> Add Your First Product
                                </button>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>

<!-- Add Product Modal - SIMPLE -->
<div id="addProductModalContainer"></div>

<!-- Pricing Modal -->
<div class="modal fade" id="pricingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Manage Pricing Tiers</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="pricingModalBody"></div>
        </div>
    </div>
</div>
<script>
    async function openAddProductModal() {
        const container = document.getElementById('addProductModalContainer');

        let unitsHtml = '';
        try {
            const response = await fetch('api/get_units.php');
            const units = await response.json();
            unitsHtml = units.map(u => `<option value="${u.symbol}">${u.name}</option>`).join('');
        } catch (e) {
            unitsHtml = `
            <option value="Piece">Piece</option>
            <option value="kg">Kilogram (kg)</option>
            <option value="g">Gram (g)</option>
            <option value="liter">Liter</option>
            <option value="ml">Milliliter (ml)</option>
            <option value="packet">Packet</option>
            <option value="dozen">Dozen</option>
            <option value="box">Box</option>
        `;
        }

        container.innerHTML = `
        <div class="modal fade" id="addProductModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add New Product</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label>Product Code *</label>
                                <div class="input-group">
                                    <input type="text" name="code" id="productCodeInput" class="form-control" required>
                                    <button type="button" class="btn btn-outline-secondary" onclick="generateProductCode()">
                                        <i class="bi bi-arrow-repeat"></i> Generate
                                    </button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label>Product Name *</label>
                                <input dir="rtl" type="text" name="name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label>Category</label>
                                <select name="category" class="form-select">
                                    <option value="">Select Category</option>
                                    ${getCategorySelectOptions('')}
                                </select>
                            </div>
                            <div class="mb-3">
                                <label>Unit</label>
                                <select name="unit" class="form-select">
                                    ${unitsHtml}
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="bi bi-boxes"></i> Quick Packages (Optional)
                                </label>
                                <div id="packagesContainer">
                                    <!-- Packages added here -->
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addPackageRow()">
                                    <i class="bi bi-plus-circle"></i> Add Package
                                </button>
                                <small class="text-muted d-block mt-1">
                                    e.g., Dozen = 12, Tray = 30, Box = 50
                                </small>
                            </div>
                            <div class="mb-3">
                                <label>Description</label>
                                <input type="text" name="description" class="form-control">
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label>Initial Stock</label>
                                    <input value="999999" type="number" name="current_stock" class="form-control" step="0.001">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>Min Stock Alert</label>
                                    <input type="number" name="min_stock_alert" class="form-control" step="0.001" value="10">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label>Purchase Price (Cost) *</label>
                                <input value="0" type="number" name="purchase_price" class="form-control" step="0.01" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="add_product" class="btn btn-primary">Save Product</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `;

        new bootstrap.Modal(document.getElementById('addProductModal')).show();
    }
    // Generate unique product code
    function generateProductCode() {
        const timestamp = Date.now().toString().slice(-8);
        const random = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
        const code = `PRD${timestamp}${random}`;
        document.getElementById('productCodeInput').value = code;
    }

    // Generate for edit modal
    function generateCodeForEdit() {
        const timestamp = Date.now().toString().slice(-8);
        const random = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
        const code = `PRD${timestamp}${random}`;
        document.querySelector('#editProductModal input[name="code"]').value = code;
    }

    // Fetch units and open modal
    

    function getCategorySelectOptions(selectedCategory = '') {
        const categories = <?php
        $stmt = $pdo->query("SELECT name FROM categories ORDER BY name");
        echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
        ?>;

        let options = '';
        categories.forEach(cat => {
            const selected = (cat === selectedCategory) ? 'selected' : '';
            options += `<option value="${cat}" ${selected}>${cat}</option>`;
        });
        return options;
    }

    function toggleSelectAll(source) {
        document.querySelectorAll('.product-checkbox').forEach(cb => cb.checked = source.checked);
        updateBulkDeleteBtn();
    }

    function updateBulkDeleteBtn() {
        const checked = document.querySelectorAll('.product-checkbox:checked').length;
        document.getElementById('selectedCount').textContent = checked;
        document.getElementById('bulkDeleteBtn').style.display = checked > 0 ? 'inline-block' : 'none';
    }

    function submitBulkDelete() {
        if (confirm('Delete ' + document.getElementById('selectedCount').textContent + ' selected products?')) {
            document.getElementById('bulkDeleteForm').submit();
        }
    }

    let currentProductId = 0;

    function managePricing(productId) {
        currentProductId = productId;

        fetch(`api/get_pricing.php?product_id=${productId}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('pricingModalBody').innerHTML = html;
                new bootstrap.Modal(document.getElementById('pricingModal')).show();
            })
            .catch(error => {
                showNotification('error', 'Failed to load pricing tiers');
            });
    }

    // Edit tier - open modal with data
    function editTier(tierId) {
        const row = document.getElementById('tier-row-' + tierId);
        if (!row) {
            console.error('Row not found for tier:', tierId);
            return;
        }

        const type = row.querySelector('.badge').textContent.trim().toLowerCase();
        const minQty = row.querySelector('.tier-min').textContent.trim();
        const maxQty = row.querySelector('.tier-max').textContent.trim();
        const price = row.querySelector('.tier-price').textContent.replace('Rs.', '').replace(',', '').trim();

        // Create edit modal if not exists
        let editModal = document.getElementById('editTierModal');
        if (!editModal) {
            createEditTierModal();
            editModal = document.getElementById('editTierModal');
        }

        document.getElementById('edit_tier_id').value = tierId;
        document.getElementById('edit_tier_type').value = type;
        document.getElementById('edit_tier_min').value = minQty;
        document.getElementById('edit_tier_max').value = maxQty === '∞' ? '' : maxQty;
        document.getElementById('edit_tier_price').value = price;

        new bootstrap.Modal(editModal).show();
    }

    // Create edit tier modal
    function createEditTierModal() {
        const modalHtml = `
        <div class="modal fade" id="editTierModal" tabindex="-1">
            <div class="modal-dialog modal-sm">
                <div class="modal-content">
                    <div class="modal-header bg-warning">
                        <h6 class="modal-title">Edit Pricing Tier</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="edit_tier_id">
                        <div class="mb-2">
                            <label>Customer Type</label>
                            <select id="edit_tier_type" class="form-select">
                                <option value="wholesale">Wholesale</option>
                                <option value="retail">Retail</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label>Min Quantity</label>
                            <input type="number" id="edit_tier_min" class="form-control" step="0.001">
                        </div>
                        <div class="mb-2">
                            <label>Max Quantity (empty = ∞)</label>
                            <input type="number" id="edit_tier_max" class="form-control" step="0.001">
                        </div>
                        <div class="mb-2">
                            <label>Price per Unit</label>
                            <input type="number" id="edit_tier_price" class="form-control" step="0.01">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-warning" onclick="saveEditedTier()">Save</button>
                    </div>
                </div>
            </div>
        </div>
    `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }

    // Save edited tier
    async function saveEditedTier() {
        const tierId = document.getElementById('edit_tier_id').value;
        const type = document.getElementById('edit_tier_type').value;
        const minQty = document.getElementById('edit_tier_min').value;
        const maxQty = document.getElementById('edit_tier_max').value || null;
        const price = document.getElementById('edit_tier_price').value;

        if (!minQty || !price) {
            alert('Please fill Min Qty and Price');
            return;
        }

        const formData = new FormData();
        formData.append('tier_id', tierId);
        formData.append('customer_type', type);
        formData.append('min_quantity', minQty);
        if (maxQty) formData.append('max_quantity', maxQty);
        formData.append('price_per_unit', price);

        try {
            const response = await fetch('api/update_pricing.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                bootstrap.Modal.getInstance(document.getElementById('editTierModal')).hide();
                managePricing(currentProductId);
                showNotification('success', 'Pricing tier updated!');
            } else {
                alert(result.error || 'Failed to update tier');
            }
        } catch (error) {
            alert('Error updating tier');
        }
    }

    async function editProduct(id) {
        // Fetch product details
        const response = await fetch(`api/get_product.php?id=${id}`);
        const product = await response.json();

        if (product.error) {
            showNotification('error', product.error);
            return;
        }

        // Get unit options
        const unitOptions = await getUnitOptions(product.unit);

        const container = document.getElementById('addProductModalContainer');
        container.innerHTML = `
        <div class="modal fade" id="editProductModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form onsubmit="updateProduct(event, ${id})">
                        <div class="modal-header bg-warning">
                            <h5 class="modal-title">Edit Product: ${product.name}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label>Product Code *</label>
                                <div class="input-group">
                                    <input type="text" name="code" class="form-control" value="${product.code}" required>
                                    <button type="button" class="btn btn-outline-secondary" onclick="generateCodeForEdit()">
                                        <i class="bi bi-arrow-repeat"></i> Generate
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label>Product Name *</label>
                                <input  dir="rtl" type="text" name="name" class="form-control" value="${product.name}" required>
                            </div>
                            
                            <div class="mb-3">
                                <label>Category</label>
                                <select name="category" class="form-select">
                                    <option value="">Select Category</option>
                                    ${getCategorySelectOptions(product.category)}
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label>Unit</label>
                                <select name="unit" class="form-select">
                                    ${unitOptions}
                                </select>
                                <small class="text-muted">
                                    <a href="units.php" target="_blank">Manage Units</a>
                                </small>
                            </div>
                            
                            <div class="mb-3">
                                <label>Description</label>
                                <input type="text" name="description" class="form-control" value="${product.description || ''}">
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label>Current Stock</label>
                                    <input type="number" name="current_stock" class="form-control" step="0.001" value="${product.current_stock || 0}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>Min Stock Alert</label>
                                    <input type="number" name="min_stock_alert" class="form-control" step="0.001" value="${product.min_stock_alert || 10}">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label>Purchase Price (Cost) *</label>
                                <input type="number" name="purchase_price" class="form-control" step="0.01" value="${product.purchase_price || 0}" required>
                            </div>
                            
                            <div class="mb-3">
                                <label>Status</label>
                                <select name="status" class="form-select">
                                    <option value="active" ${product.status == 'active' ? 'selected' : ''}>Active</option>
                                    <option value="inactive" ${product.status == 'inactive' ? 'selected' : ''}>Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-warning">Update Product</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `;

        new bootstrap.Modal(document.getElementById('editProductModal')).show();
    }
    async function updateProduct(event, id) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        formData.append('id', id);
        const response = await fetch('api/update_product.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) { location.reload(); }
        else { alert(result.error || 'Failed to update product'); }
    }
    function showNotification(type, message) {
        let container = document.getElementById('notificationContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notificationContainer';
            container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999;';
            document.body.appendChild(container);
        }

        const notification = document.createElement('div');
        notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show`;
        notification.style.cssText = 'min-width: 300px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
        notification.innerHTML = `
        <strong>${type === 'success' ? 'Success!' : 'Error!'}</strong> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
        container.appendChild(notification);
        setTimeout(() => notification.remove(), 5000);
    }

    // Get unit options from database
    async function getUnitOptions(selectedUnit) {
        try {
            const response = await fetch('api/get_units.php');
            const units = await response.json();

            let options = '';
            units.forEach(unit => {
                const selected = unit.symbol === selectedUnit ? 'selected' : '';
                options += `<option value="${unit.symbol}" ${selected}>${unit.name} (${unit.symbol})</option>`;
            });
            return options;
        } catch (e) {
            // Fallback units if API fails
            const units = ['Piece', 'kg', 'g', 'liter', 'ml', 'packet', 'dozen', 'box', 'sack'];
            let options = '';
            units.forEach(unit => {
                const selected = unit === selectedUnit ? 'selected' : '';
                options += `<option value="${unit}" ${selected}>${unit}</option>`;
            });
            return options;
        }
    }

    function deleteTier(tierId) {
        if (confirm('Delete this pricing tier?')) {
            // Get current product ID from the modal context
            const productId = currentProductId;

            fetch(`api/delete_pricing.php?id=${tierId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('success', 'Tier deleted!');
                        // Refresh the modal content
                        managePricing(productId);
                    } else {
                        alert('Failed to delete tier');
                    }
                });
        }
    }
    // When pricing modal is closed, update the products table
    document.addEventListener('DOMContentLoaded', function () {
        const pricingModal = document.getElementById('pricingModal');
        if (pricingModal) {
            pricingModal.addEventListener('hidden.bs.modal', function () {
                // Clean up backdrops
                document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';

                // Update the table with fresh prices
                refreshProductsTable();
            });
        }
    });

    // Function to refresh only the products table data
    function refreshProductsTable() {
        // Get current filter/search params
        const urlParams = new URLSearchParams(window.location.search);
        const category = urlParams.get('category') || '';
        const search = urlParams.get('search') || '';

        // Fetch updated products list
        fetch(`api/get_products_list.php?category=${category}&search=${search}`)
            .then(response => response.json())
            .then(products => {
                updateTableRows(products);
            })
            .catch(error => {
                console.error('Failed to refresh table:', error);
                // Fallback: reload page if API fails
                location.reload();
            });
    }

    // Update table rows with new data
    function updateTableRows(products) {
        const tbody = document.querySelector('tbody');
        if (!tbody) return;

        // Clear existing rows
        tbody.innerHTML = '';

        if (products.length === 0) {
            tbody.innerHTML = `
            <tr>
                <td colspan="10" class="text-center py-4 text-muted">
                    <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                    <p class="mt-2">No products found.</p>
                </td>
            </tr>
        `;
            return;
        }

        // Add updated rows
        products.forEach(product => {
            const stockClass = product.current_stock <= product.min_stock_alert ? 'danger' : 'success';
            const row = document.createElement('tr');
            row.innerHTML = `
            <td><input type="checkbox" name="selected_products[]" value="${product.id}" class="product-checkbox" onchange="updateBulkDeleteBtn()"></td>
            <td><small>${escapeHtml(product.code)}</small></td>
            <td dir="rtl"><strong>${escapeHtml(product.name)}</strong></td>
            <td>${escapeHtml(product.category || '-')}</td>
            <td>${escapeHtml(product.unit || 'Piece')}</td>
            <td><span class="badge bg-${stockClass}">${product.current_stock}</span></td>
            <td>Rs. ${parseFloat(product.purchase_price || 0).toFixed(0)}</td>
            <td>Rs. ${parseFloat(product.retail_price || 0).toFixed(0)}</td>
            <td>Rs. ${parseFloat(product.wholesale_price || 0).toFixed(0)}</td>
            <td>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-primary" onclick="editProduct(${product.id})">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button type="button" class="btn btn-outline-success" onclick="managePricing(${product.id})">
                        <i class="bi bi-tag"></i>
                    </button>
                    <a href="products.php?delete=${product.id}" class="btn btn-outline-danger" onclick="return confirm('Delete this product?')">
                        <i class="bi bi-trash"></i>
                    </a>
                </div>
            </td>
        `;
            tbody.appendChild(row);
        });
    }

    // Helper to escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    function addPricingTier(productId) {
        const type = document.getElementById('new_tier_type').value;
        const minQty = document.getElementById('new_tier_min').value;
        const maxQtyInput = document.getElementById('new_tier_max');
        const maxQty = maxQtyInput.value.trim() === '' ? null : maxQtyInput.value;
        const price = document.getElementById('new_tier_price').value;

        if (!minQty || !price) {
            alert('Please fill Min Qty and Price');
            return;
        }

        const formData = new FormData();
        formData.append('product_id', productId);
        formData.append('customer_type', type);
        formData.append('min_quantity', minQty);
        if (maxQty !== null) {
            formData.append('max_quantity', maxQty);
        }
        formData.append('price_per_unit', price);

        fetch('api/save_pricing.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message briefly
                    showNotification('success', 'Tier added!');

                    // Refresh the pricing modal content (NOT the whole page)
                    managePricing(productId);

                    // Clear the input fields for next tier
                    document.getElementById('new_tier_min').value = '';
                    document.getElementById('new_tier_max').value = '';
                    document.getElementById('new_tier_price').value = '';
                    document.getElementById('new_tier_min').focus();
                } else {
                    alert(data.error);
                }
            });
    }
    // ===============================================
    // PACKAGE MANAGEMENT FUNCTIONS
    // ===============================================

    let packageCount = 0;

    let currentUnitProductId = 0;


async function addPackageRow() {
    packageCount++;
    const container = document.getElementById('packagesContainer');
    if (!container) return;
    
    // Fetch units for dropdown
    let unitsHtml = '';
    try {
        const response = await fetch('api/get_units.php');
        const units = await response.json();
        unitsHtml = units.map(u => `<option value="${u.name}">${u.name}</option>`).join('');
    } catch (e) {
        unitsHtml = `<option value="Dozen">Dozen</option><option value="Tray">Tray</option><option value="Box">Box</option>`;
    }
    
    const row = document.createElement('div');
    row.className = 'input-group mb-2';
    row.id = `package_row_${packageCount}`;
    row.innerHTML = `
        <select name="package_name[]" class="form-select" required>
            <option value="">Select Unit</option>
            ${unitsHtml}
        </select>
        <span class="input-group-text">=</span>
        <input type="number" name="package_multiplier[]" class="form-control" placeholder="Qty (e.g., 12)" step="1" min="1" required>
        <button type="button" class="btn btn-outline-danger" onclick="removePackageRow(${packageCount})">
            <i class="bi bi-x"></i>
        </button>
    `;
    
    container.appendChild(row);
}

function removePackageRow(id) {
    const row = document.getElementById(`package_row_${id}`);
    if (row) row.remove();
}
    // Manage packages for existing product
async function managePackages(productId, productName) {
    currentUnitProductId = productId;


     let unitsHtml = '';
    try {
        const response = await fetch('api/get_units.php');
        const units = await response.json();
        unitsHtml = units.map(u => `<option value="${u.name}">${u.name}</option>`).join('');
    } catch (e) {
        unitsHtml = `<option value="Dozen">Dozen</option><option value="Tray">Tray</option><option value="Box">Box</option>`;
    }
    
    // Fetch existing packages
    fetch(`api/get_product_packages.php?product_id=${productId}`)
        .then(response => response.json())
        .then(packages => {
            let html = `
                <div class="modal fade" id="managePackagesModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-secondary text-white">
                                <h5 class="modal-title">
                                    <i class="bi bi-boxes"></i> Manage Packages - ${productName}
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Package</th>
                                            <th>Multiplier</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="packagesList">
            `;
            
            if (packages.length === 0) {
                html += `<tr><td colspan="3" class="text-center text-muted">No packages added</td></tr>`;
            } else {
                packages.forEach(pkg => {
                    html += `
                        <tr>
                            <td>${pkg.package_name}</td>
                            <td>${pkg.multiplier}</td>
                            <td>
                                <button class="btn btn-sm btn-danger" onclick="deletePackage(${pkg.id})">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
            }
            
            html += `
                                </tbody>
                            </table>
                            <hr>
                            <h6>Add New Package</h6>
                            <div class="input-group mb-2">
                                <input type="text" id="new_package_name" class="form-control" placeholder="Name (e.g., Dozen)">
                                <span class="input-group-text">=</span>
                                <input type="number" id="new_package_multiplier" class="form-control" placeholder="Qty (e.g., 12)">
                                <button class="btn btn-primary" onclick="savePackage(${productId})">Add</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            `;
            
            // Remove existing modal if any
            const existingModal = document.getElementById('managePackagesModal');
            if (existingModal) existingModal.remove();
            
            document.body.insertAdjacentHTML('beforeend', html);
            new bootstrap.Modal(document.getElementById('managePackagesModal')).show();
        });
}

// Save new package
function savePackage(productId) {
    const name = document.getElementById('new_package_name').value;
    const multiplier = document.getElementById('new_package_multiplier').value;
    
    if (!name || !multiplier) {
        alert('Please enter both name and multiplier');
        return;
    }
    
    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('package_name', name);
    formData.append('multiplier', multiplier);
    
    fetch('api/save_product_package.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close modal and reopen to refresh
            bootstrap.Modal.getInstance(document.getElementById('managePackagesModal')).hide();
            managePackages(productId, document.querySelector('.modal-title').textContent.replace('Manage Packages - ', ''));
        } else {
            alert(data.error);
        }
    });
}

// Delete package
function deletePackage(id) {
    if (!confirm('Delete this package?')) return;
    
    fetch(`api/delete_product_package.php?id=${id}`)
        .then(() => {
            // Refresh modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('managePackagesModal'));
            const productName = document.querySelector('.modal-title').textContent.replace('Manage Packages - ', '');
            modal.hide();
            managePackages(currentUnitProductId, productName);
        });
}
</script>

<?php require_once 'includes/footer.php'; ?>