<?php
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Handle add category
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

// Handle delete category
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Check if category is in use
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category = (SELECT name FROM categories WHERE id = ?)");
    $stmt->execute([$id]);
    $inUse = $stmt->fetchColumn();
    
    if ($inUse > 0) {
        $error = "Cannot delete category - it is used by $inUse product(s)!";
    } else {
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Category deleted successfully!";
    }
}

// Get all categories
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
    <h1 class="h2">
        <i class="bi bi-folder"></i> Category Management
    </h1>
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
                <h5 class="mb-0">
                    <i class="bi bi-plus-circle"></i> Add New Category
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label>Category Name *</label>
                        <input dir="rtl" type="text" name="category_name" class="form-control" required 
                               placeholder="نام۔۔۔">
                    </div>
                    <div class="mb-3">
                        <label>Description (Optional)</label>
                        <textarea name="description" class="form-control" rows="2" 
                                  placeholder="Brief description of this category"></textarea>
                    </div>
                    <button type="submit" name="add_category" class="btn btn-primary w-100">
                        <i class="bi bi-save"></i> Save Category
                    </button>
                </form>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="bi bi-info-circle"></i> Information
                </h5>
            </div>
            <div class="card-body">
                <p class="mb-2"><strong>Total Categories:</strong> <?php echo count($categories); ?></p>
                <p class="mb-0 small text-muted">
                    Categories help organize your products. You can assign a category when adding or editing a product.
                </p>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="bi bi-list"></i> All Categories
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (count($categories) > 0): ?>
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Products</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td>#<?php echo $cat['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($cat['name']); ?></strong>
                            </td>
                            <td>
                                <small class="text-muted"><?php echo htmlspecialchars($cat['description'] ?: '-'); ?></small>
                            </td>
                            <td>
                                <?php if ($cat['product_count'] > 0): ?>
                                    <a href="products.php?category=<?php echo urlencode($cat['name']); ?>" class="badge bg-primary text-decoration-none">
                                        <?php echo $cat['product_count']; ?> products
                                    </a>
                                <?php else: ?>
                                    <span class="badge bg-secondary">0 products</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="products.php?category=<?php echo urlencode($cat['name']); ?>" 
                                       class="btn btn-outline-primary" title="View Products">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($cat['product_count'] == 0): ?>
                                    <a href="categories.php?delete=<?php echo $cat['id']; ?>" 
                                       class="btn btn-outline-danger" 
                                       onclick="return confirm('Delete category \'<?php echo htmlspecialchars($cat['name']); ?>\'?')"
                                       title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                    <?php else: ?>
                                    <button class="btn btn-outline-secondary" disabled title="Cannot delete - category in use">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-folder-x" style="font-size: 3rem;"></i>
                    <h5 class="mt-3">No Categories Found</h5>
                    <p>Add your first category using the form.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>