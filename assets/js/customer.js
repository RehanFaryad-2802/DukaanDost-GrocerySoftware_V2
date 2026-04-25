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

  if (cart.length > 0) {
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

  if (cart.length > 0) {
    recalculateCartPrices();
  }

  showNotification("success", `Customer: ${name} selected`);
}

function clearCustomerSearch() {
  document.getElementById("customer_search").value = "";
  document.getElementById("customer_results").innerHTML = "";
  document.getElementById("customer_results").style.display = "none";
}
