<script>
// ==================== ADD PRODUCT MODAL ====================
async function openAddProductModal() {
    const container = document.getElementById('addProductModalContainer');

    let unitsHtml = '';
    try {
        const response = await fetch('api/get_units.php');
        const units = await response.json();
        unitsHtml = units.map(u => `<option value="${u.symbol}">${u.name}</option>`).join('');
    } catch (e) {
        unitsHtml = `
            <option value="Piece">Piece</option>
            <option value="kg">Kilogram (kg)</option>
            <option value="g">Gram (g)</option>
            <option value="liter">Liter</option>
            <option value="ml">Milliliter (ml)</option>
            <option value="packet">Packet</option>
            <option value="dozen">Dozen</option>
            <option value="box">Box</option>
        `;
    }

    container.innerHTML = `
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add New Product</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Product Code *</label>
                            <div class="input-group">
                                <input type="text" name="code" id="productCodeInput" class="form-control" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="generateProductCode()">
                                    <i class="bi bi-arrow-repeat"></i> Generate
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>Product Name *</label>
                            <input dir="rtl" type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Category</label>
                            <select name="category" class="form-select">
                                <option value="">Select Category</option>
                                ${getCategorySelectOptions('')}
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Unit</label>
                            <select name="unit" class="form-select">
                                ${unitsHtml}
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-boxes"></i> Quick Packages (Optional)</label>
                            <div id="packagesContainer"></div>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addPackageRow()">
                                <i class="bi bi-plus-circle"></i> Add Package
                            </button>
                            <small class="text-muted d-block mt-1">e.g., Dozen = 12, Tray = 30, Box = 50</small>
                        </div>
                        <div class="mb-3">
                            <label>Description</label>
                            <input type="text" name="description" class="form-control">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Initial Stock</label>
                                <input value="999999" type="number" name="current_stock" class="form-control" step="0.001">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Min Stock Alert</label>
                                <input type="number" name="min_stock_alert" class="form-control" step="0.001" value="10">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>Purchase Price (Cost) *</label>
                            <input value="0" type="number" name="purchase_price" class="form-control" step="0.01" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_product" class="btn btn-primary">Save Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>`;

    new bootstrap.Modal(document.getElementById('addProductModal')).show();
}

function generateProductCode() {
    const timestamp = Date.now().toString().slice(-8);
    const random = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
    document.getElementById('productCodeInput').value = `PRD${timestamp}${random}`;
}

// Category options
function getCategorySelectOptions(selectedCategory = '') {
    const categories = <?php
        $stmt = $pdo->query("SELECT name FROM categories ORDER BY name");
        echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
    ?>;
    let options = '';
    categories.forEach(cat => {
        const selected = (cat === selectedCategory) ? 'selected' : '';
        options += `<option value="${cat}" ${selected}>${cat}</option>`;
    });
    return options;
}

// ==================== EDIT PRODUCT ====================
async function editProduct(id) {
    const response = await fetch(`api/get_product.php?id=${id}`);
    const product = await response.json();

    if (product.error) {
        showNotification('error', product.error);
        return;
    }

    const unitOptions = await getUnitOptions(product.unit);

    const container = document.getElementById('addProductModalContainer');
    container.innerHTML = `
    <div class="modal fade" id="editProductModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form onsubmit="updateProduct(event, ${id})">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title">Edit Product: ${product.name}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Code, Name, Category, Unit, Description, Stock, Price, Status fields -->
                        <div class="mb-3">
                            <label>Product Code *</label>
                            <div class="input-group">
                                <input type="text" name="code" class="form-control" value="${product.code}" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="generateCodeForEdit()">
                                    <i class="bi bi-arrow-repeat"></i> Generate
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>Product Name *</label>
                            <input dir="rtl" type="text" name="name" class="form-control" value="${product.name}" required>
                        </div>
                        <div class="mb-3">
                            <label>Category</label>
                            <select name="category" class="form-select">
                                <option value="">Select Category</option>
                                ${getCategorySelectOptions(product.category)}
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Unit</label>
                            <select name="unit" class="form-select">${unitOptions}</select>
                        </div>
                        <div class="mb-3">
                            <label>Description</label>
                            <input type="text" name="description" class="form-control" value="${product.description || ''}">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Current Stock</label>
                                <input type="number" name="current_stock" class="form-control" step="0.001" value="${product.current_stock || 0}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Min Stock Alert</label>
                                <input type="number" name="min_stock_alert" class="form-control" step="0.001" value="${product.min_stock_alert || 10}">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>Purchase Price (Cost) *</label>
                            <input type="number" name="purchase_price" class="form-control" step="0.01" value="${product.purchase_price || 0}" required>
                        </div>
                        <div class="mb-3">
                            <label>Status</label>
                            <select name="status" class="form-select">
                                <option value="active" ${product.status == 'active' ? 'selected' : ''}>Active</option>
                                <option value="inactive" ${product.status == 'inactive' ? 'selected' : ''}>Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Update Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>`;

    new bootstrap.Modal(document.getElementById('editProductModal')).show();
}

function generateCodeForEdit() {
    const timestamp = Date.now().toString().slice(-8);
    const random = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
    document.querySelector('#editProductModal input[name="code"]').value = `PRD${timestamp}${random}`;
}

async function updateProduct(event, id) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    formData.append('id', id);
    const response = await fetch('api/update_product.php', { method: 'POST', body: formData });
    const result = await response.json();
    if (result.success) location.reload();
    else alert(result.error || 'Failed to update product');
}

async function getUnitOptions(selectedUnit) {
    try {
        const response = await fetch('api/get_units.php');
        const units = await response.json();
        let options = '';
        units.forEach(unit => {
            const selected = unit.symbol === selectedUnit ? 'selected' : '';
            options += `<option value="${unit.symbol}" ${selected}>${unit.name} (${unit.symbol})</option>`;
        });
        return options;
    } catch (e) {
        const units = ['Piece', 'kg', 'g', 'liter', 'ml', 'packet', 'dozen', 'box', 'sack'];
        let options = '';
        units.forEach(unit => {
            const selected = unit === selectedUnit ? 'selected' : '';
            options += `<option value="${unit}" ${selected}>${unit}</option>`;
        });
        return options;
    }
}

function showNotification(type, message) {
    let container = document.getElementById('notificationContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notificationContainer';
        container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999;';
        document.body.appendChild(container);
    }
    const notification = document.createElement('div');
    notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show`;
    notification.style.cssText = 'min-width: 300px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
    notification.innerHTML = `<strong>${type === 'success' ? 'Success!' : 'Error!'}</strong> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    container.appendChild(notification);
    setTimeout(() => notification.remove(), 5000);
}
</script>