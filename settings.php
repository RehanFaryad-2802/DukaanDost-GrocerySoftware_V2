<?php
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Only admin can access settings
if ($_SESSION['user_role'] != 'admin') {
    header('Location: dashboard.php');
    exit;
}

// Handle save settings
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
            'low_stock_alert' => $low_stock_alert
        ];
        
        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->execute([$value, $key]);
        }
        
        $success = "Settings saved successfully!";
        
    } catch (Exception $e) {
        $error = "Error saving settings: " . $e->getMessage();
    }
}

// Get current settings
$stmt = $pdo->query("SELECT * FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
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
                            <textarea name="store_address" class="form-control" rows="3"><?php echo htmlspecialchars($settings['store_address'] ?? ''); ?></textarea>
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
                                   value="<?php echo htmlspecialchars($settings['low_stock_alert'] ?? '10'); ?>" step="0.001">
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
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>