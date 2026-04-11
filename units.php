<?php
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Handle add unit
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
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM units WHERE id = ?");
    $stmt->execute([$id]);
    $success = "Unit deleted!";
}

// Get all units
$units = $pdo->query("SELECT * FROM units ORDER BY type, name")->fetchAll();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-rulers"></i> Unit Management</h1>
    <a href="products.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to Products</a>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Add New Unit</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label>Unit Name *</label>
                        <input type="text" name="unit_name" class="form-control" placeholder="e.g., Box, Dozen" required>
                    </div>
                    <div class="mb-3">
                        <label>Unit Symbol *</label>
                        <input type="text" name="unit_symbol" class="form-control" placeholder="e.g., box, doz" required>
                    </div>
                    <div class="mb-3">
                        <label>Unit Type</label>
                        <select name="unit_type" class="form-select">
                            <option value="base">Base (Piece, kg, etc.)</option>
                            <option value="packaging">Packaging (Box, Dozen, etc.)</option>
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
                <h5 class="mb-0">Available Units</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr><th>Name</th><th>Symbol</th><th>Type</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($units as $unit): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($unit['name']); ?></td>
                            <td><code><?php echo htmlspecialchars($unit['symbol']); ?></code></td>
                            <td><span class="badge bg-info"><?php echo $unit['type']; ?></span></td>
                            <td>
                                <a href="units.php?delete=<?php echo $unit['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this unit?')">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>