<style>
    .search-result-item.selected {
        background-color: #0d6efd !important;
        color: white !important;
    }

    .search-result-item.selected small {
        color: rgba(255, 255, 255, 0.8) !important;
    }

    /* Urdu font for product names */
    /* .item-name,
    [dir="rtl"],
    .search-result-item strong {
        font-family: 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', 'Alvi Nastaleeq', serif;
        font-weight: 600;
    } */

    /* For search input placeholder */
    /* #search_product {
        font-family: 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', 'Alvi Nastaleeq', 'Segoe UI', sans-serif;
    } */

    /* For cart items */
    /* #cart_items td:first-child {
        font-family: 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', 'Alvi Nastaleeq', serif;
    } */
</style>
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

<?php if ($edit_mode > 0): ?>
    <!-- DEBUG -->
    <script>
        console.log('Edit Mode: <?php echo $edit_mode; ?>');
        console.log('Invoice Header: <?php echo $invoice_header ? 'Found' : 'NOT FOUND'; ?>');
        console.log('Items Count: <?php echo count($items ?? []); ?>');
    </script>
<?php endif; ?>


<div class="row">
    <div class="col-md-8">
        <!-- Customer Details -->
        <div class="card mb-3">
            <div class="p-2">
                <div class="row">
                    <div class="col-md-3">
                        <label>Customer Type</label>
                        <select id="customer_type" class="form-select" onchange="updateCustomerType()">
                            <option value="">-- Select Type --</option>
                            <option value="retail">🛒 Retail</option>
                            <option value="wholesale">📦 Wholesale</option>
                        </select>
                        <small id="pricing_note" class="text-success">Retail pricing applied</small>
                    </div>
                    <div class="col-md-4">
                        <label>Select Customer</label>
                        <select id="customer_select" class="form-select" onchange="onCustomerSelect(this.value)">
                            <option value="">-- Walk-in Customer --</option>
                        </select>
                        <small class="text-muted">
                            <a href="customers.php" target="_blank">Manage Customers</a>
                        </small>
                    </div>
                    <div class="col-md-5">
                        <div class="row">
                            <div class="col-md-7">
                                <label>Customer Name</label>
                                <input type="text" id="customer_name" class="form-control"
                                    placeholder="Walk-in customer" readonly style="background-color: #f8f9fa;">
                            </div>
                            <div class="col-md-5">
                                <label>Phone</label>
                                <input type="text" id="customer_phone" class="form-control" placeholder="Optional"
                                    readonly style="background-color: #f8f9fa;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Product Search -->
        <div class="card mb-3">
            <div class="p-2">
                <div class="input-group mb-3">
                    <input dir="rtl" type="text" id="search_product" class="form-control form-control-lg"
                        placeholder="تلاش کریں۔۔۔" oninput="handleSearchInput(this.value)" autofocus>
                    <button class="btn btn-outline-secondary" type="button" onclick="clearSearch()">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
                <div id="search_results" class="list-group" style="max-height: 200px; overflow-y: auto;"></div>
            </div>
        </div>

        <!-- Quick Products Grid -->
        <div class="card mb-3">

            <div class="card-body p-2">
                <div id="quick_products" class="row g-2"
                    style="max-height: 150px; overflow-y: auto; overflow-x: hidden;">
                    <!-- Loaded via AJAX -->
                    <div class="col-12 text-center py-3">
                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cart Items -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-cart"></i> Current Bill Items
                <span class="badge bg-light text-dark" id="cart_item_count">(renderCart)</span>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th width="100">Quantity</th>
                            <th width="80">Unit</th>
                            <th width="120">Unit Price</th>
                            <th width="120">Total</th>
                            <th width="50"></th>
                        </tr>
                    </thead>
                    <tbody id="cart_items">
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                No items in cart. Search and add products above.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Bill Summary -->
    <div class="col-md-4">
        <div class="card sticky-top" style="top: 20px;">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Bill Summary</h5>
            </div>

            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <td>Subtotal:</td>
                        <td class="text-end"><span id="subtotal">0.00</span></td>
                    </tr>
                    <tr>
                        <td>Discount:</td>
                        <td class="text-end">
                            <input type="number" id="discount_input" class="form-control form-control-sm d-inline-block"
                                style="width: 80px;" value="0" min="0" onchange="updateTotal()">
                            <select id="discount_type" class="form-select form-select-sm d-inline-block"
                                style="width: 70px;" onchange="updateTotal()">
                                <option value="fixed">Rs.</option>
                                <option value="percent">%</option>
                            </select>
                        </td>
                    </tr>
                    <tr class="table-primary">
                        <th>GRAND TOTAL:</th>
                        <th class="text-end">
                            <h4 id="grand_total">0.00</h4>
                        </th>
                    </tr>
                </table>

                <hr>

                <!-- Payment Received Section -->
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        <i class="bi bi-cash"></i> Amount Received
                    </label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text">Rs.</span>
                        <input type="number" id="amount_received" class="form-control text-end" style="font-size: 20px;"
                            min="0" step="1" onkeyup="calculateChange()" onchange="calculateChange()"
                            placeholder="رقم درج کریں۔۔۔">
                    </div>
                </div>

                <!-- Balance/Change Display -->
                <div id="payment_status" class="alert alert-secondary mb-3" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center">
                        <span id="status_label">Balance:</span>
                        <span id="balance_amount" class="fw-bold fs-5">Rs. 0.00</span>
                    </div>
                </div>

                <!-- Quick Amount Buttons -->
                <div class="mb-3">
                    <label class="form-label small text-muted">Quick Amount</label>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-outline-secondary btn-sm"
                            onclick="setReceivedAmount('exact')">
                            Exact
                        </button>
                    </div>
                </div>

                <hr>

                <div class="mb-3">
                    <label>Payment Method</label>
                    <select id="payment_method" class="form-select">
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="upi">UPI</option>
                        <option value="credit">Credit</option>
                    </select>
                </div>

                <!-- ACTION BUTTONS -->
                <button class="btn btn-success btn-lg w-100 mb-2" onclick="completeSale()" id="completeSaleBtn">
                    <i class="bi bi-check-circle"></i> Complete Sale (F12)
                </button>

                <div class="btn-group w-100">
                    <button class="btn btn-warning" onclick="holdInvoice()">
                        <i class="bi bi-pause-circle"></i> Save
                    </button>
                    <button class="btn btn-info" onclick="showHeldInvoices()">
                        <i class="bi bi-list-ul"></i> Held Bills
                    </button>
                    <button class="btn btn-secondary" onclick="clearCart()">
                        <i class="bi bi-trash"></i> Clear
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    let cart = [];
    let currentUnitProductId = 0;
    let customerType = '';
    let searchResults = [];
    let selectedResultIndex = -1;
    let currentProductId = 0;
    let packageCount = 0;
    function checkCustomerType() {
        const type = document.getElementById('customer_type').value;
        if (!type) {
            alert('⚠️ Please select Customer Type (Retail or Wholesale) before completing the sale!');
            document.getElementById('customer_type').focus();
            document.getElementById('customer_type').style.border = '2px solid red';
            return false;
        }
        document.getElementById('customer_type').style.border = '';
        return true;
    }
    async function addPackageRow() {
        packageCount++;
        const container = document.getElementById('packagesContainer');
        if (!container) return;

        let unitsHtml = '';
        try {
            const response = await fetch('api/get_units.php');
            const units = await response.json();
            unitsHtml = units.map(u => `<option value="${u.name}">${u.name}</option>`).join('');
        } catch (e) {
            unitsHtml = `<option value="Dozen">Dozen</option><option value="Tray">Tray</option><option value="Box">Box</option>`;
        }

        const row = document.createElement('div');
        row.className = 'input-group mb-2';
        row.id = `package_row_${packageCount}`;
        row.innerHTML = `
        <select name="package_name[]" class="form-select" required>
            <option value="">Select Unit</option>
            ${unitsHtml}
        </select>
        <span class="input-group-text">=</span>
        <input type="number" name="package_multiplier[]" class="form-control" placeholder="Qty (e.g., 12)" step="1" min="1" required>
        <button type="button" class="btn btn-outline-danger" onclick="removePackageRow(${packageCount})">
            <i class="bi bi-x"></i>
        </button>
    `;

        container.appendChild(row);
    }

    function removePackageRow(id) {
        const row = document.getElementById(`package_row_${id}`);
        if (row) row.remove();
    }

    function savePackage(productId) {
        const name = document.getElementById('new_package_name').value;
        const multiplier = document.getElementById('new_package_multiplier').value;

        if (!name || !multiplier) {
            alert('Please select a unit and enter multiplier');
            return;
        }

        const formData = new FormData();
        formData.append('product_id', productId);
        formData.append('package_name', name);
        formData.append('multiplier', multiplier);

        fetch('api/save_product_package.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('managePackagesModal')).hide();
                    managePackages(productId, document.querySelector('.modal-title').textContent.replace('Manage Packages - ', ''));
                } else {
                    alert(data.error);
                }
            });
    }

    function deletePackage(id) {
        if (!confirm('Delete this package?')) return;

        fetch(`api/delete_product_package.php?id=${id}`)
            .then(() => {
                const modal = bootstrap.Modal.getInstance(document.getElementById('managePackagesModal'));
                const productName = document.querySelector('.modal-title').textContent.replace('Manage Packages - ', '');
                modal.hide();
                managePackages(currentUnitProductId, productName);
            });
    }

    async function managePackages(productId, productName) {
        currentUnitProductId = productId;


        let unitsHtml = '';
        try {
            const response = await fetch('api/get_units.php');
            const units = await response.json();
            unitsHtml = units.map(u => `<option value="${u.name}">${u.name}</option>`).join('');
        } catch (e) {
            unitsHtml = `<option value="Dozen">Dozen</option><option value="Tray">Tray</option><option value="Box">Box</option>`;
        }


        fetch(`api/get_product_packages.php?product_id=${productId}`)
            .then(response => response.json())
            .then(packages => {
                let html = `
                <div class="modal fade" id="managePackagesModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-secondary text-white">
                                <h5 class="modal-title">
                                    <i class="bi bi-boxes"></i> Manage Packages - ${productName}
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Package</th>
                                            <th>Multiplier</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="packagesList">
            `;

                if (packages.length === 0) {
                    html += `<tr><td colspan="3" class="text-center text-muted">No packages added</td></tr>`;
                } else {
                    packages.forEach(pkg => {
                        html += `
                        <tr>
                            <td>${pkg.package_name}</td>
                            <td>${pkg.multiplier}</td>
                            <td>
                                <button class="btn btn-sm btn-danger" onclick="deletePackage(${pkg.id})">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                    });
                }

                html += `
                                </tbody>
                            </table>
                            <hr>
                            <h6>Add New Package</h6>
                            <div class="input-group mb-2">
                                <select id="new_package_name" class="form-select">
                                    <option value="">Select Unit</option>
                                    ${unitsHtml}
                                </select>
                                <span class="input-group-text">=</span>
                                <input type="number" id="new_package_multiplier" class="form-control" placeholder="Qty (e.g., 12)">
                                <button class="btn btn-primary" onclick="savePackage(${productId})">Add</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            `;

                const existingModal = document.getElementById('managePackagesModal');
                if (existingModal) existingModal.remove();

                document.body.insertAdjacentHTML('beforeend', html);
                new bootstrap.Modal(document.getElementById('managePackagesModal')).show();
            });
    }



    <?php if ($editing_invoice): ?>
            (async function () {
                console.log('Edit mode activated for invoice: <?php echo $editing_invoice['invoice_no']; ?>');

                customerType = '<?php echo $editing_invoice['customer_type']; ?>';
                document.getElementById('customer_type').value = customerType;
                updatePricingNote();

                document.getElementById('customer_name').value = '<?php echo addslashes($editing_invoice['customer_name'] ?? ''); ?>';
                document.getElementById('customer_phone').value = '<?php echo addslashes($editing_invoice['customer_phone'] ?? ''); ?>';

                const rawCart = <?php echo json_encode($editing_invoice['items']); ?>;
                cart = [];

                for (const item of rawCart) {
                    let productUnit = 'Piece';
                    try {
                        const response = await fetch(`api/get_product.php?id=${item.product_id}`);
                        const product = await response.json();
                        productUnit = product.unit || 'Piece';
                    } catch (e) { }

                    cart.push({
                        product_id: parseInt(item.product_id) || 0,
                        product_name: item.product_name,
                        base_unit: productUnit,
                        display_unit: productUnit,
                        actual_quantity: parseFloat(item.quantity) || 0,
                        display_quantity: parseFloat(item.quantity) || 0,
                        unit_price: parseFloat(item.unit_price) || 0,
                        total_price: parseFloat(item.total_price) || 0,
                        tier_info: item.tier_info || '',
                        max_stock: 999999
                    });
                }

                console.log('Loaded cart items:', cart.length);

                await renderCart();
                updateTotal();

                const completeBtn = document.querySelector('button[onclick="completeSale()"]');
                if (completeBtn) {
                    completeBtn.innerHTML = '<i class="bi bi-check-circle"></i> Update Invoice (F12)';
                    completeBtn.className = 'btn btn-warning btn-lg w-100 mb-2';
                    completeBtn.setAttribute('onclick', 'completeSale()');
                }

                const banner = document.createElement('div');
                banner.className = 'alert alert-warning mb-3';
                banner.innerHTML = `
        <i class="bi bi-pencil-square"></i> 
        <strong>Editing Invoice:</strong> <?php echo $editing_invoice['invoice_no']; ?> 
        <small class="ms-2">(Changes will create a new invoice version)</small>
        <button type="button" class="btn-close float-end" onclick="this.parentElement.remove()"></button>
    `;
                const mainContainer = document.querySelector('main .row') || document.querySelector('main');
                if (mainContainer) {
                    mainContainer.insertBefore(banner, mainContainer.firstChild);
                }

                window.EDIT_MODE = true;
                window.OLD_INVOICE_ID = <?php echo $editing_invoice['id']; ?>;


                window.originalCompleteSale = completeSale;
                completeSale = async function () {
                    await updateExistingInvoice(window.OLD_INVOICE_ID);
                };

                const paymentSection = document.getElementById('payment_method').closest('.mb-3');
                if (paymentSection) paymentSection.style.display = 'none';

            })();
    <?php endif; ?>


    async function updateExistingInvoice(oldInvoiceId) {
        if (cart.length === 0) {
            showNotification('error', 'Cart is empty!');
            return;
        }
        const subtotal = cart.reduce((sum, item) => sum + (item.total_price || 0), 0);
        const discountInput = parseFloat(document.getElementById('discount_input').value) || 0;
        const discountType = document.getElementById('discount_type').value;
        let discount = 0;
        if (discountType === 'percent') {
            discount = subtotal * (discountInput / 100);
        } else {
            discount = discountInput;
        }
        const total = Math.max(0, subtotal - discount);
        const invoiceData = {
            action: 'update_invoice',
            old_invoice_id: oldInvoiceId,
            customer_name: document.getElementById('customer_name').value,
            customer_phone: document.getElementById('customer_phone').value,
            customer_type: customerType,
            subtotal: subtotal,
            discount: discount,
            total: total,
            payment_method: 'cash',
            items: cart.map(item => ({
                product_id: item.product_id,
                product_name: item.product_name,
                quantity: item.actual_quantity || item.quantity || 0,
                unit_price: item.unit_price || 0,
                total_price: item.total_price || 0,
                tier_info: item.tier_info || ''
            }))
        };
        try {
            const response = await fetch('api/edit_invoice.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(invoiceData)
            });
            const result = await response.json();
            if (result.success) {
                window.open(`api/print_receipt.php?id=${result.invoice_id}`, '_blank');
                showNotification('success', result.message || 'Invoice updated!');
                setTimeout(() => { window.location.href = 'dashboard.php'; }, 2000);
            } else {
                showNotification('error', result.error || 'Failed to update invoice');
            }
        } catch (error) {
            showNotification('error', 'Error updating invoice');
        }
    }

    function calculateChange() {
        const total = getGrandTotal();
        const received = parseFloat(document.getElementById('amount_received').value) || 0;

        const statusDiv = document.getElementById('payment_status');
        const statusLabel = document.getElementById('status_label');
        const balanceAmount = document.getElementById('balance_amount');
        const completeBtn = document.getElementById('completeSaleBtn');

        if (received <= 0) {
            statusDiv.style.display = 'none';
            completeBtn.disabled = false;
            completeBtn.innerHTML = '<i class="bi bi-check-circle"></i> Complete Sale (F12)';
            return;
        }

        statusDiv.style.display = 'block';

        if (received >= total) {

            const change = received - total;
            statusDiv.className = 'alert alert-success mb-3';
            statusLabel.textContent = '💵 Change to Return:';
            statusLabel.className = 'text-success';
            balanceAmount.textContent = `Rs. ${change.toFixed(0)}`;
            balanceAmount.className = 'fw-bold fs-5 text-success';
            completeBtn.disabled = false;
            completeBtn.innerHTML = '<i class="bi bi-check-circle"></i> Complete Sale (F12)';
        } else {

            const due = total - received;
            statusDiv.className = 'alert alert-danger mb-3';
            statusLabel.textContent = '⚠️ Balance Due:';
            statusLabel.className = 'text-danger';
            balanceAmount.textContent = `Rs. ${due.toFixed(0)}`;
            balanceAmount.className = 'fw-bold fs-5 text-danger';
            completeBtn.disabled = true;
            completeBtn.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Insufficient Payment';
        }
    }


    function getGrandTotal() {
        const totalText = document.getElementById('grand_total').textContent;
        return parseFloat(totalText.replace('Rs.', '').replace(',', '').trim()) || 0;
    }


    function setReceivedAmount(type, value = 0) {
        const total = getGrandTotal();
        const receivedInput = document.getElementById('amount_received');

        if (type === 'exact') {
            receivedInput.value = total;
        } else if (type === 'round') {
            receivedInput.value = Math.ceil(total / value) * value;
        }

        calculateChange();
        receivedInput.focus();
    }


    const originalUpdateTotal = updateTotal;
    updateTotal = function () {
        originalUpdateTotal();
        calculateChange();


        const total = getGrandTotal();
        const exactBtn = document.querySelector('button[onclick="setReceivedAmount(\'exact\')"]');
        if (exactBtn) {
            exactBtn.textContent = `Exact (Rs. ${total.toFixed(0)})`;
        }
    };


    const originalCompleteSale = completeSale;
    completeSale = async function () {
        const total = getGrandTotal();
        const received = parseFloat(document.getElementById('amount_received').value) || 0;
        const paymentMethod = document.getElementById('payment_method').value;


        if (paymentMethod !== 'cash') {
            document.getElementById('amount_received').value = total;
        } else if (received < total) {
            showNotification('error', `Insufficient payment! Balance due: Rs. ${(total - received).toFixed(0)}`);
            document.getElementById('amount_received').focus();
            return;
        }

        await originalCompleteSale();
    };


    const originalClearCart = clearCart;
    clearCart = function () {
        if (confirm('Clear all items from cart?')) {
            originalClearCart();
            document.getElementById('amount_received').value = '0';
            document.getElementById('payment_status').style.display = 'none';
        }
    };
    function updateCustomerType() {
        customerType = document.getElementById('customer_type').value;
        const note = document.getElementById('pricing_note');


        if (!customerType) {
            note.innerHTML = '⚠️ Please select Retail or Wholesale';
            note.className = 'text-danger';
            document.getElementById('customer_type').style.border = '2px solid red';
            return;
        }


        document.getElementById('customer_type').style.border = '';

        if (customerType === 'wholesale') {
            note.innerHTML = '📦 Wholesale pricing applied';
            note.className = 'text-primary';
        } else {
            note.innerHTML = '🛒 Retail pricing applied';
            note.className = 'text-success';
        }

        if (cart.length > 0) {
            recalculateCartPrices();
        }
    }
    async function recalculateCartPrices() {
        for (let item of cart) {
            if (!item || !item.product_id) continue;

            const actualQty = item.actual_quantity || item.quantity || 0;
            const productId = item.product_id;

            if (productId <= 0 || actualQty <= 0) continue;



            let correctBaseUnit = item.base_unit || 'Piece';
            try {
                const response = await fetch(`api/get_product.php?id=${productId}`);
                const product = await response.json();
                correctBaseUnit = product.unit || 'Piece';
            } catch (e) {
                console.error('Failed to fetch product unit:', e);
            }


            const savedDisplayUnit = item.display_unit || correctBaseUnit;
            const savedDisplayQty = item.display_quantity || actualQty;

            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('quantity', actualQty);
            formData.append('customer_type', customerType);

            try {
                const response = await fetch('api/get_price.php', {
                    method: 'POST',
                    body: formData
                });

                const text = await response.text();
                if (text.startsWith('<')) continue;

                const priceData = JSON.parse(text);


                item.unit_price = priceData.unit_price || 0;
                item.total_price = priceData.total_price || 0;
                item.tier_info = priceData.tier_info || '';


                item.base_unit = correctBaseUnit;
                item.display_unit = savedDisplayUnit;
                item.display_quantity = savedDisplayQty;

            } catch (error) {
                console.error('Recalculate error:', error);
            }
        }

        renderCart();
        updateTotal();
    }

    async function addToCartWithQuantity(productId, productName, unit, maxStock, quantity) {
        const formData = new FormData();
        formData.append('product_id', productId);
        formData.append('quantity', quantity);
        formData.append('customer_type', customerType);

        try {
            const response = await fetch('api/get_price.php', { method: 'POST', body: formData });
            const text = await response.text();

            if (text.startsWith('<')) {
                console.error('API returned HTML');
                return;
            }

            const priceData = JSON.parse(text);

            const existingIndex = cart.findIndex(item =>
                item.product_id === productId && item.display_unit === unit
            );

            if (existingIndex >= 0) {
                const item = cart[existingIndex];
                const newQty = (item.actual_quantity || 0) + quantity;

                if (newQty > maxStock) {
                    alert(`Only ${maxStock} ${unit} available!`);
                    return;
                }

                item.actual_quantity = newQty;
                item.display_quantity = (item.display_quantity || 0) + quantity;
                item.total_price = priceData.total_price || 0;
                item.unit_price = priceData.unit_price || 0;
                item.tier_info = priceData.tier_info || '';
            } else {
                cart.unshift({
                    product_id: productId,
                    product_name: productName,
                    base_unit: unit,
                    display_unit: unit,
                    actual_quantity: quantity,
                    display_quantity: quantity,
                    unit_price: priceData.unit_price || 0,
                    total_price: priceData.total_price || 0,
                    tier_info: priceData.tier_info || '',
                    max_stock: maxStock
                });
            }

            renderCart();
            updateTotal();
        } catch (error) {
            console.error('Add to cart error:', error);
        }
    }

    async function searchProduct(query) {
        if (query.length < 2) {
            document.getElementById('search_results').innerHTML = '';
            searchResults = [];
            selectedResultIndex = -1;
            return;
        }

        try {
            const response = await fetch(`api/search_product.php?q=${encodeURIComponent(query)}&customer_type=${customerType}`);
            const products = await response.json();

            searchResults = products;



            if (!window.isNavigating) {
                selectedResultIndex = products.length > 0 ? 0 : -1;
            }
            window.isNavigating = false;

            let html = '';
            products.forEach((product, index) => {
                html += `
            <a href="#" class="list-group-item list-group-item-action search-result-item ${index === selectedResultIndex ? 'selected' : ''}" 
               data-index="${index}"
               data-id="${product.id}"
               data-name="${product.name.replace(/"/g, '&quot;')}"
               data-unit="${product.unit}"
               data-stock="${product.current_stock}"
               onclick="selectProductByClick(${index}); return false;">
                <div class="d-flex justify-content-between">
                    <strong>${escapeHtml(product.name)}</strong>
                    <span class="badge bg-secondary">${product.code}</span>
                </div>
                <small>Stock: ${product.current_stock} ${product.unit} | Price: </small>
            </a>
        `;
            });

            document.getElementById('search_results').innerHTML = html;
        } catch (error) {
            console.error('Search error:', error);
        }
    }

    async function addToCart(productId, productName, unit, maxStock) {
        const quantity = prompt(`Enter quantity for ${productName} (${unit}):`, '1');
        if (!quantity || quantity <= 0) return;
        if (parseFloat(quantity) > maxStock) {
            alert(`Only ${maxStock} ${unit} available in stock!`);
            return;
        }

        await addToCartWithQuantity(productId, productName, unit, maxStock, quantity);

        renderCart();
        updateTotal();
        document.getElementById('search_product').value = '';
        document.getElementById('search_results').innerHTML = '';
    }

    async function updateItemPrice(index, newQuantity) {
        const item = cart[index];
        const formData = new FormData();
        formData.append('product_id', item.product_id);
        formData.append('quantity', newQuantity);
        formData.append('customer_type', customerType);

        const response = await fetch('api/get_price.php', {
            method: 'POST',
            body: formData
        });
        const priceData = await response.json();

        if (priceData.error) {
            alert(priceData.error);
            return;
        }

        cart[index].quantity = parseFloat(newQuantity);
        cart[index].unit_price = priceData.unit_price;
        cart[index].total_price = priceData.total_price;
        cart[index].tier_info = priceData.tier_info || `${priceData.tier_min} - ${priceData.tier_max || '∞'} ${item.unit}`;
    }




    async function renderCart() {
        const tbody = document.getElementById('cart_items');
        const countEl = document.getElementById('cart_item_count');
        if (countEl) countEl.textContent = `${cart.length} item${cart.length !== 1 ? 's' : ''}`;

        if (cart.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No items in cart.</td></tr>';
            return;
        }

        let html = '';
        for (let i = 0; i < cart.length; i++) {
            const item = cart[i];


            const productName = item.product_name || 'Unknown';
            const displayQty = item.display_quantity || item.quantity || 1;
            const displayUnit = item.display_unit || item.base_unit || 'Piece';
            const baseUnit = item.base_unit || 'Piece';
            const totalPrice = item.total_price || 0;
            const unitPrice = totalPrice / displayQty;
            const tierInfo = item.tier_info || '';
            const productId = item.product_id || 0;

            let packageOptions = `<option value="${baseUnit}" data-multiplier="1">${baseUnit}</option>`;

            if (productId > 0) {
                try {
                    const response = await fetch(`api/get_product_packages.php?product_id=${productId}`);
                    const text = await response.text();
                    if (!text.startsWith('<')) {
                        const packages = JSON.parse(text);
                        packages.forEach(pkg => {
                            const selected = (displayUnit === pkg.package_name) ? 'selected' : '';
                            packageOptions += `<option value="${pkg.package_name}" data-multiplier="${pkg.multiplier}" ${selected}>${pkg.package_name}</option>`;
                        });
                    }
                } catch (e) {
                    console.error('Failed to load packages:', e);
                }
            }

            html += `
            <tr>
                <td><strong>${productName}</strong><br><small>${tierInfo}</small></td>
                <td><input type="number" class="form-control form-control-sm" value="${displayQty}" step="1" min="0" onchange="updateCartItemQuantity(${i}, this.value)"></td>
                <td>
                    <select class="form-select form-select-sm" onchange="changeItemPackage(${i}, this.value, this.options[this.selectedIndex].dataset.multiplier)">
                        ${packageOptions}
                    </select>
                </td>
                <td class="text-end">Rs. ${unitPrice.toFixed(2)}</td>
                <td class="text-end">Rs. ${totalPrice.toFixed(2)}</td>
                <td><button class="btn btn-sm btn-danger" onclick="removeFromCart(${i})"><i class="bi bi-x"></i></button></td>
            </tr>
        `;
        }
        tbody.innerHTML = html;
    }

    async function changeItemPackage(index, packageName, multiplier) {
        const item = cart[index];
        if (!item) return;

        const newMultiplier = parseFloat(multiplier) || 1;
        const displayQty = item.display_quantity || item.quantity || 1;
        const newActualQty = displayQty * newMultiplier;
        const maxStock = item.max_stock || 999999;
        const baseUnit = item.base_unit || 'Piece';

        if (newActualQty > maxStock) {
            alert(`Only ${maxStock} ${baseUnit} available!`);
            renderCart();
            return;
        }

        const formData = new FormData();
        formData.append('product_id', item.product_id);
        formData.append('quantity', newActualQty);
        formData.append('customer_type', customerType);

        try {
            const response = await fetch('api/get_price.php', { method: 'POST', body: formData });
            const priceData = await response.json();

            item.display_unit = packageName;
            item.actual_quantity = newActualQty;
            item.unit_price = priceData.unit_price || 0;
            item.total_price = priceData.total_price || 0;
            item.tier_info = priceData.tier_info || '';

            renderCart();
            updateTotal();
        } catch (error) {
            console.error('Change package error:', error);
        }
    }

    async function updateCartItemQuantity(index, newDisplayQty) {
        if (newDisplayQty <= 0) {
            removeFromCart(index);
            return;
        }

        const item = cart[index];
        if (!item) return;

        const oldDisplayQty = item.display_quantity || item.quantity || 1;
        const oldActualQty = item.actual_quantity || oldDisplayQty;
        const ratio = oldActualQty / oldDisplayQty;
        const newActualQty = newDisplayQty * ratio;
        const maxStock = item.max_stock || 999999;
        const baseUnit = item.base_unit || 'Piece';

        if (newActualQty > maxStock) {
            alert(`Only ${maxStock} ${baseUnit} available!`);
            renderCart();
            return;
        }

        const formData = new FormData();
        formData.append('product_id', item.product_id);
        formData.append('quantity', newActualQty);
        formData.append('customer_type', customerType);

        try {
            const response = await fetch('api/get_price.php', { method: 'POST', body: formData });
            const priceData = await response.json();

            item.actual_quantity = newActualQty;
            item.display_quantity = parseFloat(newDisplayQty);
            item.unit_price = priceData.unit_price || 0;
            item.total_price = priceData.total_price || 0;
            item.tier_info = priceData.tier_info || '';

            renderCart();
            updateTotal();
        } catch (error) {
            console.error('Update quantity error:', error);
        }
    }

    function cleanCartData(items) {
        return items.map(item => ({
            product_id: parseInt(item.product_id) || 0,
            product_name: item.product_name,
            quantity: parseFloat(item.quantity) || 0,
            unit_price: parseFloat(item.unit_price) || 0,
            total_price: parseFloat(item.total_price) || 0,
            tier_info: item.tier_info || '',
            unit: item.unit || 'piece'
        }));
    }


    function removeFromCart(index) {
        cart.splice(index, 1);
        renderCart();
        updateTotal();
    }

    function updateTotal() {
        const subtotal = cart.reduce((sum, item) => sum + item.total_price, 0);
        const discountInput = parseFloat(document.getElementById('discount_input').value) || 0;
        const discountType = document.getElementById('discount_type').value;

        let discount = 0;
        if (discountType === 'percent') {
            discount = subtotal * (discountInput / 100);
        } else {
            discount = discountInput;
        }

        const total = Math.max(0, subtotal - discount);

        document.getElementById('subtotal').textContent = `Rs. ${subtotal.toFixed(2)}`;
        document.getElementById('grand_total').textContent = `Rs. ${total.toFixed(2)}`;
    }

    async function completeSale() {
        if (!checkCustomerType()) return;
        if (cart.length === 0) {
            alert('Cart is empty!');
            return;
        }

        const subtotal = cart.reduce((sum, item) => sum + (item.total_price || 0), 0);
        const discountInput = parseFloat(document.getElementById('discount_input').value) || 0;
        const discountType = document.getElementById('discount_type').value;

        let discount = 0;
        if (discountType === 'percent') {
            discount = subtotal * (discountInput / 100);
        } else {
            discount = discountInput;
        }

        const total = Math.max(0, subtotal - discount);

        const invoiceData = {
            customer_name: document.getElementById('customer_name').value,
            customer_phone: document.getElementById('customer_phone').value,
            customer_type: customerType,
            subtotal: subtotal,
            discount: discount,
            total: total,
            payment_method: document.getElementById('payment_method').value,
            items: cart.map(item => ({
                product_id: item.product_id,
                product_name: item.product_name,
                quantity: item.actual_quantity || item.quantity || 0,
                unit_price: item.unit_price || 0,
                total_price: item.total_price || 0,
                tier_info: item.tier_info || '',
                display_unit: item.display_unit || item.base_unit,
                display_quantity: item.display_quantity || item.quantity
            }))
        };

        const response = await fetch('api/save_invoice.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(invoiceData)
        });

        const result = await response.json();

        if (result.success) {
            window.open(`api/print_receipt.php?id=${result.invoice_id}`, '_blank');

            cart = [];
            renderCart();
            updateTotal();
            customerType = '';
            document.getElementById('customer_type').value = '';
            document.getElementById('customer_name').value = '';
            document.getElementById('customer_phone').value = '';
            document.getElementById('amount_received').value = '0';
            document.getElementById('payment_status').style.display = 'none';
            document.getElementById('discount_input').value = '0';

            showNotification('success', `Invoice ${result.invoice_no} completed!`);
        } else {
            alert('Error: ' + result.error);
        }
    }

    function editInvoice(invoiceId) {
        if (confirm('Edit this invoice? A new version will be created.')) {

            window.location.href = 'billing.php?edit=' + invoiceId;
        }
    }
    function clearCart() {
        if (confirm('Clear all items from cart?')) {
            cart = [];
            renderCart();
            updateTotal();
        }
    }

    function updatePricingNote() {
        const note = document.getElementById('pricing_note');
        if (customerType === 'wholesale') {
            note.innerHTML = '📦 Wholesale pricing applied (Bulk rates active)';
            note.className = 'text-primary';
        } else {
            note.innerHTML = '🛒 Retail pricing applied';
            note.className = 'text-success';
        }
    }

    async function loadQuickProducts() {
        try {
            const response = await fetch('api/search_product.php?mode=popular');
            const products = await response.json();

            let html = '';

            if (products.length === 0) {
                html = '<div class="col-12 text-muted text-center py-3">No products available</div>';
            } else {
                products.forEach(product => {

                    const salesBadge = product.sales_count > 0
                        ? `<br><small class="text-success"><i class="bi bi-star-fill"></i> ${product.sales_count} sold</small>`
                        : '';

                    html += `
                    <div class="col-3 mb-2">
                        <button class="btn btn-outline-primary w-100 h-100" 
                                onclick="addToCart(${product.id}, '${product.name.replace(/'/g, "\\'")}', '${product.unit}', ${product.current_stock})"
                                style="min-height: 60px;">
                            <strong>${product.name}</strong>
                            ${salesBadge}
                        </button>
                    </div>
                `;
                });
            }

            document.getElementById('quick_products').innerHTML = html;

        } catch (error) {
            console.error('Error loading quick products:', error);
            document.getElementById('quick_products').innerHTML =
                '<div class="col-12 text-danger text-center py-3">Failed to load products</div>';
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
        notification.innerHTML = `
        <strong>${type === 'success' ? 'Success!' : 'Error!'}</strong> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
        container.appendChild(notification);
        setTimeout(() => notification.remove(), 5000);
    }
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    async function holdInvoice() {
        if (cart.length === 0) {
            showNotification('error', 'Cart is empty!');
            return;
        }

        const subtotal = cart.reduce((sum, item) => sum + item.total_price, 0);
        const discountInput = parseFloat(document.getElementById('discount_input').value) || 0;
        const discountType = document.getElementById('discount_type').value;

        let discount = 0;
        if (discountType === 'percent') {
            discount = subtotal * (discountInput / 100);
        } else {
            discount = discountInput;
        }

        const total = Math.max(0, subtotal - discount);

        const holdData = {
            action: 'save',
            customer_name: document.getElementById('customer_name').value,
            customer_phone: document.getElementById('customer_phone').value,
            customer_type: customerType,
            cart: cart,
            subtotal: subtotal,
            discount: discount,
            total: total
        };

        try {
            const response = await fetch('api/hold_invoice.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(holdData)
            });

            const result = await response.json();

            if (result.success) {
                showNotification('success', `Invoice held! Ref: ${result.hold_ref}`);


                cart = [];
                renderCart();
                updateTotal();
                document.getElementById('customer_name').value = '';
                document.getElementById('customer_phone').value = '';
                document.getElementById('discount_input').value = '0';
            } else {
                showNotification('error', 'Failed to hold invoice');
            }
        } catch (error) {
            showNotification('error', 'Error holding invoice');
        }
    }


    async function showHeldInvoices() {
        try {
            const response = await fetch('api/hold_invoice.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'list' })
            });

            const result = await response.json();

            if (!result.success) {
                showNotification('error', 'Failed to load held invoices');
                return;
            }

            const modalHtml = `
            <div class="modal fade" id="heldInvoicesModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-info text-white">
                            <h5 class="modal-title"><i class="bi bi-pause-circle"></i> Held Invoices</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            ${result.held_invoices.length === 0 ?
                    '<p class="text-center text-muted py-4">No held invoices</p>' :
                    `<table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Reference</th>
                                            <th>Customer</th>
                                            <th>Type</th>
                                            <th>Items</th>
                                            <th>Total</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${result.held_invoices.map(inv => {
                        const cartData = JSON.parse(inv.cart_data);
                        return `
                                                <tr>
                                                    <td><strong>${inv.hold_reference}</strong><br><small>${new Date(inv.created_at).toLocaleString()}</small></td>
                                                    <td>${inv.customer_name || 'Walk-in'}</td>
                                                    <td><span class="badge bg-${inv.customer_type === 'wholesale' ? 'success' : 'info'}">${inv.customer_type}</span></td>
                                                    <td>${cartData.length} items</td>
                                                    <td><strong>Rs. ${parseFloat(inv.total_amount).toFixed(2)}</strong></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-success" onclick="resumeHeldInvoice(${inv.id})">
                                                            <i class="bi bi-play-circle"></i> Resume
                                                        </button>
                                                        <button class="btn btn-sm btn-danger" onclick="deleteHeldInvoice(${inv.id})">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            `;
                    }).join('')}
                                    </tbody>
                                </table>`
                }
                        </div>
                    </div>
                </div>
            </div>
        `;


            const existingModal = document.getElementById('heldInvoicesModal');
            if (existingModal) existingModal.remove();

            document.body.insertAdjacentHTML('beforeend', modalHtml);
            new bootstrap.Modal(document.getElementById('heldInvoicesModal')).show();

        } catch (error) {
            showNotification('error', 'Error loading held invoices');
        }
    }


    async function resumeHeldInvoice(holdId) {
        try {
            const response = await fetch('api/hold_invoice.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get', hold_id: holdId })
            });

            const result = await response.json();

            if (result.success) {
                const inv = result.invoice;


                document.getElementById('customer_name').value = inv.customer_name || '';
                document.getElementById('customer_phone').value = inv.customer_phone || '';
                document.getElementById('customer_type').value = inv.customer_type;
                customerType = inv.customer_type;


                cart = inv.cart_data;
                renderCart();
                updateTotal();


                bootstrap.Modal.getInstance(document.getElementById('heldInvoicesModal')).hide();

                showNotification('success', 'Invoice resumed!');
            }
        } catch (error) {
            showNotification('error', 'Error resuming invoice');
        }
    }
    function handleSearchInput(query) {

        if (window.isNavigating) {
            return;
        }
        searchProduct(query);
    }

    document.getElementById('search_product').addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {


            e.preventDefault();
        }
    });


    async function deleteHeldInvoice(holdId) {
        if (!confirm('Delete this held invoice permanently?')) return;

        try {
            await fetch('api/hold_invoice.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', hold_id: holdId })
            });


            bootstrap.Modal.getInstance(document.getElementById('heldInvoicesModal')).hide();
            showHeldInvoices();

            showNotification('success', 'Held invoice deleted!');
        } catch (error) {
            showNotification('error', 'Error deleting invoice');
        }
    }
    loadQuickProducts();
    updateTotal();

    let hasUnsavedChanges = false;


    const originalRenderCart2 = renderCart;
    renderCart = function () {
        originalRenderCart2();
        hasUnsavedChanges = cart.length > 0;
    };


    const originalAddToCart = addToCart;






    const originalRemoveFromCart = removeFromCart;
    removeFromCart = function (index) {
        originalRemoveFromCart(index);
        hasUnsavedChanges = cart.length > 0;
    };


    const originalClearCart2 = clearCart;
    clearCart = function () {
        if (confirm('Clear all items from cart?')) {
            originalClearCart2();
            hasUnsavedChanges = false;
        }
    };


    window.addEventListener('beforeunload', function (e) {
        if (hasUnsavedChanges && cart.length > 0) {

            const message = 'You have items in your cart. Are you sure you want to leave?';
            e.preventDefault();
            e.returnValue = message;
            return message;
        }
    });


    const originalCompleteSale3 = completeSale;
    completeSale = async function () {
        await originalCompleteSale3();
        hasUnsavedChanges = false;
    };


    const originalHoldInvoice = holdInvoice;
    holdInvoice = async function () {
        await originalHoldInvoice();
        hasUnsavedChanges = false;
    };


    document.addEventListener('click', function (e) {
        const link = e.target.closest('a');
        if (link && hasUnsavedChanges && cart.length > 0) {
            const href = link.getAttribute('href');

            if (href && !href.startsWith('javascript:') && !href.startsWith('#')) {
                if (!confirm('You have items in your cart. Leave without saving?')) {
                    e.preventDefault();
                    return false;
                } else {
                    hasUnsavedChanges = false;
                }
            }
        }
    });

    function highlightSelectedResult() {
        const items = document.querySelectorAll('.search-result-item');
        items.forEach((item, index) => {
            if (index === selectedResultIndex) {
                item.classList.add('selected');
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('selected');
            }
        });
    }

    function selectProductByClick(index) {
        selectedResultIndex = index;
        const product = searchResults[index];
        if (product) {
            selectProductFromSearch(product);
        }
    }



    async function selectProductFromSearch(product) {
        if (!product) return;


        try {
            const response = await fetch(`api/get_product_packages.php?product_id=${product.id}`);
            const packages = await response.json();

            let promptMessage = `Enter quantity for ${product.name} (${product.unit}):`;

            if (packages.length > 0) {
                promptMessage += '\n\nQuick Packages:\n';
                packages.forEach(pkg => {
                    promptMessage += `  • ${pkg.package_name} = ${pkg.multiplier} ${product.unit}\n`;
                });
                promptMessage += '\nYou can enter any number or use package values.';
            }

            const quantity = prompt(promptMessage, '1');
            if (!quantity || quantity <= 0) return;

            if (parseFloat(quantity) > product.current_stock) {
                alert(`Only ${product.current_stock} ${product.unit} available in stock!`);
                return;
            }

            await addToCartWithQuantity(
                product.id,
                product.name,
                product.unit,
                product.current_stock,
                parseFloat(quantity)
            );

            renderCart();
            updateTotal();


            document.getElementById('search_product').value = '';
            document.getElementById('search_results').innerHTML = '';
            searchResults = [];
            selectedResultIndex = -1;

            document.getElementById('search_product').focus();

        } catch (error) {

            const quantity = prompt(`Enter quantity for ${product.name} (${product.unit}):`, '1');
            if (!quantity || quantity <= 0) return;

            await addToCartWithQuantity(
                product.id,
                product.name,
                product.unit,
                product.current_stock,
                parseFloat(quantity)
            );

            renderCart();
            updateTotal();
            document.getElementById('search_product').value = '';
            document.getElementById('search_results').innerHTML = '';
            searchResults = [];
            selectedResultIndex = -1;
            document.getElementById('search_product').focus();
        }
    }
    function clearSearch() {
        document.getElementById('search_product').value = '';
        document.getElementById('search_results').innerHTML = '';
        searchResults = [];
        selectedResultIndex = -1;
        document.getElementById('search_product').focus();
    }












































































































    document.addEventListener('keydown', function (e) {
        const searchInput = document.getElementById('search_product');
        const isSearchFocused = document.activeElement === searchInput;
        const resultItems = document.querySelectorAll('.search-result-item');
        const tagName = e.target.tagName;
        const isInput = tagName === 'INPUT' || tagName === 'SELECT' || tagName === 'TEXTAREA';
        const modalOpen = document.querySelector('.modal.show') !== null;


        if (e.key === 'F12') {
            e.preventDefault();
            completeSale();
            return;
        }


        if (e.key === 'Escape') {
            if (isSearchFocused) {
                e.preventDefault();
                clearSearch();
            }
            return;
        }



        if (isSearchFocused && resultItems.length > 0) {

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                e.stopPropagation();


                window.isNavigating = true;


                if (selectedResultIndex >= 0 && resultItems[selectedResultIndex]) {
                    resultItems[selectedResultIndex].classList.remove('selected');
                }


                if (selectedResultIndex < resultItems.length - 1) {
                    selectedResultIndex++;
                }


                if (selectedResultIndex >= 0 && resultItems[selectedResultIndex]) {
                    resultItems[selectedResultIndex].classList.add('selected');
                    resultItems[selectedResultIndex].scrollIntoView({ block: 'nearest' });
                }


                setTimeout(() => { window.isNavigating = false; }, 100);
                return;
            }

            if (e.key === 'ArrowUp') {
                e.preventDefault();
                e.stopPropagation();


                window.isNavigating = true;


                if (selectedResultIndex >= 0 && resultItems[selectedResultIndex]) {
                    resultItems[selectedResultIndex].classList.remove('selected');
                }


                if (selectedResultIndex > 0) {
                    selectedResultIndex--;
                }


                if (selectedResultIndex >= 0 && resultItems[selectedResultIndex]) {
                    resultItems[selectedResultIndex].classList.add('selected');
                    resultItems[selectedResultIndex].scrollIntoView({ block: 'nearest' });
                }


                setTimeout(() => { window.isNavigating = false; }, 100);
                return;
            }

            if (e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation();

                if (selectedResultIndex >= 0 && selectedResultIndex < searchResults.length) {
                    const product = searchResults[selectedResultIndex];
                    if (product) {
                        selectProductFromSearch(product);
                    }
                }
                return;
            }
        }




        if (!isInput && !modalOpen) {

            const isRegularKey = e.key.length === 1 && !e.altKey && !e.ctrlKey && !e.metaKey;


            const isNavigationKey = ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Enter', 'Escape', 'Tab'].includes(e.key);

            if (isRegularKey && !isNavigationKey) {

                if (!isSearchFocused) {
                    e.preventDefault();
                    searchInput.focus();

                    setTimeout(() => {
                        searchInput.value = e.key;

                        searchProduct(e.key);
                    }, 10);
                }
            }
        }
    });

    async function searchCustomer(query) {
        if (query.length < 2) {
            document.getElementById('customer_results').innerHTML = '';
            document.getElementById('customer_results').style.display = 'none';
            return;
        }

        try {
            const response = await fetch(`api/search_customers.php?q=${encodeURIComponent(query)}`);
            const customers = await response.json();

            let html = '';
            customers.forEach(customer => {
                html += `
                <a href="#" class="list-group-item list-group-item-action" onclick="selectCustomer(${customer.id}, '${customer.name.replace(/'/g, "\\'")}', '${customer.phone || ''}', '${customer.customer_type}'); return false;">
                    <div class="d-flex justify-content-between">
                        <strong>${customer.name}</strong>
                        <span class="badge bg-${customer.customer_type === 'wholesale' ? 'success' : 'info'}">${customer.customer_type}</span>
                    </div>
                    ${customer.phone ? `<small>📞 ${customer.phone}</small>` : ''}
                </a>
            `;
            });

            const resultsDiv = document.getElementById('customer_results');
            resultsDiv.innerHTML = html;
            resultsDiv.style.display = customers.length > 0 ? 'block' : 'none';

        } catch (error) {
            console.error('Customer search error:', error);
        }
    }


    function selectCustomer(id, name, phone, type) {
        document.getElementById('customer_name').value = name;
        document.getElementById('customer_phone').value = phone;
        document.getElementById('customer_type').value = type;
        customerType = type;
        updatePricingNote();


        document.getElementById('customer_search').value = '';
        document.getElementById('customer_results').innerHTML = '';
        document.getElementById('customer_results').style.display = 'none';


        if (cart.length > 0) {
            recalculateCartPrices();
        }

        showNotification('success', `Customer: ${name} selected`);
    }


    function clearCustomerSearch() {
        document.getElementById('customer_search').value = '';
        document.getElementById('customer_results').innerHTML = '';
        document.getElementById('customer_results').style.display = 'none';
    }


    const originalCompleteSale4 = completeSale;
    completeSale = async function () {

        await originalCompleteSale4();
    };

    async function loadCustomers() {
        try {
            const response = await fetch('api/get_customers.php');
            const customers = await response.json();

            let options = '<option value="">-- Walk-in Customer --</option>';
            customers.forEach(customer => {
                options += `<option value="${customer.id}" data-name="${customer.name.replace(/"/g, '&quot;')}" data-phone="${customer.phone || ''}" data-type="${customer.customer_type}">${customer.name} ${customer.phone ? '(' + customer.phone + ')' : ''}</option>`;
            });

            document.getElementById('customer_select').innerHTML = options;

        } catch (error) {
            console.error('Error loading customers:', error);
        }
    }


    function onCustomerSelect(customerId) {
        if (!customerId) {

            document.getElementById('customer_name').value = '';
            document.getElementById('customer_phone').value = '';
            document.getElementById('customer_name').placeholder = 'Walk-in customer';
            document.getElementById('customer_name').readOnly = false;
            document.getElementById('customer_name').style.backgroundColor = '';
            document.getElementById('customer_phone').readOnly = false;
            document.getElementById('customer_phone').style.backgroundColor = '';
            return;
        }


        const select = document.getElementById('customer_select');
        const option = select.options[select.selectedIndex];

        const name = option.dataset.name;
        const phone = option.dataset.phone;
        const type = option.dataset.type;


        document.getElementById('customer_name').value = name;
        document.getElementById('customer_phone').value = phone;
        document.getElementById('customer_type').value = type;


        document.getElementById('customer_name').readOnly = true;
        document.getElementById('customer_name').style.backgroundColor = '#f8f9fa';
        document.getElementById('customer_phone').readOnly = true;
        document.getElementById('customer_phone').style.backgroundColor = '#f8f9fa';


        customerType = type;
        updatePricingNote();


        if (cart.length > 0) {
            recalculateCartPrices();
        }
    }


    function setCustomerInDropdown(customerName, customerPhone) {
        const select = document.getElementById('customer_select');
        for (let i = 0; i < select.options.length; i++) {
            if (select.options[i].dataset.name === customerName) {
                select.selectedIndex = i;
                onCustomerSelect(select.value);
                return;
            }
        }

        document.getElementById('customer_name').value = customerName || '';
        document.getElementById('customer_phone').value = customerPhone || '';
    }


    document.addEventListener('DOMContentLoaded', function () {
        loadCustomers();
        document.getElementById('customer_type').addEventListener('change', function () {
            this.style.border = '';
        });
    });


    <?php if ($editing_invoice): ?>

    setTimeout(() => {
        setCustomerInDropdown('<?php echo addslashes($editing_invoice['customer_name'] ?? ''); ?>', '<?php echo addslashes($editing_invoice['customer_phone'] ?? ''); ?>');
    }, 500);
    <?php endif; ?>

</script>

<?php require_once 'includes/footer.php'; ?>