<script>
    <?php if ($editing_invoice): ?>
            (async function () {

                customerType = '<?php echo $editing_invoice['customer_type']; ?>';
                document.getElementById('customer_type').value = customerType;
                updateCustomerType();

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
                        max_stock: 999999,
                        package_multiplier: 1
                    });
                }


                renderCart();
                updateTotal();

                // Change button to Update mode
                const completeBtn = document.getElementById('completeSaleBtn');
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
                const mainContainer = document.querySelector('.row');
                if (mainContainer) mainContainer.insertBefore(banner, mainContainer.firstChild);

                window.EDIT_MODE = true;
                window.OLD_INVOICE_ID = <?php echo $editing_invoice['id']; ?>;

                // Override completeSale for edit mode
                window.originalCompleteSale = completeSale;
                completeSale = async function () {
                    await updateExistingInvoice(window.OLD_INVOICE_ID);
                };

                // Hide payment section in edit mode
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

    // Additional overrides for edit mode
    const originalCompleteSale = completeSale;
    completeSale = async function () {
        const total = getGrandTotal();
        const received = parseFloat(document.getElementById('amount_received').value) || 0;
        const paymentMethod = document.getElementById('payment_method').value;

        if (paymentMethod !== 'cash') {
            document.getElementById('amount_received').value = total;
        }

        await originalCompleteSale();
    };
</script>