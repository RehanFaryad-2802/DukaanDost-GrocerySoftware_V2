<?php
require_once '../config/database.php';
checkAuth();

$product_id = $_GET['product_id'] ?? 0;
$product = $pdo->query("SELECT name FROM products WHERE id = $product_id")->fetch();
$tiers = $pdo->query("SELECT * FROM pricing_tiers WHERE product_id = $product_id ORDER BY customer_type, min_quantity")->fetchAll();
?>

<h6>Product: <?php echo htmlspecialchars($product['name']); ?></h6>

<table class="table table-sm">
    <thead>
        <tr><th>Type</th><th>Min Qty</th><th>Max Qty</th><th>Price/Unit</th><th>Action</th></tr>
    </thead>
    <tbody>
        <?php foreach ($tiers as $tier): ?>
        <tr>
            <td><span class="badge bg-<?php echo $tier['customer_type'] == 'wholesale' ? 'success' : 'info'; ?>"><?php echo $tier['customer_type']; ?></span></td>
            <td><?php echo $tier['min_quantity']; ?></td>
            <td><?php echo $tier['max_quantity'] ?? '∞'; ?></td>
            <td>Rs. <?php echo number_format($tier['price_per_unit'], 2); ?></td>
            <td><button class="btn btn-sm btn-danger" onclick="deleteTier(<?php echo $tier['id']; ?>)"><i class="bi bi-trash"></i></button></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<hr>
<h6>Add New Tier</h6>
<div class="row g-2">
    <div class="col-md-3">
        <select id="new_tier_type" class="form-select">
            <option value="wholesale">Wholesale</option>
            <option value="retail">Retail</option>
        </select>
    </div>
    <div class="col-md-2">
        <input type="number" id="new_tier_min" class="form-control" placeholder="Min Qty" step="0.001">
    </div>
    <div class="col-md-2">
        <input type="number" id="new_tier_max" class="form-control" placeholder="Max Qty" step="0.001">
    </div>
    <div class="col-md-3">
        <input type="number" id="new_tier_price" class="form-control" placeholder="Price" step="0.01">
    </div>
    <div class="col-md-2">
        <button class="btn btn-primary w-100" onclick="addPricingTier(<?php echo $product_id; ?>)">Add</button>
    </div>
</div>