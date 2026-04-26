<?php
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

if ($_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'manager') {
    header('Location: dashboard.php');
    exit;
}

if (isset($_POST['add_unit'])) {
    $name = trim($_POST['unit_name']);
    $symbol = trim($_POST['unit_symbol']);
    $type = $_POST['unit_type'] ?? 'packaging';
    
    if (!empty($name) && !empty($symbol)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO units (name, symbol, type) VALUES (?, ?, ?)");
            $stmt->execute([$name, $symbol, $type]);
            $success = "Unit '$name' added successfully!";
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $error = "Unit symbol '$symbol' already exists!";
            } else {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

if (isset($_POST['edit_unit'])) {
    $id = intval($_POST['unit_id']);
    $name = trim($_POST['edit_name']);
    $symbol = trim($_POST['edit_symbol']);
    $type = $_POST['edit_type'] ?? 'packaging';
    
    if (!empty($name) && !empty($symbol)) {
        try {
            // Get old symbol
            $stmt = $pdo->prepare("SELECT symbol FROM units WHERE id = ?");
            $stmt->execute([$id]);
            $oldSymbol = $stmt->fetchColumn();
            
            // Update unit in units table
            $stmt = $pdo->prepare("UPDATE units SET name = ?, symbol = ?, type = ? WHERE id = ?");
            $stmt->execute([$name, $symbol, $type, $id]);
            
            // Update all products using this unit
            $stmt = $pdo->prepare("UPDATE products SET unit = ? WHERE unit = ?");
            $stmt->execute([$symbol, $oldSymbol]);
            
            $updatedProducts = $stmt->rowCount();
            $success = "Unit updated! $updatedProducts products updated.";
            
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $error = "Unit symbol '$symbol' already exists!";
            } else {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Check if unit is in use
    $stmt = $pdo->prepare("SELECT symbol FROM units WHERE id = ?");
    $stmt->execute([$id]);
    $symbol = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE unit = ?");
    $stmt->execute([$symbol]);
    $inUse = $stmt->fetchColumn();
    
    if ($inUse > 0) {
        $error = "Cannot delete - unit is used by $inUse product(s)!";
    } else {
        $stmt = $pdo->prepare("DELETE FROM units WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Unit deleted successfully!";
    }
}

// Get all units
$units = $pdo->query("SELECT * FROM units ORDER BY type, name")->fetchAll();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-rulers"></i> Unit Management</h1>
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
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Add New Unit</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label>Unit Name *</label>
                        <input type="text" name="unit_name" class="form-control" placeholder="e.g., Kilogram" required>
                    </div>
                    <div class="mb-3">
                        <label>Unit Symbol *</label>
                        <input type="text" name="unit_symbol" class="form-control" placeholder="e.g., kg" required>
                    </div>
                    <div class="mb-3">
                        <label>Unit Type</label>
                        <select name="unit_type" class="form-select">
                            <option value="base">Base (Piece)</option>
                            <option value="packaging">Packaging (Box, Dozen)</option>
                            <option value="weight">Weight (kg, g)</option>
                            <option value="volume">Volume (liter, ml)</option>
                        </select>
                    </div>
                    <button type="submit" name="add_unit" class="btn btn-primary w-100">Add Unit</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-list"></i> Available Units</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Symbol</th>
                            <th>Type</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($units as $unit): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($unit['name']); ?></strong></td>
                            <td><code><?php echo htmlspecialchars($unit['symbol']); ?></code></td>
                            <td><span class="badge bg-info"><?php echo $unit['type']; ?></span></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-warning" onclick="editUnit(<?php echo $unit['id']; ?>, '<?php echo htmlspecialchars($unit['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($unit['symbol'], ENT_QUOTES); ?>', '<?php echo $unit['type']; ?>')">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <a href="units.php?delete=<?php echo $unit['id']; ?>" class="btn btn-outline-danger" onclick="return confirm('Delete this unit?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (count($units) == 0): ?>
                        <tr><td colspan="4" class="text-center py-4 text-muted">No units found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Unit Modal -->
<div class="modal fade" id="editUnitModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="unit_id" id="edit_unit_id">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">Edit Unit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Unit Name *</label>
                        <input type="text" name="edit_name" id="edit_unit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Unit Symbol *</label>
                        <input type="text" name="edit_symbol" id="edit_unit_symbol" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Unit Type</label>
                        <select name="edit_type" id="edit_unit_type" class="form-select">
                            <option value="base">Base (Piece)</option>
                            <option value="packaging">Packaging (Box, Dozen)</option>
                            <option value="weight">Weight (kg, g)</option>
                            <option value="volume">Volume (liter, ml)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_unit" class="btn btn-warning">Update Unit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editUnit(id, name, symbol, type) {
    document.getElementById('edit_unit_id').value = id;
    document.getElementById('edit_unit_name').value = name;
    document.getElementById('edit_unit_symbol').value = symbol;
    document.getElementById('edit_unit_type').value = type;
    new bootstrap.Modal(document.getElementById('editUnitModal')).show();
}
</script>

<?php require_once 'includes/footer.php'; ?>