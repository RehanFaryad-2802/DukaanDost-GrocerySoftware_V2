<?php
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Get all products with complete details
$stmt = $pdo->query("
    SELECT 
        p.*,
        COUNT(DISTINCT pt.id) as tier_count,
        SUM(CASE WHEN pt.customer_type = 'wholesale' THEN 1 ELSE 0 END) as wholesale_tiers,
        SUM(CASE WHEN pt.customer_type = 'retail' THEN 1 ELSE 0 END) as retail_tiers,
        MIN(CASE WHEN pt.customer_type = 'wholesale' THEN pt.price_per_unit END) as min_wholesale_price,
        MAX(CASE WHEN pt.customer_type = 'wholesale' THEN pt.price_per_unit END) as max_wholesale_price,
        MIN(CASE WHEN pt.customer_type = 'retail' THEN pt.price_per_unit END) as min_retail_price,
        MAX(CASE WHEN pt.customer_type = 'retail' THEN pt.price_per_unit END) as max_retail_price
    FROM products p
    LEFT JOIN pricing_tiers pt ON p.id = pt.product_id
    GROUP BY p.id
    ORDER BY p.category, p.name
");
$products = $stmt->fetchAll();

// Get summary statistics
$total_products = count($products);
$total_stock_value = 0;
$active_products = 0;
$low_stock_count = 0;

foreach ($products as $p) {
    $total_stock_value += $p['current_stock'] * $p['purchase_price'];
    if ($p['status'] == 'active') $active_products++;
    if ($p['current_stock'] <= $p['min_stock_alert']) $low_stock_count++;
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Complete Products Report</h1>
    <div>
        <a href="export_products.php" class="btn btn-success">
            <i class="bi bi-download"></i> Export to Excel
        </a>
        <button class="btn btn-primary" onclick="window.print()">
            <i class="bi bi-printer"></i> Print Report
        </button>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h6>Total Products</h6>
                <h3><?php echo $total_products; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6>Active Products</h6>
                <h3><?php echo $active_products; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <h6>Low Stock Items</h6>
                <h3><?php echo $low_stock_count; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h6>Stock Value (Cost)</h6>
                <h3>Rs. <?php echo number_format($total_stock_value, 2); ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Products Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th>Stock</th>
                        <th>Cost Price</th>
                        <th>Wholesale Range</th>
                        <th>Retail Range</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                    <tr>
                        <td><strong><?php echo $product['code']; ?></strong></td>
                        <td>
                            <?php echo $product['name']; ?>
                            <?php if ($product['description']): ?>
                                <br><small class="text-muted"><?php echo $product['description']; ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $product['category'] ?: '-'; ?></td>
                        <td><?php echo $product['unit']; ?></td>
                        <td>
                            <?php 
                            $stock_class = $product['current_stock'] <= $product['min_stock_alert'] ? 'danger' : 'success';
                            ?>
                            <span class="badge bg-<?php echo $stock_class; ?>">
                                <?php echo $product['current_stock']; ?>
                            </span>
                            <?php if ($product['current_stock'] <= $product['min_stock_alert']): ?>
                                <br><small class="text-danger">Low Stock!</small>
                            <?php endif; ?>
                        </td>
                        <td>Rs. <?php echo number_format($product['purchase_price'], 2); ?></td>
                        <td>
                            <?php if ($product['wholesale_tiers'] > 0): ?>
                                Rs. <?php echo number_format($product['min_wholesale_price'], 2); ?> - 
                                Rs. <?php echo number_format($product['max_wholesale_price'], 2); ?>
                                <br><small><?php echo $product['wholesale_tiers']; ?> tiers</small>
                            <?php else: ?>
                                <span class="text-muted">Not set</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($product['retail_tiers'] > 0): ?>
                                Rs. <?php echo number_format($product['min_retail_price'], 2); ?> - 
                                Rs. <?php echo number_format($product['max_retail_price'], 2); ?>
                                <br><small><?php echo $product['retail_tiers']; ?> tiers</small>
                            <?php else: ?>
                                <span class="text-muted">Not set</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $product['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                <?php echo $product['status']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Detailed Pricing Tiers -->
<div class="card mt-4">
    <div class="card-header">
        <h5>Complete Pricing Details</h5>
    </div>
    <div class="card-body">
        <?php
        // Get detailed pricing for each product
        $stmt = $pdo->query("
            SELECT 
                p.name as product_name,
                p.unit,
                pt.customer_type,
                pt.min_quantity,
                pt.max_quantity,
                pt.price_per_unit,
                pt.package_price
            FROM products p
            JOIN pricing_tiers pt ON p.id = pt.product_id
            ORDER BY p.name, pt.customer_type, pt.min_quantity
        ");
        $tiers = $stmt->fetchAll();
        
        $current_product = '';
        foreach ($tiers as $tier):
            if ($current_product != $tier['product_name']):
                if ($current_product != '') echo '</tbody></table><br>';
                $current_product = $tier['product_name'];
        ?>
            <h6><?php echo $tier['product_name']; ?></h6>
            <table class="table table-sm table-bordered">
                <thead>
                    <tr>
                        <th>Customer Type</th>
                        <th>Min Qty (<?php echo $tier['unit']; ?>)</th>
                        <th>Max Qty (<?php echo $tier['unit']; ?>)</th>
                        <th>Price per <?php echo $tier['unit']; ?></th>
                        <th>Package Price</th>
                    </tr>
                </thead>
                <tbody>
        <?php endif; ?>
                <tr>
                    <td>
                        <span class="badge bg-<?php echo $tier['customer_type'] == 'wholesale' ? 'success' : 'info'; ?>">
                            <?php echo ucfirst($tier['customer_type']); ?>
                        </span>
                    </td>
                    <td><?php echo $tier['min_quantity']; ?></td>
                    <td><?php echo $tier['max_quantity'] ?? '∞'; ?></td>
                    <td>Rs. <?php echo number_format($tier['price_per_unit'], 2); ?></td>
                    <td><?php echo $tier['package_price'] ? 'Rs. ' . number_format($tier['package_price'], 2) : '-'; ?></td>
                </tr>
        <?php endforeach; ?>
                </tbody>
            </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>