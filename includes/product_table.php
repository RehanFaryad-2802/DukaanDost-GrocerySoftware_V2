<!-- Bulk Delete Form -->
<form id="bulkDeleteForm" method="POST" onsubmit="return confirm('Delete selected products? This cannot be undone!');">
    <input type="hidden" name="bulk_delete" value="1">

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th width="40"><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)"></th>
                        <th>Code</th>
                        <th style="text-align: center;">Name</th>
                        <th style="text-align: center;">Category</th>
                        <th>Unit</th>
                        <!-- <th>Stock</th> -->
                        <th>Cost</th>
                        <th>Retail</th>
                        <th>Wholesale</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product):
                        $stmt = $pdo->prepare("
                            SELECT price_per_unit FROM pricing_tiers 
                            WHERE product_id = ? AND customer_type = 'retail' 
                            ORDER BY min_quantity ASC LIMIT 1
                        ");
                        $stmt->execute([$product['id']]);
                        $retail = $stmt->fetchColumn() ?: 0;

                        $stmt = $pdo->prepare("
                            SELECT price_per_unit FROM pricing_tiers 
                            WHERE product_id = ? AND customer_type = 'wholesale' 
                            ORDER BY min_quantity ASC LIMIT 1
                        ");
                        $stmt->execute([$product['id']]);
                        $wholesale = $stmt->fetchColumn() ?: 0;

                        $stock_class = $product['current_stock'] <= $product['min_stock_alert'] ? 'danger' : 'success';
                        ?>
                        <tr>
                            <td><input type="checkbox" name="selected_products[]" value="<?= $product['id'] ?>"
                                    class="product-checkbox" onchange="updateBulkDeleteBtn()"></td>
                            <td><small><?= htmlspecialchars($product['code']) ?></small></td>
                            <td dir="rtl" style="text-align: center;">
                                <strong><?= htmlspecialchars($product['name']) ?></strong></td>
                            <td style="text-align: center;"><?= htmlspecialchars($product['category'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($product['unit'] ?: 'Piece') ?></td>
                            <!-- <td><span class="badge bg-<?= $stock_class ?>"><?= $product['current_stock'] ?></span></td> -->
                            <td>Rs. <?= number_format($product['purchase_price'] ?? 0, 0) ?></td>
                            <td>Rs. <?= number_format($retail, 0) ?></td>
                            <td>Rs. <?= number_format($wholesale, 0) ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <?php if ($_SESSION['user_role'] == 'admin' || $_SESSION['user_role'] == 'manager'): ?>
                                        <button type="button" class="btn btn-outline-primary"
                                            onclick="editProduct(<?= $product['id'] ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-success"
                                            onclick="managePricing(<?= $product['id'] ?>)">
                                            <i class="bi bi-tag"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary"
                                            onclick="managePackages(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name'], ENT_QUOTES) ?>')"
                                            title="Packages">
                                            <i class="bi bi-boxes"></i>
                                        </button>
                                        <a href="products.php?delete=<?= $product['id'] ?>" class="btn btn-outline-danger"
                                            onclick="return confirm('Delete this product?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">View Only</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (count($products) == 0): ?>
                        <tr>
                            <td colspan="10" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                                <p class="mt-2">No products found.</p>
                                <button type="button" class="btn btn-primary" onclick="openAddProductModal()">
                                    <i class="bi bi-plus-circle"></i> Add Your First Product
                                </button>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>