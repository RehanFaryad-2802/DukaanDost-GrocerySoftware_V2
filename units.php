<?php
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

if ($_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'manager') {
    header('Location: dashboard.php');
    exit;
}

if (isset($_POST['add_unit'])) {
    $singular = trim($_POST['unit_singular']);
    $plural = trim($_POST['unit_plural']) ?: $singular;
    $type = $_POST['unit_type'] ?? 'packaging';
    $sub_sing = trim($_POST['sub_singular']) ?: null;
    $sub_plur = trim($_POST['sub_plural']) ?: null;
    $sub_factor = $_POST['sub_factor'] !== '' ? floatval($_POST['sub_factor']) : null;

    if (!empty($singular)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO units (name, symbol, type, name_urdu, name_singular, name_plural, sub_singular, sub_plural, sub_factor) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$singular, $singular, $type, $singular, $singular, $plural, $sub_sing, $sub_plur, $sub_factor]);
            $success = "Unit '$singular' added successfully!";
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $error = "Unit '$singular' already exists!";
            } else {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

if (isset($_POST['edit_unit'])) {
    $id = intval($_POST['unit_id']);
    $singular = trim($_POST['edit_singular']);
    $plural = trim($_POST['edit_plural']) ?: $singular;
    $type = $_POST['edit_type'] ?? 'packaging';
    $sub_sing = trim($_POST['edit_sub_singular']) ?: null;
    $sub_plur = trim($_POST['edit_sub_plural']) ?: null;
    $sub_factor = isset($_POST['edit_sub_factor']) && $_POST['edit_sub_factor'] !== '' ? floatval($_POST['edit_sub_factor']) : null;

    if (!empty($singular)) {
        try {
            $stmt = $pdo->prepare("UPDATE units SET name=?, symbol=?, type=?, name_urdu=?, name_singular=?, name_plural=?, sub_singular=?, sub_plural=?, sub_factor=? WHERE id=?");
            $stmt->execute([$singular, $singular, $type, $singular, $singular, $plural, $sub_sing, $sub_plur, $sub_factor, $id]);
            $success = "Unit updated!";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
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
                    <div class="row mb-3">
                        <div class="col-6">
                            <label>Singular <small class="text-muted">(qty = 1)</small></label>
                            <input type="text" name="unit_singular" class="form-control" placeholder="e.g., کلو"
                                required dir="rtl">
                        </div>
                        <div class="col-6">
                            <label>Plural <small class="text-muted">(qty > 1)</small></label>
                            <input type="text" name="unit_plural" class="form-control" placeholder="e.g., کلو"
                                dir="rtl">
                        </div>
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
                    <hr>
                    <label class="text-muted small">Auto-convert when qty &lt; 1 (optional)</label>
                    <div class="row mb-2 mt-1">
                        <div class="col-4">
                            <input type="text" name="sub_singular" class="form-control form-control-sm"
                                placeholder="گرام" dir="rtl">
                            <small class="text-muted">Sub singular</small>
                        </div>
                        <div class="col-4">
                            <input type="text" name="sub_plural" class="form-control form-control-sm" placeholder="گرام"
                                dir="rtl">
                            <small class="text-muted">Sub plural</small>
                        </div>
                        <div class="col-4">
                            <input type="number" name="sub_factor" class="form-control form-control-sm"
                                placeholder="1000">
                            <small class="text-muted">Factor</small>
                        </div>
                    </div>
                    <button type="submit" name="add_unit" class="btn btn-primary w-100 mt-2">Add Unit</button>
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
                            <th>Singular / Plural</th>
                            <th>Sub-unit</th>
                            <th>Type</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($units as $unit):
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE unit = ?");
                            $stmt->execute([$unit['symbol']]);
                            $is_used = $stmt->fetchColumn() > 0;
                            ?>
                            <tr>
                                <td>
                                    <a style="color: black"
                                        href="products.php?unit=<?= htmlspecialchars($unit['symbol']) ?>"
                                        class="text-decoration-none">
                                        <strong dir="rtl"><?php echo htmlspecialchars($unit['name']); ?></strong>
                                    </a>
                                </td>
                                <td>
                                    <span class="text-muted">—</span>
                                </td>
                                <td><span class="badge bg-success"><?php echo $unit['type']; ?></span></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-dark"
                                            onclick="editUnit(<?php echo $unit['id']; ?>, '<?php echo htmlspecialchars($unit['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($unit['name_plural'] ?? $unit['name'], ENT_QUOTES); ?>', '<?php echo $unit['type']; ?>', '<?php echo htmlspecialchars($unit['sub_singular'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($unit['sub_plural'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($unit['sub_factor'] ?? '', ENT_QUOTES); ?>')">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php if ($is_used): ?>
                                            <button class="btn btn-outline-danger" disabled
                                                title="Cannot delete: unit is used by products">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <a href="units.php?delete=<?php echo $unit['id']; ?>" class="btn btn-outline-danger"
                                                onclick="return confirm('Delete this unit?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (count($units) == 0): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">No units found.</td>
                            </tr>
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
                    <div class="row mb-3">
                        <div class="col-6">
                            <label>Singular <small class="text-muted">(qty = 1)</small></label>
                            <input type="text" name="edit_singular" id="edit_unit_singular" class="form-control"
                                dir="rtl" required>
                        </div>
                        <div class="col-6">
                            <label>Plural <small class="text-muted">(qty > 1)</small></label>
                            <input type="text" name="edit_plural" id="edit_unit_plural" class="form-control" dir="rtl">
                        </div>
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
                    <hr>
                    <label class="text-muted small">Auto-convert when qty &lt; 1 (optional)</label>
                    <div class="row mt-1">
                        <div class="col-4">
                            <input type="text" name="edit_sub_singular" id="edit_sub_singular"
                                class="form-control form-control-sm" placeholder="گرام" dir="rtl">
                            <small class="text-muted">Sub singular</small>
                        </div>
                        <div class="col-4">
                            <input type="text" name="edit_sub_plural" id="edit_sub_plural"
                                class="form-control form-control-sm" placeholder="گرام" dir="rtl">
                            <small class="text-muted">Sub plural</small>
                        </div>
                        <div class="col-4">
                            <input type="number" name="edit_sub_factor" id="edit_sub_factor"
                                class="form-control form-control-sm" placeholder="1000">
                            <small class="text-muted">Factor</small>
                        </div>
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
    function editUnit(id, singular, plural, type, subSing, subPlur, subFactor) {
        document.getElementById('edit_unit_id').value = id;
        document.getElementById('edit_unit_singular').value = singular || '';
        document.getElementById('edit_unit_plural').value = plural || '';
        document.getElementById('edit_unit_type').value = type;
        document.getElementById('edit_sub_singular').value = subSing || '';
        document.getElementById('edit_sub_plural').value = subPlur || '';
        document.getElementById('edit_sub_factor').value = subFactor || '';
        new bootstrap.Modal(document.getElementById('editUnitModal')).show();
    }
</script>

<?php require_once 'includes/footer.php'; ?>