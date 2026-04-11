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
                        <td>
                            Discount:
                            <input type="number" id="discount_input" class="form-control form-control-sm d-inline-block"
                                style="width: 80px;" value="0" min="0" onchange="updateTotal()">
                        </td>
                        <td class="text-end">
                            <select id="discount_type" class="form-select form-select-sm d-inline-block"
                                style="width: 70px;" onchange="updateTotal()">
                                <option value="fixed">₹</option>
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

                <div class="mb-3">
                    <label>Payment Method</label>
                    <select id="payment_method" class="form-select">
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="upi">UPI</option>
                        <option value="credit">Credit</option>
                    </select>
                </div>

                <button class="btn btn-success btn-lg w-100 mb-2" onclick="completeSale()">
                    <i class="bi bi-check-circle"></i> Complete Sale (F12)
                </button>
                <button class="btn btn-secondary w-100" onclick="clearCart()">
                    <i class="bi bi-trash"></i> Clear Cart
                </button>
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

    loadQuickProducts();
    updateTotal();
</script>

<?php require_once 'includes/footer.php'; ?>