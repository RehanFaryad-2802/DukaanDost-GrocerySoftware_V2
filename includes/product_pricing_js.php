<script>
let currentProductId = 0;

function managePricing(productId) {
    currentProductId = productId;

    fetch(`api/get_pricing.php?product_id=${productId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('pricingModalBody').innerHTML = html;
            const pricingModal = document.getElementById('pricingModal');
            const modal = new bootstrap.Modal(pricingModal);
            modal.show();
            
            // Reload page when modal is closed (original behavior)
            pricingModal.addEventListener('hidden.bs.modal', () => {
                location.reload();
            }, { once: true });
        })
        .catch(error => {
            showNotification('error', 'Failed to load pricing tiers');
        });
}

// Edit tier
function editTier(tierId) {
    const row = document.getElementById('tier-row-' + tierId);
    if (!row) return;

    const type = row.querySelector('.badge').textContent.trim().toLowerCase();
    const minQty = row.querySelector('.tier-min').textContent.trim();
    const maxQty = row.querySelector('.tier-max').textContent.trim();
    const price = row.querySelector('.tier-price').textContent.replace('Rs.', '').replace(',', '').trim();

    let editModal = document.getElementById('editTierModal');
    if (!editModal) {
        createEditTierModal();
        editModal = document.getElementById('editTierModal');
    }

    document.getElementById('edit_tier_id').value = tierId;
    document.getElementById('edit_tier_type').value = type;
    document.getElementById('edit_tier_min').value = minQty;
    document.getElementById('edit_tier_max').value = maxQty === '∞' ? '' : maxQty;
    document.getElementById('edit_tier_price').value = price;

    new bootstrap.Modal(editModal).show();
}

function createEditTierModal() {
    const existingModal = document.getElementById('editTierModal');
    if (existingModal) {
        const instance = bootstrap.Modal.getInstance(existingModal);
        if (instance) instance.hide();
        setTimeout(() => existingModal.remove(), 500);
    }
    
    const modalHtml = `
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
                        <label>Max Quantity (empty = ∞)</label>
                        <input type="number" id="edit_tier_max" class="form-control" step="0.001">
                    </div>
                    <div class="mb-2">
                        <label>Price per Unit</label>
                        <input type="number" id="edit_tier_price" class="form-control" step="0.01">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" onclick="saveEditedTier()">Save</button>
                </div>
            </div>
        </div>
    </div>`;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    const newModal = document.getElementById('editTierModal');
    newModal.addEventListener('hidden.bs.modal', () => newModal.remove(), { once: true });
}

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
            bootstrap.Modal.getInstance(document.getElementById('editTierModal')).hide();
            managePricing(currentProductId);
            showNotification('success', 'Pricing tier updated!');
        } else {
            alert(result.error || 'Failed to update tier');
        }
    } catch (error) {
        alert('Error updating tier');
    }
}

function deleteTier(tierId) {
    if (confirm('Delete this pricing tier?')) {
        fetch(`api/delete_pricing.php?id=${tierId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('success', 'Tier deleted!');
                    managePricing(currentProductId);
                } else {
                    alert('Failed to delete tier');
                }
            });
    }
}

function addPricingTier(productId) {
    const type = document.getElementById('new_tier_type').value;
    const minQty = document.getElementById('new_tier_min').value;
    const maxQtyInput = document.getElementById('new_tier_max');
    const maxQty = maxQtyInput.value.trim() === '' ? null : maxQtyInput.value;
    const price = document.getElementById('new_tier_price').value;

    if (!minQty || !price) {
        alert('Please fill Min Qty and Price');
        return;
    }

    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('customer_type', type);
    formData.append('min_quantity', minQty);
    if (maxQty !== null) formData.append('max_quantity', maxQty);
    formData.append('price_per_unit', price);

    fetch('api/save_pricing.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', 'Tier added!');
            managePricing(productId);

            // Clear inputs
            document.getElementById('new_tier_min').value = '';
            document.getElementById('new_tier_max').value = '';
            document.getElementById('new_tier_price').value = '';
            document.getElementById('new_tier_min').focus();
        } else {
            alert(data.error || 'Failed to add tier');
        }
    });
}

// Bulk actions
function toggleSelectAll(source) {
    document.querySelectorAll('.product-checkbox').forEach(cb => cb.checked = source.checked);
    updateBulkDeleteBtn();
}

function updateBulkDeleteBtn() {
    const checked = document.querySelectorAll('.product-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = checked;
    document.getElementById('bulkDeleteBtn').style.display = checked > 0 ? 'inline-block' : 'none';
}

function submitBulkDelete() {
    if (confirm('Delete ' + document.getElementById('selectedCount').textContent + ' selected products?')) {
        document.getElementById('bulkDeleteForm').submit();
    }
}

// Refresh table after pricing changes (if needed)
document.addEventListener('DOMContentLoaded', function () {
    const pricingModal = document.getElementById('pricingModal');
    if (pricingModal) {
        pricingModal.addEventListener('hidden.bs.modal', function () {
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
        });
    }
});
</script>