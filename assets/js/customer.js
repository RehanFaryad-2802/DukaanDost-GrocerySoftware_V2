async function loadCustomers() {
  try {
    const response = await fetch("api/get_customers.php");
    const customers = await response.json();

    let options = '<option value="">-- Walk-in Customer --</option>';
    customers.forEach((customer) => {
      options += `<option value="${customer.id}" data-name="${customer.name.replace(/"/g, "&quot;")}" data-phone="${customer.phone || ""}" data-type="${customer.customer_type}">${customer.name} ${customer.phone ? "(" + customer.phone + ")" : ""}</option>`;
    });

    document.getElementById("customer_select").innerHTML = options;
  } catch (error) {
    console.error("Error loading customers:", error);
  }
}

function updatePricingNote() {
  const type = document.getElementById('customer_type').value;
  const note = document.getElementById('pricing_note');

  if (!note) return;

  if (type === 'wholesale') {
    note.innerHTML = '📦 Wholesale pricing applied';
    note.className = 'text-primary';
  } else if (type === 'retail') {
    note.innerHTML = '🛒 Retail pricing applied';
    note.className = 'text-success';
  } else {
    note.innerHTML = '⚠️ Please select Retail or Wholesale';
    note.className = 'text-danger';
  }
}

async function recalculateCartPrices() {
  if (!cart || cart.length === 0) return;

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

  if (typeof renderCart === 'function') renderCart();
  if (typeof updateTotal === 'function') updateTotal();
}

function onCustomerSelect(customerId) {
  if (!customerId) {
    document.getElementById("customer_name").value = "";
    document.getElementById("customer_phone").value = "";
    document.getElementById("customer_name").placeholder = "Walk-in customer";
    document.getElementById("customer_name").readOnly = false;
    document.getElementById("customer_name").style.backgroundColor = "";
    document.getElementById("customer_phone").readOnly = false;
    document.getElementById("customer_phone").style.backgroundColor = "";
    return;
  }

  const select = document.getElementById("customer_select");
  const option = select.options[select.selectedIndex];

  const name = option.dataset.name;
  const phone = option.dataset.phone;
  const type = option.dataset.type;

  document.getElementById("customer_name").value = name;
  document.getElementById("customer_phone").value = phone;
  document.getElementById("customer_type").value = type;

  document.getElementById("customer_name").readOnly = true;
  document.getElementById("customer_name").style.backgroundColor = "#f8f9fa";
  document.getElementById("customer_phone").readOnly = true;
  document.getElementById("customer_phone").style.backgroundColor = "#f8f9fa";

  customerType = type;
  updatePricingNote();

  if (cart && cart.length > 0) {
    recalculateCartPrices();
  }
}

function setCustomerInDropdown(customerName, customerPhone) {
  const select = document.getElementById("customer_select");
  for (let i = 0; i < select.options.length; i++) {
    if (select.options[i].dataset.name === customerName) {
      select.selectedIndex = i;
      onCustomerSelect(select.value);
      return;
    }
  }

  document.getElementById("customer_name").value = customerName || "";
  document.getElementById("customer_phone").value = customerPhone || "";
}

async function searchCustomer(query) {
  if (query.length < 2) {
    document.getElementById("customer_results").innerHTML = "";
    document.getElementById("customer_results").style.display = "none";
    return;
  }

  try {
    const response = await fetch(
      `api/search_customers.php?q=${encodeURIComponent(query)}`,
    );
    const customers = await response.json();

    let html = "";
    customers.forEach((customer) => {
      html += `
                <a href="#" class="list-group-item list-group-item-action" onclick="selectCustomer(${customer.id}, '${customer.name.replace(/'/g, "\\'")}', '${customer.phone || ""}', '${customer.customer_type}'); return false;">
                    <div class="d-flex justify-content-between">
                        <strong>${customer.name}</strong>
                        <span class="badge bg-${customer.customer_type === "wholesale" ? "success" : "info"}">${customer.customer_type}</span>
                    </div>
                    ${customer.phone ? `<small>📞 ${customer.phone}</small>` : ""}
                </a>
            `;
    });

    const resultsDiv = document.getElementById("customer_results");
    resultsDiv.innerHTML = html;
    resultsDiv.style.display = customers.length > 0 ? "block" : "none";
  } catch (error) {
    console.error("Customer search error:", error);
  }
}

function selectCustomer(id, name, phone, type) {
  document.getElementById("customer_name").value = name;
  document.getElementById("customer_phone").value = phone;
  document.getElementById("customer_type").value = type;
  customerType = type;
  updatePricingNote();

  document.getElementById("customer_search").value = "";
  document.getElementById("customer_results").innerHTML = "";
  document.getElementById("customer_results").style.display = "none";

  if (cart && cart.length > 0) {
    recalculateCartPrices();
  }

  if (typeof showNotification === 'function') {
    showNotification("success", `Customer: ${name} selected`);
  } else {
  }
}

function clearCustomerSearch() {
  document.getElementById("customer_search").value = "";
  document.getElementById("customer_results").innerHTML = "";
  document.getElementById("customer_results").style.display = "none";
}