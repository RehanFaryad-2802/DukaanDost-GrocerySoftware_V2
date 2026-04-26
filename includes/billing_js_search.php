<script>
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
    let quickAddTimeout = null;

    async function searchProduct(query) {
        if (query.length < 2) {
            document.getElementById('search_results').innerHTML = '';
            document.getElementById('quick_add_section').style.display = 'none';
            searchResults = [];
            selectedResultIndex = -1;
            return;
        }

        try {
            const response = await fetch(`api/search_product.php?q=${encodeURIComponent(query)}&customer_type=${customerType}`);
            const products = await response.json();

            const exactMatchCheck = await fetch(`api/check_product_exists.php?name=${encodeURIComponent(query)}`);
            const exactMatchResult = await exactMatchCheck.json();

            if (exactMatchResult.exists) {
                document.getElementById('quick_add_section').style.display = 'none';
            }

            if (products.length === 0) {
                if (!exactMatchResult.exists) {
                    document.getElementById('quick_add_section').style.display = 'block';
                    document.getElementById('quick_product_name').value = query;
                    await loadQuickFormData();
                } else {
                    document.getElementById('search_results').innerHTML = `
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> 
                        Product "${escapeHtml(query)}" exists but couldn't be found in search. 
                        Try a different spelling or check product status.
                    </div>
                `;
                    document.getElementById('quick_add_section').style.display = 'none';
                }

                searchResults = [];
                selectedResultIndex = -1;
                return;
            } else {
                document.getElementById('quick_add_section').style.display = 'none';
            }

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
                    <small>Stock: ${product.current_stock} ${product.unit} | Price: ${product.pricing_tiers || 'N/A'}</small>
                </a>
            `;
            });

            document.getElementById('search_results').innerHTML = html;
        } catch (error) {
            console.error('Search error:', error);
        }
    }

    async function loadQuickFormData() {
        // Load units
        try {
            const unitsResponse = await fetch('api/get_units.php');
            const units = await unitsResponse.json();

            const unitSelect = document.getElementById('quick_unit');
            if (unitSelect && units.length > 0 && unitSelect.options.length <= 1) {
                unitSelect.innerHTML = '<option value="">Select Unit *</option>';
                units.forEach(unit => {
                    unitSelect.innerHTML += `<option value="${unit.symbol}">${unit.name} (${unit.symbol})</option>`;
                });
            }
        } catch (e) {
            console.error('Error loading units:', e);
            // Fallback units
            const unitSelect = document.getElementById('quick_unit');
            if (unitSelect && unitSelect.options.length <= 1) {
                unitSelect.innerHTML = '<option value="">Select Unit *</option>';
                const fallbackUnits = ['kg', 'g', 'liter', 'ml', 'piece', 'dozen', 'packet'];
                fallbackUnits.forEach(unit => {
                    unitSelect.innerHTML += `<option value="${unit}">${unit}</option>`;
                });
            }
        }

        // Load categories
        try {
            const catResponse = await fetch('api/get_categories.php');
            const categories = await catResponse.json();

            const catSelect = document.getElementById('quick_category');
            if (catSelect && categories.length > 0 && catSelect.options.length <= 1) {
                catSelect.innerHTML = '<option value="">Select Category</option>';
                categories.forEach(cat => {
                    catSelect.innerHTML += `<option value="${cat.name}">${cat.name}</option>`;
                });
            }
        } catch (e) {
            console.error('Error loading categories:', e);
        }
    }

    async function addToCartOnly(event) {
        // Prevent default if event exists
        if (event && event.preventDefault) event.preventDefault();

        const productName = document.getElementById('quick_product_name').value.trim();
        const unit = document.getElementById('quick_unit').value;
        const retailPrice = parseFloat(document.getElementById('quick_retail_price').value);
        const quantity = parseFloat(document.getElementById('quick_quantity').value);

        if (!productName) {
            alert('Please enter product name!');
            document.getElementById('quick_product_name').focus();
            return;
        }
        if (!unit) {
            alert('Please select a unit!');
            document.getElementById('quick_unit').focus();
            return;
        }
        if (isNaN(retailPrice) || retailPrice <= 0) {
            alert('Please enter a valid retail price!');
            document.getElementById('quick_retail_price').focus();
            return;
        }
        if (isNaN(quantity) || quantity <= 0) {
            alert('Please enter valid quantity!');
            document.getElementById('quick_quantity').focus();
            return;
        }

        // Get the button that was clicked
        const addBtn = event ? event.target : document.activeElement;
        const originalHtml = addBtn.innerHTML;
        addBtn.innerHTML = '<span class="loading-spinner"></span> Adding...';
        addBtn.disabled = true;

        try {
            const tempId = -Date.now();
            const totalPrice = retailPrice * quantity;

            cart.unshift({
                product_id: tempId,
                product_name: productName,
                base_unit: unit,
                display_unit: unit,
                actual_quantity: quantity,
                display_quantity: quantity,
                unit_price: retailPrice,
                total_price: totalPrice,
                tier_info: 'Manual entry (not saved)',
                max_stock: 999999,
                package_multiplier: 1,
                is_temp: true,
                packages: null,
                selected_package_id: null
            });

            await renderCart();
            updateTotal();

            // Clear the quick add form
            document.getElementById('quick_product_name').value = '';
            document.getElementById('quick_retail_price').value = '';
            document.getElementById('quick_wholesale_price').value = '';
            document.getElementById('quick_quantity').value = '1';
            document.getElementById('quick_unit').value = '';
            document.getElementById('quick_category').value = '';
            document.getElementById('quick_add_section').style.display = 'none';
            document.getElementById('search_product').value = '';

            if (typeof showNotification === 'function') {
                showNotification('success', `"${productName}" added to cart!`);
            } else {
                alert(`"${productName}" added to cart!`);
            }

        } catch (error) {
            console.error('Error:', error);
            alert('Failed to add product to cart');
        } finally {
            addBtn.innerHTML = originalHtml;
            addBtn.disabled = false;
        }
    }

    async function addToProductListAndCart(event) {
        // Prevent default if event exists
        if (event && event.preventDefault) event.preventDefault();

        const productName = document.getElementById('quick_product_name').value.trim();
        const unit = document.getElementById('quick_unit').value;
        const category = document.getElementById('quick_category').value || null;
        const retailPrice = parseFloat(document.getElementById('quick_retail_price').value);
        const wholesalePrice = parseFloat(document.getElementById('quick_wholesale_price').value) || 0;
        const quantity = parseFloat(document.getElementById('quick_quantity').value);

        if (!productName) {
            alert('Please enter product name!');
            document.getElementById('quick_product_name').focus();
            return;
        }
        if (!unit) {
            alert('Please select a unit!');
            document.getElementById('quick_unit').focus();
            return;
        }
        if (isNaN(retailPrice) || retailPrice <= 0) {
            alert('Please enter a valid retail price!');
            document.getElementById('quick_retail_price').focus();
            return;
        }
        if (isNaN(quantity) || quantity <= 0) {
            alert('Please enter valid quantity!');
            document.getElementById('quick_quantity').focus();
            return;
        }

        // Check if product already exists
        try {
            const checkResponse = await fetch(`api/check_product_exists.php?name=${encodeURIComponent(productName)}`);
            const checkResult = await checkResponse.json();

            if (checkResult.exists) {
                const addExisting = confirm(
                    `Product "${productName}" already exists!\n\n` +
                    `Would you like to add the existing product to cart instead?`
                );

                if (addExisting) {
                    await addToCartWithQuantity(
                        checkResult.product_id,
                        checkResult.product_name,
                        checkResult.unit || unit,
                        999999,
                        quantity
                    );

                    document.getElementById('quick_add_section').style.display = 'none';
                    document.getElementById('search_product').value = '';

                    if (typeof showNotification === 'function') {
                        showNotification('success', `"${checkResult.product_name}" added to cart!`);
                    } else {
                        alert(`"${checkResult.product_name}" added to cart!`);
                    }
                    return;
                }
                return;
            }
        } catch (error) {
            console.error('Error checking product:', error);
        }

        // Get the button that was clicked
        const addBtn = event ? event.target : document.activeElement;
        const originalHtml = addBtn.innerHTML;
        addBtn.innerHTML = '<span class="loading-spinner"></span> Saving...';
        addBtn.disabled = true;

        try {
            const code = 'PRD' + Date.now().toString().slice(-8);

            const formData = new FormData();
            formData.append('code', code);
            formData.append('name', productName);
            formData.append('category', category);
            formData.append('unit', unit);
            formData.append('description', '');
            formData.append('current_stock', '999999');
            formData.append('min_stock_alert', '10');
            formData.append('purchase_price', retailPrice * 0.8);
            formData.append('retail_price', retailPrice);
            formData.append('wholesale_price', wholesalePrice);
            formData.append('wholesale_min_qty', '5');

            const response = await fetch('api/quick_add_product.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                // Calculate price based on customer type
                let unitPrice = retailPrice;
                if (customerType === 'wholesale' && wholesalePrice > 0) {
                    unitPrice = wholesalePrice;
                }
                const totalPrice = unitPrice * quantity;

                cart.unshift({
                    product_id: result.product_id,
                    product_name: productName,
                    base_unit: unit,
                    display_unit: unit,
                    actual_quantity: quantity,
                    display_quantity: quantity,
                    unit_price: unitPrice,
                    total_price: totalPrice,
                    tier_info: `Retail: Rs.${retailPrice} | Wholesale: Rs.${wholesalePrice}`,
                    max_stock: 999999,
                    package_multiplier: 1,
                    is_temp: false,
                    packages: null,
                    selected_package_id: null
                });

                await renderCart();
                updateTotal();

                // Clear the quick add form
                document.getElementById('quick_product_name').value = '';
                document.getElementById('quick_retail_price').value = '';
                document.getElementById('quick_wholesale_price').value = '';
                document.getElementById('quick_quantity').value = '1';
                document.getElementById('quick_unit').value = '';
                document.getElementById('quick_category').value = '';
                document.getElementById('quick_add_section').style.display = 'none';
                document.getElementById('search_product').value = '';

                if (typeof showNotification === 'function') {
                    showNotification('success', `"${productName}" saved and added to cart!`);
                } else {
                    alert(`"${productName}" saved and added to cart!`);
                }

                // Reload quick products grid
                if (typeof loadQuickProducts === 'function') {
                    loadQuickProducts();
                }
            } else {
                alert('Error: ' + (result.error || 'Unknown error'));
            }

        } catch (error) {
            console.error('Error saving product:', error);
            alert('Failed to save product. Please try again.');
        } finally {
            addBtn.innerHTML = originalHtml;
            addBtn.disabled = false;
        }
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
                item.product_id === productId
            );

            if (existingIndex >= 0) {
                const item = cart[existingIndex];
                const newQty = (item.actual_quantity || 0) + quantity;

                if (newQty > maxStock) {
                    alert(`Only ${maxStock} ${unit} available!`);
                    return;
                }

                // Update actual quantity
                item.actual_quantity = newQty;

                // Update display quantity based on current package selection
                if (item.package_multiplier && item.package_multiplier > 1) {
                    item.display_quantity = newQty / item.package_multiplier;
                } else {
                    item.display_quantity = newQty;
                }

                // Update price based on new actual quantity
                const newPriceData = await fetch('api/get_price.php', {
                    method: 'POST',
                    body: new FormData().append('product_id', productId).append('quantity', newQty).append('customer_type', customerType)
                });
                const newPrice = await newPriceData.json();

                item.unit_price = newPrice.unit_price || priceData.unit_price || 0;
                item.total_price = (newPrice.unit_price || priceData.unit_price || 0) * newQty;
                item.tier_info = newPrice.tier_info || priceData.tier_info || '';

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
                    package_multiplier: 1,
                    packages: null,
                    selected_package_id: null
                });
            }

            renderCart();
            updateTotal();
        } catch (error) {
            console.error('Add to cart error:', error);
            alert('Error adding product to cart');
        }
    }

    // Handle search input with debounce
    function handleSearchInput(query) {
        if (quickAddTimeout) clearTimeout(quickAddTimeout);

        quickAddTimeout = setTimeout(() => {
            searchProduct(query);
        }, 300);
    }

    function clearSearch() {
        document.getElementById('search_product').value = '';
        document.getElementById('search_results').innerHTML = '';
        document.getElementById('quick_add_section').style.display = 'none';
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
        const quickAddVisible = document.getElementById('quick_add_section').style.display !== 'none';

        if (e.key === 'Escape') {
            if (isSearchFocused) {
                e.preventDefault();
                clearSearch();
            }
            return;
        }

        // If quick add section is visible, don't handle arrow keys for search results
        if (quickAddVisible) return;

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
    });
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
    function highlightSelectedResult() {
        const items = document.querySelectorAll('.search-result-item');
        items.forEach((item, index) => {
            item.classList.toggle('selected', index === selectedResultIndex);
            if (index === selectedResultIndex) item.scrollIntoView({ block: 'nearest' });
        });
    }
</script>