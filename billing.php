<style>
    .search-result-item.selected {
        background-color: #0d6efd !important;
        color: white !important;
    }

    .search-result-item.selected small {
        color: rgba(255, 255, 255, 0.8) !important;
    }
</style>
<?php
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
checkAuth();
$edit_mode = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$editing_invoice = null;

if ($edit_mode > 0) {
    // Get invoice header
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
    $stmt->execute([$edit_mode]);
    $invoice_header = $stmt->fetch();

    if ($invoice_header) {
        // Get invoice items
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
                    <div class="col-md-4">
                        <label>Customer Type</label>
                        <select id="customer_type" class="form-select" onchange="updateCustomerType()">
                            <option value="retail">🛒 Retail</option>
                            <option value="wholesale">📦 Wholesale</option>
                        </select>
                        <small id="pricing_note" class="text-success">Retail pricing applied</small>
                    </div>
                    <div class="col-md-4">
                        <label>Customer Name (Optional)</label>
                        <input type="text" id="customer_name" class="form-control" placeholder="Walk-in customer">
                    </div>
                    <div class="col-md-4">
                        <label>Phone (Optional)</label>
                        <input type="text" id="customer_phone" class="form-control" placeholder="Phone number">
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
                            placeholder="Enter amount">
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
    let customerType = 'retail';
    let searchResults = [];
    let selectedResultIndex = -1;
    // Check if in edit mode

    // Check if in edit mode
    <?php if ($editing_invoice): ?>
        console.log('Edit mode activated for invoice: <?php echo $editing_invoice['invoice_no']; ?>');

        // Set customer type
        customerType = '<?php echo $editing_invoice['customer_type']; ?>';
        document.getElementById('customer_type').value = customerType;

        // Set customer details
        document.getElementById('customer_name').value = '<?php echo addslashes($editing_invoice['customer_name'] ?? ''); ?>';
        document.getElementById('customer_phone').value = '<?php echo addslashes($editing_invoice['customer_phone'] ?? ''); ?>';


        // Load and clean cart items
        const rawCart = <?php echo json_encode($editing_invoice['items']); ?>;
        cart = rawCart.map(item => ({
            product_id: parseInt(item.product_id) || 0,
            product_name: item.product_name,
            quantity: parseFloat(item.quantity) || 0,
            unit_price: parseFloat(item.unit_price) || 0,
            total_price: parseFloat(item.total_price) || 0,
            tier_info: item.tier_info || '',
            unit: 'piece'
        }));
        // cart.reverse();
        console.log('Cleaned cart items:', cart);
        console.log('Loaded cart items:', cart.length);

        // Render cart and update totals
        renderCart();
        updateTotal();

        // Change complete button to update button
        const completeBtn = document.querySelector('button[onclick="completeSale()"]');
        if (completeBtn) {
            completeBtn.innerHTML = '<i class="bi bi-check-circle"></i> Update Invoice (F12)';
            completeBtn.className = 'btn btn-warning btn-lg w-100 mb-2';
        }

        // Show edit banner
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

        // Store original invoice ID
        const EDIT_MODE = true;
        const OLD_INVOICE_ID = <?php echo $editing_invoice['id']; ?>;

        // Override complete function for edit mode
        const originalComplete = completeSale;
        completeSale = async function () {
            await updateExistingInvoice(OLD_INVOICE_ID);
        };
        // Update existing invoice
        async function updateExistingInvoice(oldInvoiceId) {
            if (cart.length === 0) {
                showNotification('error', 'Cart is empty!');
                return;
            }

            const subtotal = cart.reduce((sum, item) => sum + parseFloat(item.total_price || 0), 0);
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
                payment_method: 'cash', // Default for edits
                items: cart.map(item => ({
                    product_id: item.product_id,
                    product_name: item.product_name,
                    quantity: item.quantity,
                    unit_price: item.unit_price,
                    total_price: item.total_price,
                    tier_info: item.tier_info || null
                }))
            };

            console.log('Updating invoice:', invoiceData);

            try {
                const response = await fetch('api/edit_invoice.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(invoiceData)
                });

                const result = await response.json();
                console.log('Update result:', result);

                if (result.success) {
                    window.open(`api/print_receipt.php?id=${result.invoice_id}`, '_blank');
                    showNotification('success', result.message || 'Invoice updated!');

                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 2000);
                } else {
                    showNotification('error', result.error || 'Failed to update invoice');
                }
            } catch (error) {
                console.error('Update error:', error);
                showNotification('error', 'Error updating invoice');
            }
        }

        // Hide payment method for edit mode (keep original payment method)
        document.getElementById('payment_method').closest('.mb-3').style.display = 'none';
    <?php endif; ?>



    // Calculate change/balance based on amount received
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
            // Enough money - show change to return
            const change = received - total;
            statusDiv.className = 'alert alert-success mb-3';
            statusLabel.textContent = '💵 Change to Return:';
            statusLabel.className = 'text-success';
            balanceAmount.textContent = `Rs. ${change.toFixed(0)}`;
            balanceAmount.className = 'fw-bold fs-5 text-success';
            completeBtn.disabled = false;
            completeBtn.innerHTML = '<i class="bi bi-check-circle"></i> Complete Sale (F12)';
        } else {
            // Not enough money - show balance due
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

    // Get current grand total
    function getGrandTotal() {
        const totalText = document.getElementById('grand_total').textContent;
        return parseFloat(totalText.replace('Rs.', '').replace(',', '').trim()) || 0;
    }

    // Set received amount quickly
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

    // Update total (modified to also recalculate change)
    const originalUpdateTotal = updateTotal;
    updateTotal = function () {
        originalUpdateTotal();
        calculateChange();

        // Also update exact button text
        const total = getGrandTotal();
        const exactBtn = document.querySelector('button[onclick="setReceivedAmount(\'exact\')"]');
        if (exactBtn) {
            exactBtn.textContent = `Exact (Rs. ${total.toFixed(0)})`;
        }
    };

    // Override completeSale to validate payment
    const originalCompleteSale = completeSale;
    completeSale = async function () {
        const total = getGrandTotal();
        const received = parseFloat(document.getElementById('amount_received').value) || 0;
        const paymentMethod = document.getElementById('payment_method').value;

        // For non-cash payments, auto-set received = total
        if (paymentMethod !== 'cash') {
            document.getElementById('amount_received').value = total;
        } else if (received < total) {
            showNotification('error', `Insufficient payment! Balance due: Rs. ${(total - received).toFixed(0)}`);
            document.getElementById('amount_received').focus();
            return;
        }

        await originalCompleteSale();
    };

    // Reset received amount when cart cleared
    const originalClearCart = clearCart;
    clearCart = function () {
        if (confirm('Clear all items from cart?')) {
            originalClearCart();
            document.getElementById('amount_received').value = '0';
            document.getElementById('payment_status').style.display = 'none';
        }
    };
    async function updateCustomerType() {
        customerType = document.getElementById('customer_type').value;

        // Show loading indicator
        const oldType = customerType;

        // Recalculate all items in cart with new customer type
        if (cart.length > 0) {
            // Clear and rebuild cart with new pricing
            const currentItems = [...cart];
            cart = [];

            for (const item of currentItems) {
                await addToCartWithQuantity(
                    item.product_id,
                    item.product_name,
                    item.unit,
                    item.max_stock || 999999,
                    item.quantity
                );
            }
        }

        // Update the display
        renderCart();
        updateTotal();

        // Show notification
        const typeText = customerType === 'wholesale' ? 'Wholesale' : 'Retail';
        console.log(`Switched to ${typeText} pricing`);
    }

    async function addToCartWithQuantity(productId, productName, unit, maxStock, quantity) {
    // Get price from server with current customer type
    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('quantity', quantity);
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

    // Check if product already exists in cart
    const existingIndex = cart.findIndex(item => item.product_id === productId);

    if (existingIndex >= 0) {
        // Product exists - add to existing quantity
        const existingItem = cart[existingIndex];
        const newQuantity = existingItem.quantity + parseFloat(quantity);
        
        // Check if new total exceeds stock
        if (newQuantity > maxStock) {
            alert(`Cannot add more! Total would exceed available stock (${maxStock} ${unit} available, already have ${existingItem.quantity} in cart)`);
            return;
        }
        
        // Update quantity and recalculate price
        cart[existingIndex].quantity = newQuantity;
        
        // Recalculate price with new quantity
        const updateFormData = new FormData();
        updateFormData.append('product_id', productId);
        updateFormData.append('quantity', newQuantity);
        updateFormData.append('customer_type', customerType);
        
        const updateResponse = await fetch('api/get_price.php', {
            method: 'POST',
            body: updateFormData
        });
        const updatePriceData = await updateResponse.json();
        
        cart[existingIndex].unit_price = updatePriceData.unit_price;
        cart[existingIndex].total_price = updatePriceData.total_price;
        cart[existingIndex].tier_info = updatePriceData.tier_info || `${updatePriceData.tier_min} - ${updatePriceData.tier_max || '∞'} ${unit}`;
        
        showNotification('success', `Updated ${productName} quantity to ${newQuantity} ${unit}`);
    } else {
        // New product - add to cart
        cart.unshift({
            product_id: productId,
            product_name: productName,
            quantity: parseFloat(quantity),
            unit: unit,
            unit_price: priceData.unit_price,
            total_price: priceData.total_price,
            tier_info: priceData.tier_info || `${priceData.tier_min} - ${priceData.tier_max || '∞'} ${unit}`,
            max_stock: maxStock
        });
        
        showNotification('success', `Added ${quantity} ${unit} ${productName}`);
    }
}

    // const originalSearchProduct = searchProduct;
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

            // ONLY reset to 0 if this is a brand new search (not triggered by navigation)
            // We'll use a flag to know if this is navigation vs typing
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
                <small>Stock: ${product.current_stock} ${product.unit}</small>
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

    function renderCart() {
        const tbody = document.getElementById('cart_items');

        const countEl = document.getElementById('cart_item_count');
        if (countEl) {
            const itemCount = cart.length;
            const totalQty = cart.reduce((sum, item) => sum + parseFloat(item.quantity || 0), 0);
            countEl.textContent = `${itemCount}`;
        }
        if (cart.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No items in cart. Search and add products above.</td></tr>';
            return;
        }


        let html = '';
        cart.forEach((item, index) => {
            // Convert to numbers if they're strings
            const quantity = parseFloat(item.quantity) || 0;
            const unitPrice = parseFloat(item.unit_price) || 0;
            const totalPrice = parseFloat(item.total_price) || (quantity * unitPrice);

            html += `
            <tr>
                <td>
                    <strong>${item.product_name}</strong><br>
                    <small class="text-muted">${item.tier_info || ''}</small>
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm" 
                           value="${quantity}" step="1" min="0"
                           onchange="updateCartItemQuantity(${index}, this.value)">
                </td>
                <td class="text-end">Rs. ${unitPrice.toFixed(2)}</td>
                <td class="text-end">Rs. ${totalPrice.toFixed(2)}</td>
                <td>
                    <button class="btn btn-sm btn-danger" onclick="removeFromCart(${index})">
                        <i class="bi bi-x"></i>
                    </button>
                </td>
            </tr>
        `;
        });

        tbody.innerHTML = html;
        document.getElementById('amount_received').value = '0';
        document.getElementById('payment_status').style.display = 'none';
    }
    // Clean cart data - convert string prices to numbers
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
    async function updateCartItemQuantity(index, newQty) {
        if (newQty <= 0) {
            removeFromCart(index);
            return;
        }

        await updateItemPrice(index, parseFloat(newQty));
        renderCart();
        updateTotal();
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
        if (cart.length === 0) {
            alert('Cart is empty!');
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

        const invoiceData = {
            customer_name: document.getElementById('customer_name').value,
            customer_phone: document.getElementById('customer_phone').value,
            customer_type: customerType,
            subtotal: subtotal,
            discount: discount,
            total: total,
            payment_method: document.getElementById('payment_method').value,
            items: cart
        };

        const response = await fetch('api/save_invoice.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(invoiceData)
        });

        const result = await response.json();

        if (result.success) {
            // Open print window
            window.open(`api/print_receipt.php?id=${result.invoice_id}`, '_blank');

            // Clear cart for new sale
            cart = [];
            renderCart();
            updateTotal();
            document.getElementById('customer_name').value = '';
            document.getElementById('customer_phone').value = '';
            document.getElementById('discount_input').value = '0';

            alert(`Invoice ${result.invoice_no} completed successfully!`);
        } else {
            alert('Error: ' + result.error);
        }
    }
    // Edit invoice - redirect to billing page with invoice data
    function editInvoice(invoiceId) {
        if (confirm('Edit this invoice? A new version will be created.')) {
            // Redirect directly with invoice ID
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

    // function clearSearch() {
    //     document.getElementById('search_product').value = '';
    //     document.getElementById('search_results').innerHTML = '';
    // }

    // Update pricing note when type changes
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

    // Call this in updateCustomerType()
    async function updateCustomerType() {
        customerType = document.getElementById('customer_type').value;

        // Show notification
        updatePricingNote();

        // Recalculate all items in cart with new customer type
        if (cart.length > 0) {
            const currentItems = [...cart];
            cart = [];

            for (const item of currentItems) {
                await addToCartWithQuantity(
                    item.product_id,
                    item.product_name,
                    item.unit,
                    item.max_stock || 999999,
                    item.quantity
                );
            }
        }

        renderCart();
        updateTotal();
    }
    // // Keyboard shortcuts
    // document.addEventListener('keydown', function (e) {
    //     if (e.key === 'F12') {
    //         e.preventDefault();
    //         completeSale();
    //     } else if (e.key === 'Escape') {
    //         clearSearch();
    //     } else if (e.key === '/' && !e.target.matches('input')) {
    //         e.preventDefault();
    //         document.getElementById('search_product').focus();
    //     }
    // });

    // Load quick products - Popular items first, fallback to first 10
    async function loadQuickProducts() {
        try {
            const response = await fetch('api/search_product.php?mode=popular');
            const products = await response.json();

            let html = '';

            if (products.length === 0) {
                html = '<div class="col-12 text-muted text-center py-3">No products available</div>';
            } else {
                products.forEach(product => {
                    // Show sales badge if product has sales
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
    // Show notification toast
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
    // Hold current invoice
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

                // Clear cart
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

    // Show held invoices modal
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

            // Remove existing modal if any
            const existingModal = document.getElementById('heldInvoicesModal');
            if (existingModal) existingModal.remove();

            document.body.insertAdjacentHTML('beforeend', modalHtml);
            new bootstrap.Modal(document.getElementById('heldInvoicesModal')).show();

        } catch (error) {
            showNotification('error', 'Error loading held invoices');
        }
    }

    // Resume held invoice
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

                // Restore customer details
                document.getElementById('customer_name').value = inv.customer_name || '';
                document.getElementById('customer_phone').value = inv.customer_phone || '';
                document.getElementById('customer_type').value = inv.customer_type;
                customerType = inv.customer_type;

                // Restore cart
                cart = inv.cart_data;
                renderCart();
                updateTotal();

                // Close modal
                bootstrap.Modal.getInstance(document.getElementById('heldInvoicesModal')).hide();

                showNotification('success', 'Invoice resumed!');
            }
        } catch (error) {
            showNotification('error', 'Error resuming invoice');
        }
    }// Handle search input - prevents arrow keys from triggering search
    function handleSearchInput(query) {
        // Don't trigger search if we're navigating with arrow keys
        if (window.isNavigating) {
            return;
        }
        searchProduct(query);
    }
    // Prevent arrow keys from changing input value during navigation
    document.getElementById('search_product').addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            // Don't prevent default completely, just let our handler work
            // But prevent the input cursor from moving
            e.preventDefault();
        }
    });

    // Delete held invoice
    async function deleteHeldInvoice(holdId) {
        if (!confirm('Delete this held invoice permanently?')) return;

        try {
            await fetch('api/hold_invoice.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', hold_id: holdId })
            });

            // Close and refresh modal
            bootstrap.Modal.getInstance(document.getElementById('heldInvoicesModal')).hide();
            showHeldInvoices();

            showNotification('success', 'Held invoice deleted!');
        } catch (error) {
            showNotification('error', 'Error deleting invoice');
        }
    }
    loadQuickProducts();
    updateTotal();
    // Warn before leaving page with items in cart
    let hasUnsavedChanges = false;

    // Update unsaved changes flag when cart changes
    const originalRenderCart2 = renderCart;
    renderCart = function () {
        originalRenderCart2();
        hasUnsavedChanges = cart.length > 0;
    };

    // Also track when items are added
    const originalAddToCart = addToCart;
    addToCart = async function (productId, productName, unit, maxStock) {
        await originalAddToCart(productId, productName, unit, maxStock);
        hasUnsavedChanges = true;
    };

    // Track when items are removed
    const originalRemoveFromCart = removeFromCart;
    removeFromCart = function (index) {
        originalRemoveFromCart(index);
        hasUnsavedChanges = cart.length > 0;
    };

    // Track when cart is cleared
    const originalClearCart2 = clearCart;
    clearCart = function () {
        if (confirm('Clear all items from cart?')) {
            originalClearCart2();
            hasUnsavedChanges = false;
        }
    };

    // Warn before leaving page
    window.addEventListener('beforeunload', function (e) {
        if (hasUnsavedChanges && cart.length > 0) {
            // Standard message (browser may show its own)
            const message = 'You have items in your cart. Are you sure you want to leave?';
            e.preventDefault();
            e.returnValue = message;
            return message;
        }
    });

    // Reset flag after successful sale
    const originalCompleteSale3 = completeSale;
    completeSale = async function () {
        await originalCompleteSale3();
        hasUnsavedChanges = false;
    };

    // Reset flag after holding invoice
    const originalHoldInvoice = holdInvoice;
    holdInvoice = async function () {
        await originalHoldInvoice();
        hasUnsavedChanges = false;
    };

    // Handle sidebar link clicks
    document.addEventListener('click', function (e) {
        const link = e.target.closest('a');
        if (link && hasUnsavedChanges && cart.length > 0) {
            const href = link.getAttribute('href');
            // Skip logout and javascript links
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
    // Highlight the selected result
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


   function selectProductFromSearch(product) {
    if (!product) return;
    
    const quantity = prompt(`Enter quantity for ${product.name} (${product.unit}):`, '1');
    if (!quantity || quantity <= 0) return;
    if (parseFloat(quantity) > product.current_stock) {
        alert(`Only ${product.current_stock} ${product.unit} available in stock!`);
        return;
    }

    addToCartWithQuantity(
        product.id, 
        product.name, 
        product.unit, 
        product.current_stock, 
        parseFloat(quantity)
    ).then(() => {
        renderCart();
        updateTotal();
        
        // Clear search
        document.getElementById('search_product').value = '';
        document.getElementById('search_results').innerHTML = '';
        searchResults = [];
        selectedResultIndex = -1;
        
        // Focus back on search
        document.getElementById('search_product').focus();
    });
}

    function clearSearch() {
        document.getElementById('search_product').value = '';
        document.getElementById('search_results').innerHTML = '';
        searchResults = [];
        selectedResultIndex = -1;
        document.getElementById('search_product').focus();
    }




    // document.addEventListener('keydown', function (e) {
    //     const searchInput = document.getElementById('search_product');
    //     const searchResultsDiv = document.getElementById('search_results');

    //     // Only handle if search input is focused
    //     if (document.activeElement !== searchInput) return;

    //     // Check if we have search results
    //     const resultItems = document.querySelectorAll('.search-result-item');
    //     if (resultItems.length === 0) return;

    //     const key = e.key;

    //     if (key === 'ArrowDown') {
    //         e.preventDefault();
    //         e.stopPropagation();

    //         // Remove selected class from current
    //         if (selectedResultIndex >= 0 && resultItems[selectedResultIndex]) {
    //             resultItems[selectedResultIndex].classList.remove('selected');
    //         }

    //         // Move to next
    //         if (selectedResultIndex < resultItems.length - 1) {
    //             selectedResultIndex++;
    //         } else {
    //             selectedResultIndex = 0; // Wrap around to first
    //         }

    //         // Add selected class to new item
    //         if (resultItems[selectedResultIndex]) {
    //             resultItems[selectedResultIndex].classList.add('selected');
    //             resultItems[selectedResultIndex].scrollIntoView({ block: 'nearest' });
    //         }

    //     } else if (key === 'ArrowUp') {
    //         e.preventDefault();
    //         e.stopPropagation();

    //         // Remove selected class from current
    //         if (selectedResultIndex >= 0 && resultItems[selectedResultIndex]) {
    //             resultItems[selectedResultIndex].classList.remove('selected');
    //         }

    //         // Move to previous
    //         if (selectedResultIndex > 0) {
    //             selectedResultIndex--;
    //         } else {
    //             selectedResultIndex = resultItems.length - 1; // Wrap around to last
    //         }

    //         // Add selected class to new item
    //         if (resultItems[selectedResultIndex]) {
    //             resultItems[selectedResultIndex].classList.add('selected');
    //             resultItems[selectedResultIndex].scrollIntoView({ block: 'nearest' });
    //         }

    //     } else if (key === 'Enter') {
    //         e.preventDefault();
    //         e.stopPropagation();

    //         if (selectedResultIndex >= 0 && selectedResultIndex < searchResults.length) {
    //             const product = searchResults[selectedResultIndex];
    //             if (product) {
    //                 selectProductFromSearch(product);
    //             }
    //         }
    //     } else if (key === 'Escape') {
    //         e.preventDefault();
    //         clearSearch();
    //     }
    // });


    // document.addEventListener('keydown', function (e) {
    //     const tagName = e.target.tagName;
    //     const isInput = tagName === 'INPUT' || tagName === 'SELECT' || tagName === 'TEXTAREA';
    //     const modalOpen = document.querySelector('.modal.show') !== null;
    //     const overlayOpen = document.getElementById('quantityInputOverlay') !== null;

    //     // Skip if already in an input, modal open, or overlay open
    //     if (isInput || modalOpen || overlayOpen) return;

    //     // Skip modifier keys and special keys
    //     if (e.altKey || e.ctrlKey || e.metaKey) return;
    //     if (e.key.length > 1) return; // Skip function keys, arrows, etc.

    //     // Focus search and let the character type
    //     const searchInput = document.getElementById('search_product');
    //     if (searchInput) {
    //         searchInput.focus();
    //     }
    // });


    // ========== SINGLE KEYBOARD EVENT LISTENER ==========


    // ========== SINGLE KEYBOARD EVENT LISTENER ==========




    // ========== SINGLE KEYBOARD EVENT LISTENER ==========
    document.addEventListener('keydown', function (e) {
        const searchInput = document.getElementById('search_product');
        const isSearchFocused = document.activeElement === searchInput;
        const resultItems = document.querySelectorAll('.search-result-item');
        const tagName = e.target.tagName;
        const isInput = tagName === 'INPUT' || tagName === 'SELECT' || tagName === 'TEXTAREA';
        const modalOpen = document.querySelector('.modal.show') !== null;

        // F12 - Complete Sale
        if (e.key === 'F12') {
            e.preventDefault();
            completeSale();
            return;
        }

        // ESCAPE - Clear search (only if search is focused)
        if (e.key === 'Escape') {
            if (isSearchFocused) {
                e.preventDefault();
                clearSearch();
            }
            return;
        }

        // ===== ARROW KEYS & ENTER - Navigate search results =====
        // Only handle if search input is focused AND we have results
        if (isSearchFocused && resultItems.length > 0) {

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                e.stopPropagation();

                // Set flag to prevent search reset
                window.isNavigating = true;

                // Remove selected class from current
                if (selectedResultIndex >= 0 && resultItems[selectedResultIndex]) {
                    resultItems[selectedResultIndex].classList.remove('selected');
                }

                // Move to next - don't wrap around
                if (selectedResultIndex < resultItems.length - 1) {
                    selectedResultIndex++;
                }

                // Add selected class to new item
                if (selectedResultIndex >= 0 && resultItems[selectedResultIndex]) {
                    resultItems[selectedResultIndex].classList.add('selected');
                    resultItems[selectedResultIndex].scrollIntoView({ block: 'nearest' });
                }

                // Reset flag after a short delay
                setTimeout(() => { window.isNavigating = false; }, 100);
                return;
            }

            if (e.key === 'ArrowUp') {
                e.preventDefault();
                e.stopPropagation();

                // Set flag to prevent search reset
                window.isNavigating = true;

                // Remove selected class from current
                if (selectedResultIndex >= 0 && resultItems[selectedResultIndex]) {
                    resultItems[selectedResultIndex].classList.remove('selected');
                }

                // Move to previous - don't wrap around
                if (selectedResultIndex > 0) {
                    selectedResultIndex--;
                }

                // Add selected class to new item
                if (selectedResultIndex >= 0 && resultItems[selectedResultIndex]) {
                    resultItems[selectedResultIndex].classList.add('selected');
                    resultItems[selectedResultIndex].scrollIntoView({ block: 'nearest' });
                }

                // Reset flag after a short delay
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

        // ===== AUTO-FOCUS SEARCH WHEN TYPING =====
        // If no input is focused, no modal open, and user presses a regular key (letter/number)
        // Then focus the search input
        if (!isInput && !modalOpen) {
            // Check if it's a regular character key (not modifier, not function key)
            const isRegularKey = e.key.length === 1 && !e.altKey && !e.ctrlKey && !e.metaKey;

            // Also skip arrow keys, escape, etc.
            const isNavigationKey = ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Enter', 'Escape', 'Tab'].includes(e.key);

            if (isRegularKey && !isNavigationKey) {
                // Don't capture if we're already in search navigation mode
                if (!isSearchFocused) {
                    e.preventDefault();
                    searchInput.focus();
                    // Let the character be typed after focus
                    setTimeout(() => {
                        searchInput.value = e.key;
                        // Trigger search
                        searchProduct(e.key);
                    }, 10);
                }
            }
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>