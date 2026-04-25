<script>
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
async function addToCartWithQuantity(productId, productName, unit, maxStock, quantity) {
    // First, get the correct price for this SPECIFIC quantity
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

            // Recalculate price for the NEW TOTAL quantity with correct tier
            const formData2 = new FormData();
            formData2.append('product_id', productId);
            formData2.append('quantity', newQty);
            formData2.append('customer_type', customerType);

            try {
                const response2 = await fetch('api/get_price.php', { method: 'POST', body: formData2 });
                const text2 = await response2.text();
                
                if (!text2.startsWith('<')) {
                    const newPriceData = JSON.parse(text2);
                    item.actual_quantity = newQty;
                    item.display_quantity = (item.display_quantity || 0) + quantity;
                    item.unit_price = newPriceData.unit_price || 0;
                    item.total_price = newPriceData.total_price || 0;
                    item.tier_info = newPriceData.tier_info || '';
                    
                    console.log(`Price updated: ${newQty} ${unit} @ ${item.unit_price} = ${item.total_price}`);
                }
            } catch (e) {
                console.error('Error recalculating price:', e);
                // Fallback: use the existing unit price (not ideal)
                item.actual_quantity = newQty;
                item.display_quantity = (item.display_quantity || 0) + quantity;
                item.unit_price = priceData.unit_price || 0;
                item.total_price = (priceData.unit_price || 0) * newQty;
                item.tier_info = priceData.tier_info || '';
            }
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
                max_stock: maxStock,
                package_multiplier: 1
            });
        }

        renderCart();
        updateTotal();
    } catch (error) {
        console.error('Add to cart error:', error);
        alert('Error adding product to cart');
    }
}
    async function addToCart(productId, productName, unit, maxStock) {
        const quantity = prompt(`Enter quantity for ${productName} (${unit}):`, '1');
        if (!quantity || quantity <= 0) return;
        if (parseFloat(quantity) > maxStock) {
            alert(`Only ${maxStock} ${unit} available in stock!`);
            return;
        }

        await addToCartWithQuantity(productId, productName, unit, maxStock, parseFloat(quantity));
        document.getElementById('search_product').value = '';
        document.getElementById('search_results').innerHTML = '';
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

            document.getElementById('search_product').value = '';
            document.getElementById('search_results').innerHTML = '';
            searchResults = [];
            selectedResultIndex = -1;
            document.getElementById('search_product').focus();

        } catch (error) {
            // Fallback if packages fail
            const quantity = prompt(`Enter quantity for ${product.name} (${product.unit}):`, '1');
            if (!quantity || quantity <= 0) return;

            await addToCartWithQuantity(
                product.id,
                product.name,
                product.unit,
                product.current_stock,
                parseFloat(quantity)
            );

            document.getElementById('search_product').value = '';
            document.getElementById('search_results').innerHTML = '';
            searchResults = [];
            selectedResultIndex = -1;
            document.getElementById('search_product').focus();
        }
    }

    function selectProductByClick(index) {
        selectedResultIndex = index;
        const product = searchResults[index];
        if (product) {
            selectProductFromSearch(product);
        }
    }

    function handleSearchInput(query) {
        if (window.isNavigating) return;
        searchProduct(query);
    }

    function clearSearch() {
        document.getElementById('search_product').value = '';
        document.getElementById('search_results').innerHTML = '';
        searchResults = [];
        selectedResultIndex = -1;
        document.getElementById('search_product').focus();
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

    // Keyboard Navigation
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
                window.isNavigating = true;

                if (selectedResultIndex < resultItems.length - 1) selectedResultIndex++;

                highlightSelectedResult();
                setTimeout(() => { window.isNavigating = false; }, 100);
                return;
            }

            if (e.key === 'ArrowUp') {
                e.preventDefault();
                window.isNavigating = true;

                if (selectedResultIndex > 0) selectedResultIndex--;

                highlightSelectedResult();
                setTimeout(() => { window.isNavigating = false; }, 100);
                return;
            }

            if (e.key === 'Enter') {
                e.preventDefault();
                if (selectedResultIndex >= 0 && selectedResultIndex < searchResults.length) {
                    selectProductFromSearch(searchResults[selectedResultIndex]);
                }
                return;
            }
        }

        // Auto-focus search on letter key press
        if (!isInput && !modalOpen) {
            const isRegularKey = e.key.length === 1 && !e.altKey && !e.ctrlKey && !e.metaKey;
            if (isRegularKey) {
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

    function highlightSelectedResult() {
        const items = document.querySelectorAll('.search-result-item');
        items.forEach((item, index) => {
            item.classList.toggle('selected', index === selectedResultIndex);
            if (index === selectedResultIndex) item.scrollIntoView({ block: 'nearest' });
        });
    }

    // Render Cart (called from cart.js or other files)
    function renderCart() {
        // This function is usually defined in assets/js/cart.js
        // If not present, you may need to implement basic rendering here
        console.log('Cart rendered with', cart.length, 'items');
        document.getElementById('cart_item_count').textContent = `(${cart.length})`;
    }
</script>