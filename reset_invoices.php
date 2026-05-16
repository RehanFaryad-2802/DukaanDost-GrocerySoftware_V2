<?php
require_once 'config/database.php';
checkAuth();

if ($_SESSION['user_role'] != 'admin') {
    die("Only admin can reset invoices.");
}

if (isset($_POST['confirm_reset'])) {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->query("DELETE FROM invoice_items");
        $items_deleted = $stmt->rowCount();

        $stmt = $pdo->query("DELETE FROM invoices");
        $invoices_deleted = $stmt->rowCount();

        $pdo->query("ALTER TABLE invoices AUTO_INCREMENT = 1");
        $pdo->query("ALTER TABLE invoice_items AUTO_INCREMENT = 1");

        // Delete held invoices
        try {
            $stmt = $pdo->query("DELETE FROM held_invoices");
            $held_deleted = $stmt->rowCount();
        } catch (Exception $e) {
            $held_deleted = 0;
        }

        $pdo->commit();

        $success = "✅ Successfully deleted $invoices_deleted invoices and $items_deleted items!";
        if ($held_deleted > 0) {
            $success .= " Also cleared $held_deleted held invoices.";
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "❌ Error: " . $e->getMessage();
    }
}

$stmt = $pdo->query("SELECT COUNT(*) as count FROM invoices");
$invoice_count = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as count FROM invoice_items");
$item_count = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT SUM(total_amount) as total FROM invoices WHERE payment_status = 'paid'");
$total_sales = $stmt->fetchColumn() ?: 0;

try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM held_invoices");
    $held_count = $stmt->fetchColumn();
} catch (Exception $e) {
    $held_count = 0;
}

// Get recent invoices for preview
$stmt = $pdo->query("
    SELECT invoice_no, customer_name, total_amount, created_at 
    FROM invoices 
    ORDER BY created_at DESC 
    LIMIT 5
");
$recent_invoices = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Invoices - Grocery Billing</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/bootstrap-icons.css">
    <style>
        body {
            background: #f8f9fa;
            padding: 30px;
        }

        .container {
            max-width: 800px;
        }

        .card {
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .danger-zone {
            border: 2px solid #dc3545;
            background: #fff5f5;
        }

        .big-number {
            font-size: 48px;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-receipt text-danger"></i> Reset All Invoices</h1>
            <div>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Stats Card -->
        <div class="card">
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <h6 class="text-muted">Total Invoices</h6>
                        <div class="big-number text-primary"><?php echo number_format($invoice_count); ?></div>
                    </div>
                    <div class="col-md-3">
                        <h6 class="text-muted">Invoice Items</h6>
                        <div class="big-number text-info"><?php echo number_format($item_count); ?></div>
                    </div>
                    <div class="col-md-3">
                        <h6 class="text-muted">Total Sales</h6>
                        <div class="big-number text-success">
                            <?php echo $settings['currency_symbol']; ?><?php echo number_format($total_sales); ?>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <h6 class="text-muted">Held Invoices</h6>
                        <div class="big-number text-warning"><?php echo number_format($held_count); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Invoices Preview -->
        <?php if ($invoice_count > 0): ?>
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-list"></i> Recent Invoices (will be deleted)</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_invoices as $inv): ?>
                                <tr>
                                    <td><strong><?php echo $inv['invoice_no']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($inv['customer_name'] ?: 'Walk-in'); ?></td>
                                    <td>
                                        <?php echo $settings['currency_symbol']; ?>        <?php echo number_format($inv['total_amount']); ?>
                                    </td>
                                    <td><?php echo date('d-m-Y H:i', strtotime($inv['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($invoice_count > 5): ?>
                    <div class="card-footer text-muted">
                        <i class="bi bi-info-circle"></i> And <?php echo $invoice_count - 5; ?> more invoices...
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Danger Zone -->
        <div class="card danger-zone">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> DANGER ZONE - Reset All Invoices</h5>
            </div>
            <div class="card-body">
                <?php if ($invoice_count == 0 && $held_count == 0): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i> No invoices to delete! Database is already clean.
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-circle"></i>
                        <strong>Warning!</strong> This will permanently delete:
                        <ul class="mt-2">
                            <li><strong><?php echo number_format($invoice_count); ?> invoices</strong></li>
                            <li><strong><?php echo number_format($item_count); ?> invoice items</strong></li>
                            <?php if ($held_count > 0): ?>
                                <li><strong><?php echo number_format($held_count); ?> held invoices</strong></li>
                            <?php endif; ?>
                        </ul>
                        <p class="mb-0 mt-2 text-danger"><strong>This action CANNOT be undone!</strong></p>
                    </div>

                    <form method="POST"
                        onsubmit="return confirm('FINAL WARNING: Delete ALL invoices permanently?\n\nThis CANNOT be undone!')">
                        <div class="mb-3">
                            <label class="form-label">Type <strong>DELETE ALL INVOICES</strong> to confirm</label>
                            <input type="text" name="confirm_text" id="confirm_text" class="form-control"
                                placeholder="DELETE ALL INVOICES" required
                                oninput="document.getElementById('reset_btn').disabled = this.value !== 'DELETE ALL INVOICES'">
                        </div>

                        <div class="d-grid">
                            <button type="submit" name="confirm_reset" id="reset_btn" class="btn btn-danger btn-lg"
                                disabled>
                                <i class="bi bi-trash-fill"></i> PERMANENTLY DELETE ALL INVOICES
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- What Gets Deleted vs Kept -->
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> What Happens</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-danger"><i class="bi bi-x-circle"></i> Will Be DELETED:</h6>
                        <ul>
                            <li>All invoices</li>
                            <li>All invoice items</li>
                            <li>All held invoices</li>
                            <li>Invoice numbers reset to 000001</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-success"><i class="bi bi-check-circle"></i> Will Be KEPT:</h6>
                        <ul>
                            <li>All products</li>
                            <li>Categories and units</li>
                            <li>Pricing tiers</li>
                            <li>Stock levels (unchanged)</li>
                            <li>Users and settings</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-4 text-muted">
            <small>Reset Invoices Tool v1.0 | Admin Only</small>
        </div>
    </div>

    <script>
        document.getElementById('confirm_text').addEventListener('input', function () {
            document.getElementById('reset_btn').disabled = this.value !== 'DELETE ALL INVOICES';
        });
    </script>
</body>

</html>