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
    // Change package for cart item
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

                // Store package info in item
                item.package_multiplier = multiplier;
                item.display_unit = packageName;
                item.selected_package_id = packageId;

                // Convert display quantity
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
            alert('Error: ' + (result.error || 'Unknown error'));
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

    // Load quick products on page load
    document.addEventListener('DOMContentLoaded', function () {
        loadQuickProducts();
        loadCustomers();
        updateTotal();

        document.getElementById('customer_type').addEventListener('change', function () {
            this.style.border = '';
        });
    });

    // Global variables for unsaved changes warning
    let hasUnsavedChanges = false;

</script>