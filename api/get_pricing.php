<?php
require_once '../config/database.php';
checkAuth();

$product_id = $_GET['product_id'] ?? 0;

$stmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT * FROM pricing_tiers 
    WHERE product_id = ? 
    ORDER BY customer_type, min_quantity
");
$stmt->execute([$product_id]);
$tiers = $stmt->fetchAll();
?>

<h6>Product: <?php echo htmlspecialchars($product['name']); ?></h6>

<table class="table table-sm">
    <thead>
        <tr>
            <th>Type</th>
            <th>Min Qty</th>
            <th>Max Qty</th>
            <th>Price/Unit</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($tiers as $tier): ?>
        <tr id="tier-row-<?php echo $tier['id']; ?>">
            <td>
                <span class="badge bg-<?php echo $tier['customer_type'] == 'wholesale' ? 'success' : 'info'; ?>">
                    <?php echo $tier['customer_type']; ?>
                </span>
            </td>
            <td>
                <span class="tier-min"><?php echo $tier['min_quantity']; ?></span>
            </td>
            <td>
                <span class="tier-max"><?php echo $tier['max_quantity'] ?? '∞'; ?></span>
            </td>
            <td>
                <span class="tier-price">Rs. <?php echo number_format($tier['price_per_unit'], 2); ?></span>
            </td>
            <td>
                <button class="btn btn-sm btn-warning" onclick="editTier(<?php echo $tier['id']; ?>)">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-sm btn-danger" onclick="deleteTier(<?php echo $tier['id']; ?>)">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
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

<!-- Edit Tier Modal -->
<div class="modal fade" id="editTierModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h6 class="modal-title">Edit Pricing Tier</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit_tier_id">
                <div class="mb-2">
                    <label>Customer Type</label>
                    <select id="edit_tier_type" class="form-select">
                        <option value="wholesale">Wholesale</option>
                        <option value="retail">Retail</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label>Min Quantity</label>
                    <input type="number" id="edit_tier_min" class="form-control" step="0.001">
                </div>
                <div class="mb-2">
                    <label>Max Quantity (leave empty for ∞)</label>
                    <input type="number" id="edit_tier_max" class="form-control" step="0.001">
                </div>
                <div class="mb-2">
                    <label>Price per Unit</label>
                    <input type="number" id="edit_tier_price" class="form-control" step="0.01">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="saveEditedTier()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script>
// Store current product ID for editing
const currentProductId = <?php echo $product_id; ?>;
let currentTierData = {};

// Edit tier - open modal with data
function editTier(tierId) {
    // Get the row data
    const row = document.getElementById('tier-row-' + tierId);
    const type = row.querySelector('.badge').textContent.trim().toLowerCase();
    const minQty = row.querySelector('.tier-min').textContent.trim();
    const maxQty = row.querySelector('.tier-max').textContent.trim();
    const price = row.querySelector('.tier-price').textContent.replace('Rs.', '').trim();
    
    // Store current tier data
    currentTierData = {
        id: tierId,
        type: type,
        min: minQty,
        max: maxQty === '∞' ? '' : maxQty,
        price: price
    };
    
    // Populate modal
    document.getElementById('edit_tier_id').value = tierId;
    document.getElementById('edit_tier_type').value = type;
    document.getElementById('edit_tier_min').value = minQty;
    document.getElementById('edit_tier_max').value = maxQty === '∞' ? '' : maxQty;
    document.getElementById('edit_tier_price').value = price;
    
    // Show modal
    new bootstrap.Modal(document.getElementById('editTierModal')).show();
}

// Save edited tier
async function saveEditedTier() {
    const tierId = document.getElementById('edit_tier_id').value;
    const type = document.getElementById('edit_tier_type').value;
    const minQty = document.getElementById('edit_tier_min').value;
    const maxQty = document.getElementById('edit_tier_max').value || null;
    const price = document.getElementById('edit_tier_price').value;
    
    if (!minQty || !price) {
        alert('Please fill Min Qty and Price');
        return;
    }
    
    const formData = new FormData();
    formData.append('tier_id', tierId);
    formData.append('customer_type', type);
    formData.append('min_quantity', minQty);
    if (maxQty) formData.append('max_quantity', maxQty);
    formData.append('price_per_unit', price);
    
    try {
        const response = await fetch('api/update_pricing.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('editTierModal')).hide();
            
            // Refresh pricing modal
            managePricing(currentProductId);
            
            showNotification('success', 'Pricing tier updated!');
        } else {
            alert(result.error || 'Failed to update tier');
        }
    } catch (error) {
        alert('Error updating tier');
    }
}
</script>