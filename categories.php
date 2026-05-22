<?php
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

if ($_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'manager') {
    header('Location: dashboard.php');
    exit;
}

if (isset($_POST['add_category'])) {
    $name = trim($_POST['category_name']);
    $description = trim($_POST['description'] ?? '');

    if (!empty($name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
            $stmt->execute([$name, $description]);
            $success = "Category '$name' added successfully!";
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $error = "Category '$name' already exists!";
            } else {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

if (isset($_POST['edit_category'])) {
    $id = intval($_POST['category_id']);
    $name = trim($_POST['edit_name']);
    $description = trim($_POST['edit_description'] ?? '');

    if (!empty($name)) {
        try {
            $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            $oldName = $stmt->fetchColumn();

            $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
            $stmt->execute([$name, $description, $id]);

            $stmt = $pdo->prepare("UPDATE products SET category = ? WHERE category = ?");
            $stmt->execute([$name, $oldName]);

            $success = "Category updated successfully!";
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $error = "Category '$name' already exists!";
            } else {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $catName = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category = ?");
    $stmt->execute([$catName]);
    $inUse = $stmt->fetchColumn();

    if ($inUse > 0) {
        $error = "Cannot delete - category is used by $inUse product(s)!";
    } else {
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Category deleted successfully!";
    }
}

// Get all categories with product count
$stmt = $pdo->query("
    SELECT 
        c.*,
        COUNT(p.id) as product_count
    FROM categories c
    LEFT JOIN products p ON p.category = c.name
    GROUP BY c.id
    ORDER BY c.name
");
$categories = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-folder"></i> Category Management</h1>
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

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Add New Category</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label>Category Name *</label>
                        <input type="text" name="category_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Description (Optional)</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                    <button type="submit" name="add_category" class="btn btn-primary w-100">
                        <i class="bi bi-save"></i> Save Category
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-list"></i> All Categories</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Products</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td><strong><a style="text-decoration: none; color: black;"
                                            href="products.php?category=<?php echo urlencode($cat['name']); ?>"><?php echo htmlspecialchars($cat['name']); ?></a></strong>
                                </td>
                                <td><small><?php echo htmlspecialchars($cat['description'] ?: '-'); ?></small></td>
                                <td>
                                    <?php if ($cat['product_count'] > 0): ?>
                                        <span class="badge bg-success"><?php echo $cat['product_count']; ?> products</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">0 products</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-dark"
                                            onclick="editCategory(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars($cat['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cat['description'] ?? '', ENT_QUOTES); ?>')">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php if ($cat['product_count'] == 0): ?>
                                            <a href="categories.php?delete=<?php echo $cat['id']; ?>"
                                                class="btn btn-outline-danger"
                                                onclick="return confirm('Delete this category?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-outline-secondary" disabled title="Cannot delete - in use">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (count($categories) == 0): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">No categories found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="category_id" id="edit_category_id">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">Edit Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Category Name *</label>
                        <input type="text" name="edit_name" id="edit_category_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Description</label>
                        <textarea name="edit_description" id="edit_category_desc" class="form-control"
                            rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_category" class="btn btn-warning">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function editCategory(id, name, description) {
        document.getElementById('edit_category_id').value = id;
        document.getElementById('edit_category_name').value = name;
        document.getElementById('edit_category_desc').value = description;
        new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
    }
</script>

<?php require_once 'includes/footer.php'; ?>