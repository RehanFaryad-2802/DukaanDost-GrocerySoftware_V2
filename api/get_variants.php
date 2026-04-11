<?php
require_once '../config/database.php';
checkAuth();

$product_id = $_GET['product_id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY weight_kg");
$stmt->execute([$product_id]);
$variants = $stmt->fetchAll();
?>

<div class="mb-3">
    <h6>Base Product: <?php echo htmlspecialchars($product['name']); ?></h6>
    <p class="text-muted small">Add variants for different packaging sizes (e.g., 1kg, 25kg bag, 50kg sack)</p>
</div>

<table class="table table-sm">
    <thead>
        <tr>
            <th>Variant</th>
            <th>Unit</th>
            <th>Weight (kg)</th>
            <th>Retail</th>
            <th>Wholesale</th>
            <th>Min Qty</th>
            <th>Stock</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($variants as $v): ?>
        <tr>
            <td><?php echo htmlspecialchars($v['variant_name'] ?: '-'); ?></td>
            <td><?php echo $v['unit']; ?></td>
            <td><?php echo $v['weight_kg']; ?></td>
            <td>Rs. <?php echo number_format($v['retail_price'], 2); ?></td>
            <td>Rs. <?php echo number_format($v['wholesale_price'], 2); ?></td>
            <td><?php echo $v['wholesale_min_qty']; ?></td>
            <td><?php echo $v['current_stock']; ?></td>
            <td>
                <button class="btn btn-sm btn-danger" onclick="deleteVariant(<?php echo $v['id']; ?>)">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<hr>
<h6>Add New Variant</h6>
<div class="row g-2">
    <div class="col-md-2">
        <input type="text" id="variant_name" class="form-control" placeholder="Name (e.g., 1kg pack)">
    </div>
    <div class="col-md-2">
        <select id="variant_unit" class="form-select">
            <option value="kg">kg</option>
            <option value="g">g</option>
            <option value="piece">piece</option>
            <option value="packet">packet</option>
            <option value="sack">sack</option>
        </select>
    </div>
    <div class="col-md-2">
        <input type="number" id="variant_weight" class="form-control" placeholder="Weight (kg)" step="0.001">
    </div>
    <div class="col-md-2">
        <input type="number" id="variant_retail" class="form-control" placeholder="Retail Price" step="0.01">
    </div>
    <div class="col-md-2">
        <input type="number" id="variant_wholesale" class="form-control" placeholder="Wholesale Price" step="0.01">
    </div>
    <div class="col-md-2">
        <input type="number" id="variant_wholesale_qty" class="form-control" placeholder="Min Qty" value="5" step="1">
    </div>
    <div class="col-12 mt-2">
        <button class="btn btn-primary btn-sm" onclick="addVariant()">
            <i class="bi bi-plus"></i> Add Variant
        </button>
    </div>
</div>