<?php
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
checkAuth();

$edit_mode = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$editing_invoice = null;

if ($edit_mode > 0) {
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
    $stmt->execute([$edit_mode]);
    $invoice_header = $stmt->fetch();

    if ($invoice_header) {
        $stmt = $pdo->prepare("
            SELECT product_id, product_name, quantity, unit_price, total_price, tier_info
            FROM invoice_items WHERE invoice_id = ?
        ");
        $stmt->execute([$edit_mode]);
        $items = $stmt->fetchAll();

        $editing_invoice = [
            'id' => $invoice_header['id'],
            'invoice_no' => $invoice_header['invoice_no'],
            'customer_name' => $invoice_header['customer_name'],
            'customer_phone' => $invoice_header['customer_phone'],
            'customer_type' => $invoice_header['customer_type'],
            'items' => $items
        ];
    }
}
?>

<style>
    .search-result-item.selected {
        background-color: #0d6efd !important;
        color: white !important;
    }

    .search-result-item.selected small {
        color: rgba(255, 255, 255, 0.8) !important;
    }
</style>

<div class="row">
    <div class="col-md-8">

        <!-- Product Search -->
        <?php include 'includes/billing_search_section.php'; ?>

        <!-- Quick Products Grid -->
        <?php include 'includes/billing_quick_products.php'; ?>

        <!-- Cart Items -->
        <?php include 'includes/billing_cart_table.php'; ?>
    </div>

    <div class="col-md-4">
        <?php include 'includes/billing_customer_section.php'; ?>
        <?php include 'includes/billing_summary.php'; ?>
    </div>
</div>

<!-- Voice Input Modal -->
<?php include 'includes/billing_voice_modal.php'; ?>

<?php
// Include all JavaScript files
include 'includes/billing_js_core.php';
include 'includes/billing_js_search.php';
include 'includes/billing_js_edit_mode.php';
?>

<script src="assets/js/cart.js"></script>
<script src="assets/js/invoices.js"></script>
<script src="assets/js/customer.js"></script>
<script src="assets/js/pakages.js"></script>
<script src="assets/js/voice.js"></script>
<script src="assets/js/voice_input.js"></script>

<?php require_once 'includes/footer.php'; ?>