// assets/js/cart.js
let userRole = document.getElementById('user_role')?.value || 'cashier';
const isAdmin = userRole === 'admin';
let productPackagesCache = {};

// Fetch packages for a product
async function getProductPackages(productId) {
    if (productPackagesCache[productId]) {
        return productPackagesCache[productId];
    }

    try {
        const response = await fetch(`api/get_product_packages.php?product_id=${productId}`);
        const packages = await response.json();
        productPackagesCache[productId] = packages;
        return packages;
    } catch (error) {
        console.error('Error fetching packages:', error);
        return [];
    }
}

async function renderCart() {
    const tbody = document.getElementById('cart_items');
    const cartCount = document.getElementById('cart_item_count');

    if (!tbody) return;

    if (cart.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No items in cart. Search and add products above.</td></tr>';
        if (cartCount) cartCount.textContent = '(0)';
        return;
    }

    if (cartCount) cartCount.textContent = `(${cart.length})`;

    let html = '';

    for (let index = 0; index < cart.length; index++) {
        const item = cart[index];

        // Fetch packages for this product if not already fetched
        if (!item.packages) {
            item.packages = await getProductPackages(item.product_id);
        }

        let displayQty = item.display_quantity || item.actual_quantity || 0;
        displayQty = parseFloat(displayQty) || 0;

        const displayUnit = item.display_unit || item.base_unit || 'Piece';
        const unitPrice = (item.unit_price || 0).toFixed(2);
        const totalPrice = (item.total_price || 0).toFixed(2);

        let qtyValue = displayQty;
        if (Math.floor(displayQty) === displayQty) {
            qtyValue = displayQty;
        } else {
            qtyValue = displayQty.toFixed(3);
        }

        let packageOptions = `<option value="base" data-multiplier="1" data-is-package="false">${item.base_unit}</option>`;
        if (item.packages && item.packages.length > 0) {
            item.packages.forEach(pkg => {
                const selected = (item.selected_package_id == pkg.id) ? 'selected' : '';
                packageOptions += `<option value="${pkg.id}" data-multiplier="${pkg.multiplier}" data-name="${pkg.package_name}" data-is-package="true" ${selected}>${pkg.package_name}</option>`;
            });
        }

        html += `
            <tr data-cart-index="${index}">
                <td style="min-width: 180px;">
                    <strong>${escapeHtml(item.product_name)}</strong>
                    ${item.tier_info ? `<br><small class="text-muted">${item.tier_info}</small>` : ''}
                </td>
                <td width="120">
                    <input type="number" class="form-control form-control-sm cart-qty" 
                           data-index="${index}"
                           value="${qtyValue}" step="any" 
                           onchange="updateCartItemQuantity(${index}, this.value)"
                           style="width: 100px;">
                </td>
                <td width="120">
                    <select class="form-select form-select-sm cart-unit-select" 
                            data-index="${index}"
                            onchange="changePackage(${index}, this.value, this.options[this.selectedIndex])"
                            style="font-size: 13px;">
                        ${packageOptions}
                    </select>
                </td>
                <td width="120">
                    ${isAdmin ?
                `<input type="number" class="form-control form-control-sm cart-unit-price" 
                                data-index="${index}"
                                value="${unitPrice}" step="0.01" 
                                onchange="updateCartItemUnitPrice(${index}, this.value)"
                                style="width: 100px; background-color: #fff3cd;">` :
                `<span class="cart-unit-price-display">Rs. ${unitPrice}</span>`
            }
                </td>
                <td width="120">
                    ${isAdmin ?
                `<input type="number" class="form-control form-control-sm cart-total" 
                                data-index="${index}"
                                value="${totalPrice}" step="0.01" 
                                onchange="updateCartItemTotal(${index}, this.value)"
                                style="width: 110px; background-color: #d1ecf1;">` :
                `<span class="cart-total-display">Rs. ${totalPrice}</span>`
            }
                </td>
                <td width="50">
                    <button class="btn btn-sm btn-danger" onclick="removeFromCart(${index})">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    }

    tbody.innerHTML = html;
}

// Change package - Quantity number stays the same, only price changes
async function changePackage(index, selectedValue, selectedOption) {
    const item = cart[index];
    if (!item) return;

    const isPackage = selectedOption.getAttribute('data-is-package') === 'true';
    const multiplier = parseFloat(selectedOption.getAttribute('data-multiplier') || 1);

    let currentDisplayQty = item.display_quantity;
    currentDisplayQty = parseFloat(currentDisplayQty) || 1;

    if (!isPackage) {
        item.display_unit = item.base_unit;
        item.package_multiplier = 1;
        item.selected_package_id = null;
        item.actual_quantity = currentDisplayQty;
        item.display_quantity = currentDisplayQty;
    } else {
        const packageId = selectedValue;
        const packageName = selectedOption.getAttribute('data-name');

        item.selected_package_id = parseInt(packageId);
        item.display_unit = packageName;
        item.package_multiplier = multiplier;
        item.actual_quantity = currentDisplayQty * multiplier;
        item.display_quantity = currentDisplayQty;
    }

    const formData = new FormData();
    formData.append('product_id', item.product_id);
    formData.append('quantity', item.actual_quantity);
    formData.append('customer_type', customerType);

    try {
        const response = await fetch('api/get_price.php', { method: 'POST', body: formData });
        const text = await response.text();

        if (!text.startsWith('<')) {
            const priceData = JSON.parse(text);
            item.unit_price = priceData.unit_price || 0;
            item.total_price = priceData.total_price || 0;
            item.tier_info = priceData.tier_info || '';
        }
    } catch (error) {
        console.error('Error updating price:', error);
        item.total_price = (item.unit_price || 0) * item.actual_quantity;
    }

    renderCart();
    updateTotal();

    const unitName = isPackage ? selectedOption.getAttribute('data-name') : item.base_unit;
    if (typeof showNotification === 'function') {
        showNotification('success', `Changed to ${unitName} - Price updated`);
    }
}

// Update quantity - User changes the quantity number
async function updateCartItemQuantity(index, newDisplayQty) {
    const item = cart[index];
    if (!item) return;

    newDisplayQty = parseFloat(newDisplayQty);
    if (isNaN(newDisplayQty) || newDisplayQty <= 0) {
        removeFromCart(index);
        return;
    }

    let actualNewQty = newDisplayQty * (item.package_multiplier || 1);

    if (actualNewQty > item.max_stock) {
        alert(`Only ${item.max_stock} ${item.base_unit} available in stock!`);
        renderCart();
        return;
    }

    item.display_quantity = newDisplayQty;
    item.actual_quantity = actualNewQty;

    const formData = new FormData();
    formData.append('product_id', item.product_id);
    formData.append('quantity', actualNewQty);
    formData.append('customer_type', customerType);

    try {
        const response = await fetch('api/get_price.php', { method: 'POST', body: formData });
        const text = await response.text();

        if (!text.startsWith('<')) {
            const priceData = JSON.parse(text);
            item.unit_price = priceData.unit_price || 0;
            item.total_price = priceData.total_price || 0;
            item.tier_info = priceData.tier_info || '';
        } else {
            item.total_price = (item.unit_price || 0) * actualNewQty;
        }
    } catch (error) {
        console.error('Error updating price:', error);
        item.total_price = (item.unit_price || 0) * actualNewQty;
    }

    renderCart();
    updateTotal();
}

// ADMIN ONLY: Update unit price - recalculates total
async function updateCartItemUnitPrice(index, newUnitPrice) {
    if (!isAdmin) return;

    const item = cart[index];
    if (!item) return;

    newUnitPrice = parseFloat(newUnitPrice);
    if (isNaN(newUnitPrice) || newUnitPrice < 0) {
        renderCart();
        return;
    }

    const actualQuantity = item.actual_quantity || 1;
    item.unit_price = newUnitPrice;
    item.total_price = newUnitPrice * actualQuantity;

    renderCart();
    updateTotal();

    if (typeof showNotification === 'function') {
        showNotification('success', `Unit price changed to Rs. ${newUnitPrice.toFixed(2)}`);
    }
}

// ADMIN ONLY: Update total - auto-calculates quantity
async function updateCartItemTotal(index, newTotal) {
    if (!isAdmin) return;

    const item = cart[index];
    if (!item) return;

    newTotal = parseFloat(newTotal);
    if (isNaN(newTotal) || newTotal < 0) {
        renderCart();
        return;
    }

    const unitPrice = item.unit_price || 1;

    if (unitPrice <= 0) {
        alert('Unit price must be greater than 0 to calculate quantity');
        renderCart();
        return;
    }

    let actualNewQty = newTotal / unitPrice;

    const baseUnit = item.base_unit || 'Piece';
    const discreteUnits = ['piece', 'Piece', 'packet', 'dozen', 'box', 'bottle', 'can'];

    if (discreteUnits.includes(baseUnit)) {
        actualNewQty = Math.round(actualNewQty);
    } else {
        actualNewQty = Math.round(actualNewQty * 1000) / 1000;
    }

    if (actualNewQty <= 0) {
        alert('Total amount must be greater than unit price to get at least 1 unit');
        renderCart();
        return;
    }

    if (actualNewQty > item.max_stock) {
        alert(`This would require ${actualNewQty} ${baseUnit} but only ${item.max_stock} available!`);
        renderCart();
        return;
    }

    item.actual_quantity = actualNewQty;
    item.total_price = newTotal;
    item.display_quantity = actualNewQty / (item.package_multiplier || 1);

    renderCart();
    updateTotal();

    let qtyDisplay = item.display_quantity;
    if (Math.floor(qtyDisplay) === qtyDisplay) {
        qtyDisplay = qtyDisplay;
    } else {
        qtyDisplay = qtyDisplay.toFixed(3);
    }
    if (typeof showNotification === 'function') {
        showNotification('success', `Total set to Rs. ${newTotal.toFixed(2)} → Quantity: ${qtyDisplay} ${item.display_unit}`);
    }
}

function removeFromCart(index) {
    cart.splice(index, 1);
    renderCart();
    updateTotal();
}

// Helper function for escaping HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Show notification function
if (typeof showNotification !== 'function') {
    window.showNotification = function (type, message) {
        console.log(`[${type}] ${message}`);
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type === 'success' ? 'success' : 'info'} alert-dismissible fade show position-fixed`;
        alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 250px;';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(alertDiv);
        setTimeout(() => alertDiv.remove(), 3000);
    };
}