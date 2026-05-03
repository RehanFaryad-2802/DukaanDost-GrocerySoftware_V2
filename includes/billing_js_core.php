<script>

    if (typeof cart === 'undefined') {
        var cart = [];
    }
    if (typeof customerType === 'undefined') {
        var customerType = '';
    }
    if (typeof searchResults === 'undefined') {
        var searchResults = [];
    }
    if (typeof selectedResultIndex === 'undefined') {
        var selectedResultIndex = -1;
    }
    if (typeof currentProductId === 'undefined') {
        var currentProductId = 0;
    }
    if (typeof packageCount === 'undefined') {
        var packageCount = 0;
    }
    if (typeof currentUnitProductId === 'undefined') {
        var currentUnitProductId = 0;
    }

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

    function calculateChange() {
        const totalElement = document.getElementById('grand_total');
        if (!totalElement) return;

        const totalText = totalElement.textContent;
        const total = parseFloat(totalText.replace('Rs.', '').replace(',', '').trim()) || 0;
        const received = parseFloat(document.getElementById('amount_received').value) || 0;

        const statusDiv = document.getElementById('payment_status');
        const statusLabel = document.getElementById('status_label');
        const balanceAmount = document.getElementById('balance_amount');
        const completeBtn = document.getElementById('completeSaleBtn');

        if (!statusDiv) return;

        if (received <= 0) {
            statusDiv.style.display = 'none';
            if (completeBtn) {
                completeBtn.disabled = false;
                completeBtn.innerHTML = '<i class="bi bi-check-circle"></i> Complete Sale (F12)';
            }
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
            if (completeBtn) {
                completeBtn.disabled = false;
                completeBtn.innerHTML = '<i class="bi bi-check-circle"></i> Complete Sale (F12)';
            }
        } else {
            const due = total - received;
            statusDiv.className = 'alert alert-danger mb-3';
            statusLabel.textContent = '⚠️ Balance Due:';
            statusLabel.className = 'text-danger';
            balanceAmount.textContent = `Rs. ${due.toFixed(0)}`;
            balanceAmount.className = 'fw-bold fs-5 text-danger';
            if (completeBtn) {
                completeBtn.disabled = true;
                completeBtn.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Insufficient Payment';
            }
        }
    }
    async function changePackage(index, packageId) {
        if (!packageId) return;

        const item = cart[index];
        if (!item) return;

        try {
            const response = await fetch(`api/get_product_packages.php?product_id=${item.product_id}`);
            const packages = await response.json();
            const selectedPackage = packages.find(p => p.id == packageId);

            if (selectedPackage) {
                const multiplier = parseFloat(selectedPackage.multiplier);
                const packageName = selectedPackage.package_name;

                item.package_multiplier = multiplier;
                item.display_unit = packageName;
                item.selected_package_id = packageId;
                item.display_quantity = item.actual_quantity / multiplier;

                renderCart();
                updateTotal();

                showNotification('success', `Using ${packageName} (1 ${packageName} = ${multiplier} ${item.base_unit})`);
            }
        } catch (error) {
            console.error('Error changing package:', error);
            alert('Failed to change package');
        }
    }


    function getGrandTotal() {
        const totalElement = document.getElementById('grand_total');
        if (!totalElement) return 0;
        const totalText = totalElement.textContent;
        return parseFloat(totalText.replace('Rs.', '').replace(',', '').trim()) || 0;
    }

    function setReceivedAmount(type, value = 0) {
        const totalElement = document.getElementById('grand_total');
        if (!totalElement) return;

        const totalText = totalElement.textContent;
        const total = parseFloat(totalText.replace('Rs.', '').replace(',', '').trim()) || 0;
        const receivedInput = document.getElementById('amount_received');

        if (type === 'exact') {
            receivedInput.value = total;
        } else if (type === 'round') {
            receivedInput.value = Math.ceil(total / value) * value;
        }

        calculateChange();
        receivedInput.focus();
    }

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

    function updateTotal() {
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

        document.getElementById('subtotal').textContent = `Rs. ${subtotal.toFixed(2)}`;
        document.getElementById('grand_total').textContent = `Rs. ${total.toFixed(2)}`;
    }

    async function completeSale() {

        const customerTypeSelect = document.getElementById('customer_type');
        if (!customerTypeSelect) {
            console.error('Customer type select element not found');
            alert('Error: Customer type selector missing. Please refresh the page.');
            return;
        }

        const selectedCustomerType = customerTypeSelect.value;
        if (!selectedCustomerType) {
            alert('⚠️ Please select Customer Type (Retail or Wholesale) before completing the sale!');
            customerTypeSelect.focus();
            customerTypeSelect.style.border = '2px solid red';
            return;
        }
        customerTypeSelect.style.border = '';

        if (!cart || cart.length === 0) {
            alert('Cart is empty!');
            return;
        }


        const subtotal = cart.reduce((sum, item) => sum + (item.total_price || 0), 0);
        const discountInput = document.getElementById('discount_input');
        const discountTypeSelect = document.getElementById('discount_type');

        if (!discountInput || !discountTypeSelect) {
            console.error('Discount elements not found');
            alert('Error: Discount elements missing. Please refresh the page.');
            return;
        }

        const discountInputValue = parseFloat(discountInput.value) || 0;
        const discountType = discountTypeSelect.value;

        let discount = 0;
        if (discountType === 'percent') {
            discount = subtotal * (discountInputValue / 100);
        } else {
            discount = discountInputValue;
        }

        const total = Math.max(0, subtotal - discount);


        const customerName = document.getElementById('customer_name');
        const customerPhone = document.getElementById('customer_phone');
        const paymentMethod = document.getElementById('payment_method');

        if (!customerName || !customerPhone || !paymentMethod) {
            console.error('Customer or payment elements not found');
            alert('Error: Form elements missing. Please refresh the page.');
            return;
        }

        const invoiceData = {
            customer_name: customerName.value || '',
            customer_phone: customerPhone.value || '',
            customer_type: selectedCustomerType,
            subtotal: subtotal,
            discount: discount,
            total: total,
            payment_method: paymentMethod.value || 'cash',
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


        const completeBtn = document.getElementById('completeSaleBtn');
        const originalBtnHtml = completeBtn ? completeBtn.innerHTML : '';
        if (completeBtn) {
            completeBtn.disabled = true;
            completeBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Processing...';
        }

        try {
            const response = await fetch('api/save_invoice.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(invoiceData)
            });

            const result = await response.json();

            if (result.success) {

                window.open(`api/print_receipt.php?id=${result.invoice_id}`, '_blank');


                cart = [];
                if (typeof renderCart === 'function') renderCart();
                if (typeof updateTotal === 'function') updateTotal();


                customerTypeSelect.value = '';
                customerName.value = '';
                customerPhone.value = '';

                const amountReceived = document.getElementById('amount_received');
                const paymentStatus = document.getElementById('payment_status');
                if (amountReceived) amountReceived.value = '0';
                if (paymentStatus) paymentStatus.style.display = 'none';
                if (discountInput) discountInput.value = '0';

                if (typeof showNotification === 'function') {
                    showNotification('success', `Invoice ${result.invoice_no} completed!`);
                } else {
                    alert(`Invoice ${result.invoice_no} completed!`);
                }
            } else {
                alert('Error: ' + (result.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Complete sale error:', error);
            alert('Error completing sale: ' + error.message);
        } finally {

            if (completeBtn) {
                completeBtn.disabled = false;
                completeBtn.innerHTML = originalBtnHtml;
            }
        }
    }
    function clearCart() {
        if (confirm('Clear all items from cart?')) {
            cart = [];
            renderCart();
            updateTotal();
            document.getElementById('amount_received').value = '0';
            document.getElementById('payment_status').style.display = 'none';
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
            action: 'save',
            customer_name: document.getElementById('customer_name').value,
            customer_phone: document.getElementById('customer_phone').value,
            customer_type: customerType,
            subtotal: subtotal,
            discount: discount,
            total: total,
            cart: cart.map(item => ({
                product_id: item.product_id,
                product_name: item.product_name,
                base_unit: item.base_unit,
                display_unit: item.display_unit,
                actual_quantity: item.actual_quantity || item.quantity || 0,
                display_quantity: item.display_quantity || item.quantity,
                unit_price: item.unit_price || 0,
                total_price: item.total_price || 0,
                tier_info: item.tier_info || '',
                max_stock: item.max_stock,
                package_multiplier: item.package_multiplier || 1,
                selected_package_id: item.selected_package_id
            }))
        };

        try {
            const response = await fetch('api/hold_invoice.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(invoiceData)
            });

            const result = await response.json();

            if (result.success) {
                showNotification('success', `Invoice held! Reference: ${result.hold_ref}`);
                cart = [];
                renderCart();
                updateTotal();
                document.getElementById('customer_name').value = '';
                document.getElementById('customer_phone').value = '';
                document.getElementById('customer_type').value = '';
                document.getElementById('amount_received').value = '0';
                document.getElementById('payment_status').style.display = 'none';
                document.getElementById('discount_input').value = '0';
            } else {
                showNotification('error', result.error || 'Failed to hold invoice');
            }
        } catch (error) {
            console.error('Error holding invoice:', error);
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

            const existingModal = document.getElementById('heldInvoicesModal');
            if (existingModal) {
                existingModal.remove();
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
                    `<div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Reference</th>
                                                <th>Customer</th>
                                                <th>Type</th>
                                                <th>Items</th>
                                                <th>Total</th>
                                                <th>Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${result.held_invoices.map(inv => {
                        const cartData = typeof inv.cart_data === 'string' ? JSON.parse(inv.cart_data) : inv.cart_data;
                        return `
                                                    <tr>
                                                        <td><strong>${inv.hold_reference}</strong></td>
                                                        <td>${inv.customer_name || 'Walk-in'}</td>
                                                        <td><span class="badge bg-${inv.customer_type === 'wholesale' ? 'success' : 'info'}">${inv.customer_type}</span></td>
                                                        <td>${cartData.length} items</td>
                                                        <td><strong>Rs. ${parseFloat(inv.total_amount).toFixed(2)}</strong></td>
                                                        <td><small>${new Date(inv.created_at).toLocaleString()}</small></td>
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
                                    </table>
                                </div>`}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

            document.body.insertAdjacentHTML('beforeend', modalHtml);
            const modal = new bootstrap.Modal(document.getElementById('heldInvoicesModal'));
            modal.show();

            document.getElementById('heldInvoicesModal').addEventListener('hidden.bs.modal', function () {
                this.remove();
            });

        } catch (error) {
            console.error('Error loading held invoices:', error);
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
                const cartData = typeof inv.cart_data === 'string' ? JSON.parse(inv.cart_data) : inv.cart_data;

                document.getElementById('customer_name').value = inv.customer_name || '';
                document.getElementById('customer_phone').value = inv.customer_phone || '';
                document.getElementById('customer_type').value = inv.customer_type;
                customerType = inv.customer_type;
                cart = cartData;
                document.getElementById('discount_input').value = inv.discount_amount || 0;
                document.getElementById('discount_type').value = 'fixed';

                renderCart();
                updateTotal();

                const modal = bootstrap.Modal.getInstance(document.getElementById('heldInvoicesModal'));
                if (modal) modal.hide();

                showNotification('success', 'Invoice resumed!');
            } else {
                showNotification('error', result.error || 'Failed to resume invoice');
            }
        } catch (error) {
            console.error('Error resuming invoice:', error);
            showNotification('error', 'Error resuming invoice');
        }
    }

    async function deleteHeldInvoice(holdId) {
        if (!confirm('Delete this held invoice permanently?')) return;

        try {
            await fetch('api/hold_invoice.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', hold_id: holdId })
            });

            showNotification('success', 'Held invoice deleted!');

            const modal = bootstrap.Modal.getInstance(document.getElementById('heldInvoicesModal'));
            if (modal) {
                modal.hide();
                showHeldInvoices();
            }
        } catch (error) {
            console.error('Error deleting invoice:', error);
            showNotification('error', 'Error deleting invoice');
        }
    }


    async function loadQuickProducts() {
        const container = document.getElementById('quick_products');
        if (!container) {
            console.error('Quick products container not found');
            return;
        }

        container.innerHTML = '<div class="col-12 text-center py-3"><div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';

        try {
            const response = await fetch('api/search_product.php?mode=popular');
            const products = await response.json();

            let html = '';

            if (!products || products.length === 0) {
                html = '<div class="col-12 text-muted text-center py-3">No products available</div>';
            } else {
                products.forEach(product => {
                    if (product.status === 'active') {
                        html += `
                            <div class="col-6 mb-3">
                                <button class="btn btn-outline-primary w-100 h-100" 
                                        onclick="addToCart(${product.id}, '${product.name.replace(/'/g, "\\'")}', '${product.unit || 'piece'}', ${product.current_stock || 0})"
                                        style="min-height: 60px; font-size: 12px; padding: 5px;">
                                    <strong>${escapeHtml(product.name)}</strong>
                                </button>
                            </div>
                        `;
                    }
                });
            }

            container.innerHTML = html;

        } catch (error) {
            console.error('Error loading quick products:', error);
            container.innerHTML = '<div class="col-12 text-danger text-center py-3">Failed to load products</div>';
        }
    }


    async function loadCustomers() {
        try {
            const response = await fetch("api/get_customers.php");
            const customers = await response.json();

            let options = '<option value="">-- Walk-in Customer --</option>';
            if (customers && customers.length > 0) {
                customers.forEach((customer) => {
                    options += `<option value="${customer.id}" data-name="${customer.name.replace(/"/g, "&quot;")}" data-phone="${customer.phone || ""}" data-type="${customer.customer_type}">${customer.name} ${customer.phone ? "(" + customer.phone + ")" : ""}</option>`;
                });
            }

            const customerSelect = document.getElementById("customer_select");
            if (customerSelect) {
                customerSelect.innerHTML = options;
            }
        } catch (error) {
            console.error("Error loading customers:", error);
        }
    }


    document.addEventListener('DOMContentLoaded', function () {


        loadQuickProducts();


        loadCustomers();


        updateTotal();


        const searchInput = document.getElementById('search_product');
        if (searchInput) {
            setTimeout(function () {
                searchInput.focus();
            }, 500);
        }
    });

    document.addEventListener('keydown', function (e) {

        if (e.key === 'F12' || e.keyCode === 123) {
            e.preventDefault();
            e.stopPropagation();


            if (typeof completeSale === 'function') {

                if (cart && cart.length > 0) {

                    if (checkCustomerType()) {
                        completeSale();
                    }
                } else {
                    showNotification('error', 'Cart is empty! Add items before completing sale.');
                }
            } else {
                console.error('completeSale function not found');
                showNotification('error', 'Please use the Complete Sale button');
            }
            return false;
        }
    });


    document.addEventListener('keydown', function (e) {

        if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
            e.preventDefault();
            e.stopPropagation();

            if (typeof completeSale === 'function') {
                if (cart && cart.length > 0) {
                    if (checkCustomerType()) {
                        completeSale();
                    }
                } else {
                    showNotification('error', 'Cart is empty! Add items before completing sale.');
                }
            }
            return false;
        }
    });


</script>