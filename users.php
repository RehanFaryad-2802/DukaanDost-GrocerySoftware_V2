<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Only admin can access users page
if ($_SESSION['user_role'] != 'admin') {
    header('Location: dashboard.php');
    exit;
}

// Handle add user
if (isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $full_name = trim($_POST['full_name']);
    $role = $_POST['role'];
    
    if (!empty($username) && !empty($password)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password, full_name, role, status) 
                VALUES (?, ?, ?, ?, 'active')
            ");
            $stmt->execute([$username, $hashed_password, $full_name, $role]);
            
            $success = "User '$username' added successfully!";
            
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $error = "Username '$username' already exists!";
            } else {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

// Handle delete user
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Don't allow deleting yourself
    if ($id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $success = "User deleted successfully!";
    } else {
        $error = "You cannot delete your own account!";
    }
}

// Handle toggle status
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    
    if ($id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("UPDATE users SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?");
        $stmt->execute([$id]);
        $success = "User status updated!";
    } else {
        $error = "You cannot deactivate your own account!";
    }
}

// Handle reset password
if (isset($_POST['reset_password'])) {
    $id = intval($_POST['user_id']);
    $new_password = trim($_POST['new_password']);
    
    if (!empty($new_password)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed_password, $id]);
        $success = "Password reset successfully!";
    }
}

// Get all users
$stmt = $pdo->query("
    SELECT 
        id, username, full_name, role, status, 
        last_login, created_at
    FROM users 
    ORDER BY role, username
");
$users = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-people"></i> User Management
    </h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="bi bi-plus-circle"></i> Add New User
    </button>
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

<!-- Users Table -->
<div class="card">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0"><i class="bi bi-list"></i> All Users</h5>
    </div>
    <div class="card-body p-0">
        <table class="table table-striped table-hover mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td>#<?php echo $user['id']; ?></td>
                    <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                    <td><?php echo htmlspecialchars($user['full_name'] ?: '-'); ?></td>
                    <td>
                        <span class="badge bg-<?php 
                            echo $user['role'] == 'admin' ? 'danger' : 
                                ($user['role'] == 'manager' ? 'warning' : 'info'); 
                        ?>">
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-<?php echo $user['status'] == 'active' ? 'success' : 'secondary'; ?>">
                            <?php echo ucfirst($user['status']); ?>
                        </span>
                    </td>
                    <td>
                        <small><?php echo $user['last_login'] ? date('d-m-Y H:i', strtotime($user['last_login'])) : 'Never'; ?></small>
                    </td>
                    <td>
                        <small><?php echo date('d-m-Y', strtotime($user['created_at'])); ?></small>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <button class="btn btn-outline-warning" onclick="resetPassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                <i class="bi bi-key"></i>
                            </button>
                            <a href="users.php?toggle=<?php echo $user['id']; ?>" class="btn btn-outline-info">
                                <i class="bi bi-<?php echo $user['status'] == 'active' ? 'pause' : 'play'; ?>"></i>
                            </a>
                            <a href="users.php?delete=<?php echo $user['id']; ?>" class="btn btn-outline-danger" onclick="return confirm('Delete user \'<?php echo htmlspecialchars($user['username']); ?>\'?')">
                                <i class="bi bi-trash"></i>
                            </a>
                            <?php else: ?>
                            <span class="badge bg-secondary">Current User</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-person-plus"></i> Add New User
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Username *</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Password *</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Full Name</label>
                        <input type="text" name="full_name" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label>Role *</label>
                        <select name="role" class="form-select" required>
                            <option value="cashier">Cashier</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="user_id" id="reset_user_id">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">
                        <i class="bi bi-key"></i> Reset Password - <span id="reset_username"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>New Password *</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="reset_password" class="btn btn-warning">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetPassword(userId, username) {
    document.getElementById('reset_user_id').value = userId;
    document.getElementById('reset_username').textContent = username;
    new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
}
</script>

<?php require_once 'includes/footer.php'; ?>