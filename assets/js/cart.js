async function renderCart() {
  const tbody = document.getElementById("cart_items");
  const countEl = document.getElementById("cart_item_count");
  if (countEl)
    countEl.textContent = `${cart.length} item${cart.length !== 1 ? "s" : ""}`;

  if (cart.length === 0) {
    tbody.innerHTML =
      '<tr><td colspan="6" class="text-center text-muted py-4">No items in cart.</td></tr>';
    return;
  }

  let html = "";
  for (let i = 0; i < cart.length; i++) {
    const item = cart[i];

    const productName = item.product_name || "Unknown";
    const displayQty = item.display_quantity || item.quantity || 1;
    const displayUnit = item.display_unit || item.base_unit || "Piece";
    const baseUnit = item.base_unit || "Piece";
    const totalPrice = item.total_price || 0;
    const unitPrice = totalPrice / displayQty;
    const tierInfo = item.tier_info || "";
    const productId = item.product_id || 0;

    let packageOptions = `<option value="${baseUnit}" data-multiplier="1">${baseUnit}</option>`;

    if (productId > 0) {
      try {
        const response = await fetch(
          `api/get_product_packages.php?product_id=${productId}`,
        );
        const text = await response.text();
        if (!text.startsWith("<")) {
          const packages = JSON.parse(text);
          packages.forEach((pkg) => {
            const selected = displayUnit === pkg.package_name ? "selected" : "";
            packageOptions += `<option value="${pkg.package_name}" data-multiplier="${pkg.multiplier}" ${selected}>${pkg.package_name}</option>`;
          });
        }
      } catch (e) {
        console.error("Failed to load packages:", e);
      }
    }

    html += `
            <tr>
                <td><strong>${productName}</strong><br><small>${tierInfo}</small></td>
                <td><input type="number" class="form-control form-control-sm" value="${displayQty}" step="1" min="0" onchange="updateCartItemQuantity(${i}, this.value)"></td>
                <td>
                    <select class="form-select form-select-sm" onchange="changeItemPackage(${i}, this.value, this.options[this.selectedIndex].dataset.multiplier)">
                        ${packageOptions}
                    </select>
                </td>
                <td class="text-end">Rs. ${unitPrice.toFixed(2)}</td>
                <td class="text-end">Rs. ${totalPrice.toFixed(2)}</td>
                <td><button class="btn btn-sm btn-danger" onclick="removeFromCart(${i})"><i class="bi bi-x"></i></button></td>
            </tr>
        `;
  }
  tbody.innerHTML = html;
}
const originalClearCart2 = clearCart;
clearCart = function () {
  if (confirm("Clear all items from cart?")) {
    originalClearCart2();
    hasUnsavedChanges = false;
  }
};

const originalRenderCart2 = renderCart;
renderCart = function () {
  originalRenderCart2();
  hasUnsavedChanges = cart.length > 0;
};

const originalHoldInvoice = holdInvoice;
holdInvoice = async function () {
  await originalHoldInvoice();
  hasUnsavedChanges = false;
};
async function holdInvoice() {
  if (cart.length === 0) {
    showNotification("error", "Cart is empty!");
    return;
  }

  const subtotal = cart.reduce((sum, item) => sum + item.total_price, 0);
  const discountInput =
    parseFloat(document.getElementById("discount_input").value) || 0;
  const discountType = document.getElementById("discount_type").value;

  let discount = 0;
  if (discountType === "percent") {
    discount = subtotal * (discountInput / 100);
  } else {
    discount = discountInput;
  }

  const total = Math.max(0, subtotal - discount);

  const holdData = {
    action: "save",
    customer_name: document.getElementById("customer_name").value,
    customer_phone: document.getElementById("customer_phone").value,
    customer_type: customerType,
    cart: cart,
    subtotal: subtotal,
    discount: discount,
    total: total,
  };

  try {
    const response = await fetch("api/hold_invoice.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(holdData),
    });

    const result = await response.json();

    if (result.success) {
      showNotification("success", `Invoice held! Ref: ${result.hold_ref}`);

      cart = [];
      renderCart();
      updateTotal();
      document.getElementById("customer_name").value = "";
      document.getElementById("customer_phone").value = "";
      document.getElementById("discount_input").value = "0";
    } else {
      showNotification("error", "Failed to hold invoice");
    }
  } catch (error) {
    showNotification("error", "Error holding invoice");
  }
}
