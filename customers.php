<?php
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Only admin and manager can access
if ($_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'manager') {
    header('Location: dashboard.php');
    exit;
}

// Handle add customer
if (isset($_POST['add_customer'])) {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $customer_type = $_POST['customer_type'] ?? 'retail';
    
    if (!empty($name)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO customers (name, phone, address, customer_type) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$name, $phone, $address, $customer_type]);
            $success = "Customer '$name' added successfully!";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Handle edit customer
if (isset($_POST['edit_customer'])) {
    $id = intval($_POST['customer_id']);
    $name = trim($_POST['edit_name']);
    $phone = trim($_POST['edit_phone'] ?? '');
    $address = trim($_POST['edit_address'] ?? '');
    $customer_type = $_POST['edit_customer_type'] ?? 'retail';
    
    if (!empty($name)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE customers SET name = ?, phone = ?, address = ?, customer_type = ? 
                WHERE id = ?
            ");
            $stmt->execute([$name, $phone, $address, $customer_type, $id]);
            $success = "Customer updated successfully!";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Don't delete Walk-in Customer (ID 1)
    if ($id != 1) {
        $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Customer deleted successfully!";
    } else {
        $error = "Cannot delete default Walk-in Customer!";
    }
}

// Get all customers
$stmt = $pdo->query("
    SELECT * FROM customers 
    ORDER BY name
");
$customers = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-people"></i> Customer Management</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
        <i class="bi bi-plus-circle"></i> Add New Customer
    </button>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        ✅ <?php echo $success; ?>
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
    <div class="card-body p-0">
        <table class="table table-striped table-hover mb-0">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Address</th>
                    <th>Type</th>
                    <th>Total Purchases</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customers as $customer): ?>
                <tr>
                    <td>#<?php echo $customer['id']; ?></td>
                    <td><strong><?php echo htmlspecialchars($customer['name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($customer['phone'] ?: '-'); ?></td>
                    <td><?php echo htmlspecialchars($customer['address'] ?: '-'); ?></td>
                    <td>
                        <span class="badge bg-<?php echo $customer['customer_type'] == 'wholesale' ? 'success' : 'info'; ?>">
                            <?php echo ucfirst($customer['customer_type']); ?>
                        </span>
                    </td>
                    <td>Rs. <?php echo number_format($customer['total_purchases'], 2); ?></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-warning" onclick="editCustomer(<?php echo $customer['id']; ?>, '<?php echo htmlspecialchars($customer['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($customer['phone'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($customer['address'] ?? '', ENT_QUOTES); ?>', '<?php echo $customer['customer_type']; ?>')">
                                <i class="bi bi-pen"></i>
                            </button>
                            <?php if ($customer['id'] != 1): ?>
                            <a href="customers.php?delete=<?php echo $customer['id']; ?>" class="btn btn-outline-danger" onclick="return confirm('Delete this customer?')">
                                <i class="bi bi-trash"></i>
                            </a>
                            <?php else: ?>
                            <button class="btn btn-outline-secondary" disabled title="Default customer">
                                <i class="bi bi-lock"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Customer Modal -->
<div class="modal fade" id="addCustomerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-person-plus"></i> Add New Customer</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Customer Name *</label>
                        <input dir="rtl" type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Phone (Optional)</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label>Address (Optional)</label>
                        <textarea name="address" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label>Customer Type</label>
                        <select name="customer_type" class="form-select">
                            <option value="retail">Retail</option>
                            <option value="wholesale">Wholesale</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_customer" class="btn btn-primary">Save Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Customer Modal -->
<div class="modal fade" id="editCustomerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="customer_id" id="edit_customer_id">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="bi bi-pen"></i> Edit Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Customer Name *</label>
                        <input type="text" name="edit_name" id="edit_customer_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Phone (Optional)</label>
                        <input type="text" name="edit_phone" id="edit_customer_phone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label>Address (Optional)</label>
                        <textarea name="edit_address" id="edit_customer_address" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label>Customer Type</label>
                        <select name="edit_customer_type" id="edit_customer_type" class="form-select">
                            <option value="retail">Retail</option>
                            <option value="wholesale">Wholesale</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_customer" class="btn btn-warning">Update Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editCustomer(id, name, phone, address, type) {
    document.getElementById('edit_customer_id').value = id;
    document.getElementById('edit_customer_name').value = name;
    document.getElementById('edit_customer_phone').value = phone;
    document.getElementById('edit_customer_address').value = address;
    document.getElementById('edit_customer_type').value = type;
    new bootstrap.Modal(document.getElementById('editCustomerModal')).show();
}
</script>

<?php require_once 'includes/footer.php'; ?>