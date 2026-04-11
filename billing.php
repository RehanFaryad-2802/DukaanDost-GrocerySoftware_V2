<?php
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">New Sale Invoice</h1>
</div>

<div class="row">
    <div class="col-md-8">
        <!-- Customer Details -->
        <div class="card mb-3">
            <div class="card-body">
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
            <div class="card-body">
                <div class="input-group mb-3">
                    <input type="text" id="search_product" class="form-control form-control-lg"
                        placeholder="Scan/Search product by name or code..." onkeyup="searchProduct(this.value)"
                        autofocus>
                    <button class="btn btn-outline-secondary" type="button" onclick="clearSearch()">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
                <div id="search_results" class="list-group" style="max-height: 200px; overflow-y: auto;"></div>
            </div>
        </div>

        <!-- Quick Products Grid -->
        <div class="card mb-3">
            <div class="card-header">
                <i class="bi bi-grid-3x3"></i> Quick Products
            </div>
            <div class="card-body">
                <div id="quick_products" class="row">
                    <!-- Loaded via AJAX -->
                </div>
            </div>
        </div>

        <!-- Cart Items -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-cart"></i> Current Bill Items
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
                    <option value="fixed">₹</option>
                    <option value="percent">%</option>
                </select>
            </td>
        </tr>
        <tr class="table-primary">
            <th>GRAND TOTAL:</th>
            <th class="text-end"><h4 id="grand_total">0.00</h4></th>
        </tr>
    </table>
    
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
    <button class="btn btn-success btn-lg w-100 mb-2" onclick="completeSale()">
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

        cart.push({
            product_id: productId,
            product_name: productName,
            quantity: parseFloat(quantity),
            unit: unit,
            unit_price: priceData.unit_price,
            total_price: priceData.total_price,
            tier_info: priceData.tier_info || `${priceData.tier_min} - ${priceData.tier_max || '∞'} ${unit}`,
            max_stock: maxStock
        });
    }

    async function searchProduct(query) {
        if (query.length < 2) {
            document.getElementById('search_results').innerHTML = '';
            return;
        }

        const response = await fetch(`api/search_product.php?q=${query}&customer_type=${customerType}`);
        const products = await response.json();

        let html = '';
        products.forEach(product => {
            html += `
            <a href="#" class="list-group-item list-group-item-action" onclick="addToCart(${product.id}, '${product.name}', '${product.unit}', ${product.current_stock}); return false;">
                <div class="d-flex justify-content-between">
                    <strong>${product.name}</strong>
                    <span class="badge bg-secondary">${product.code}</span>
                </div>
                <small>Stock: ${product.current_stock} ${product.unit}</small>
            </a>
        `;
        });

        document.getElementById('search_results').innerHTML = html;
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

        if (cart.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No items in cart. Search and add products above.</td></tr>';
            return;
        }

        let html = '';
        cart.forEach((item, index) => {
            html += `
            <tr>
                <td>
                    <strong>${item.product_name}</strong><br>
                    <small class="text-muted">${item.tier_info}</small>
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm" 
                           value="${item.quantity}" step="0.001" min="0.001"
                           onchange="updateCartItemQuantity(${index}, this.value)">
                </td>
                <td class="text-end">₹ ${item.unit_price.toFixed(2)}</td>
                <td class="text-end">₹ ${item.total_price.toFixed(2)}</td>
                <td>
                    <button class="btn btn-sm btn-danger" onclick="removeFromCart(${index})">
                        <i class="bi bi-x"></i>
                    </button>
                </td>
            </tr>
        `;
        });

        tbody.innerHTML = html;
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

        document.getElementById('subtotal').textContent = `₹ ${subtotal.toFixed(2)}`;
        document.getElementById('grand_total').textContent = `₹ ${total.toFixed(2)}`;
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

    function clearCart() {
        if (confirm('Clear all items from cart?')) {
            cart = [];
            renderCart();
            updateTotal();
        }
    }

    function clearSearch() {
        document.getElementById('search_product').value = '';
        document.getElementById('search_results').innerHTML = '';
    }

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
    // Keyboard shortcuts
    document.addEventListener('keydown', function (e) {
        if (e.key === 'F12') {
            e.preventDefault();
            completeSale();
        } else if (e.key === 'Escape') {
            clearSearch();
        } else if (e.key === '/' && !e.target.matches('input')) {
            e.preventDefault();
            document.getElementById('search_product').focus();
        }
    });

    // Load quick products
    async function loadQuickProducts() {
        const response = await fetch('api/search_product.php?q=');
        const products = await response.json();

        let html = '';
        products.slice(0, 8).forEach(product => {
            html += `
            <div class="col-3 mb-2">
                <button class="btn btn-outline-primary w-100" 
                        onclick="addToCart(${product.id}, '${product.name}', '${product.unit}', ${product.current_stock})">
                    ${product.name}
                </button>
            </div>
        `;
        });

        document.getElementById('quick_products').innerHTML = html;
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
            headers: {'Content-Type': 'application/json'},
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
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'list'})
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
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get', hold_id: holdId})
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
}

// Delete held invoice
async function deleteHeldInvoice(holdId) {
    if (!confirm('Delete this held invoice permanently?')) return;
    
    try {
        await fetch('api/hold_invoice.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'delete', hold_id: holdId})
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
</script>

<?php require_once 'includes/footer.php'; ?>