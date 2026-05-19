<?php
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

if ($_SESSION['user_role'] != 'admin') {
    header('Location: dashboard.php');
    exit;
}

if (isset($_POST['save_settings'])) {
    $store_name = $_POST['store_name'] ?? '';
    $store_address = $_POST['store_address'] ?? '';
    $store_phone = $_POST['store_phone'] ?? '';
    $store_gst = $_POST['store_gst'] ?? '';
    $invoice_prefix = $_POST['invoice_prefix'] ?? 'INV-';
    $receipt_header = $_POST['receipt_header'] ?? '';
    $receipt_footer = $_POST['receipt_footer'] ?? '';
    $currency_symbol = $_POST['currency_symbol'] ?? 'Rs.';
    $low_stock_alert = $_POST['low_stock_alert'] ?? 10;
    $calculator_enabled = isset($_POST['calculator_enabled']) ? 'on' : 'off';

    try {
        $settings = [
            'store_name' => $store_name,
            'store_address' => $store_address,
            'store_phone' => $store_phone,
            'store_gst' => $store_gst,
            'invoice_prefix' => $invoice_prefix,
            'receipt_header' => $receipt_header,
            'receipt_footer' => $receipt_footer,
            'currency_symbol' => $currency_symbol,
            'low_stock_alert' => $low_stock_alert,
            'calculator_enabled' => $calculator_enabled
        ];

        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) 
                           VALUES (?, ?) 
                           ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        }

        // Save voice input preference together with settings
        $voice_val = isset($_POST['voice_input']) ? 'on' : 'off';
        $stmt_vp = $pdo->prepare("INSERT INTO user_preferences (user_id, preference_key, preference_value) 
                                  VALUES (?, 'voice_input', ?) 
                                  ON DUPLICATE KEY UPDATE preference_value = ?");
        $stmt_vp->execute([$_SESSION['user_id'], $voice_val, $voice_val]);

        $success = "Settings saved successfully!";

    } catch (Exception $e) {
        $error = "Error saving settings: " . $e->getMessage();
    }
}

$stmt = $pdo->query("SELECT * FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Get user preferences for toggles
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT preference_key, preference_value FROM user_preferences WHERE user_id = ?");
$stmt->execute([$user_id]);
$prefs = [];
while ($row = $stmt->fetch()) {
    $prefs[$row['preference_key']] = $row['preference_value'];
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-gear"></i> Settings
    </h1>
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
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-shop"></i> Store Settings</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Store Name</label>
                            <input type="text" name="store_name" class="form-control"
                                value="<?php echo htmlspecialchars($settings['store_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Store Phone</label>
                            <input type="text" name="store_phone" class="form-control"
                                value="<?php echo htmlspecialchars($settings['store_phone'] ?? ''); ?>">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label>Store Address</label>
                            <textarea name="store_address" class="form-control"
                                rows="3"><?php echo htmlspecialchars($settings['store_address'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>GST Number (Optional)</label>
                            <input type="text" name="store_gst" class="form-control"
                                value="<?php echo htmlspecialchars($settings['store_gst'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Currency Symbol</label>
                            <input type="text" name="currency_symbol" class="form-control"
                                value="<?php echo htmlspecialchars($settings['currency_symbol'] ?? 'Rs.'); ?>">
                        </div>
                    </div>

                    <hr>
                    <h6 class="mb-3">Invoice Settings</h6>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Invoice Prefix</label>
                            <input type="text" name="invoice_prefix" class="form-control"
                                value="<?php echo htmlspecialchars($settings['invoice_prefix'] ?? 'INV-'); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Low Stock Alert Quantity</label>
                            <input type="number" name="low_stock_alert" class="form-control"
                                value="<?php echo htmlspecialchars($settings['low_stock_alert'] ?? '10'); ?>"
                                step="0.001">
                        </div>
                    </div>

                    <hr>
                    <h6 class="mb-3">Receipt Settings</h6>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label>Receipt Header</label>
                            <input type="text" name="receipt_header" class="form-control"
                                value="<?php echo htmlspecialchars($settings['receipt_header'] ?? 'Thank you for shopping!'); ?>">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label>Receipt Footer</label>
                            <input type="text" name="receipt_footer" class="form-control"
                                value="<?php echo htmlspecialchars($settings['receipt_footer'] ?? 'Goods once sold cannot be returned'); ?>">
                        </div>
                    </div>

                    <hr>
                    <h6 class="mb-3">Interface Preferences</h6>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <strong><i class="bi bi-mic"></i> Voice Input</strong><br>
                            <small class="text-muted">Show mic button and voice fields on billing &amp; product
                                pages</small>
                        </div>
                        <div class="form-check form-switch ms-3">
                            <input class="form-check-input" type="checkbox" name="voice_input" id="voiceToggle"
                                role="switch" style="width:2.5em;height:1.3em;" <?= $voice_input_enabled ? 'checked' : '' ?>>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <strong><i class="bi bi-calculator"></i> Show Calculator</strong><br>
                            <small class="text-muted">Display the sidebar system calculator block on the billing
                                page</small>
                        </div>
                        <div class="form-check form-switch ms-3">
                            <input class="form-check-input" type="checkbox" name="calculator_enabled" id="calcToggle"
                                role="switch" style="width:2.5em;height:1.3em;"
                                <?= isset($settings['calculator_enabled']) && $settings['calculator_enabled'] === 'on' ? 'checked' : '' ?>>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" name="save_settings" class="btn btn-primary">
                            <i class="bi bi-save"></i> Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Information</h5>
            </div>
            <div class="card-body">
                <p><strong>Store Name:</strong> Displayed on receipts</p>
                <p><strong>Invoice Prefix:</strong> e.g., INV-000001</p>
                <p><strong>Low Stock Alert:</strong> Show warning when stock falls below this</p>
                <p><strong>Receipt Header/Footer:</strong> Custom messages on receipts</p>
                <p><strong>
                        &lt;br&gt;:
                    </strong>Move content to new line.</p>
            </div>
        </div>
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Options</h5>
            </div>
            <div class="card-body">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="export_products.php">
                            <i class="bi bi-box-arrow-up"></i> Export Products
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="import_products.php">
                            <i class="bi bi-upload"></i> Import Products
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="sql/install.sql">
                            <i class="bi bi-hurricane"></i> Export Database Query
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>