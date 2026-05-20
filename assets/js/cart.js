// assets/js/cart.js
let userRole = document.getElementById("user_role")?.value || "cashier";
// Units cache for singular/plural/conversion
let _unitsCache = {};
(async function () {
  try {
    const res = await fetch("api/get_units.php");
    const units = await res.json();
    units.forEach((u) => {
      _unitsCache[u.symbol] = u;
    });
  } catch (e) {}
})();

function formatUnit(symbol, qty) {
  const u = _unitsCache[symbol];
  if (!u) return symbol;
  // Sub-unit conversion (e.g. 0.5 کلو → 500 گرام)
  if (u.sub_factor && qty < 1 && qty > 0) {
    const converted = qty * u.sub_factor;
    const subLabel =
      converted == 1
        ? u.sub_singular || symbol
        : u.sub_plural || u.sub_singular || symbol;
    return converted % 1 === 0
      ? `${converted} ${subLabel}`
      : `${parseFloat(converted.toFixed(3))} ${subLabel}`;
  }
  // Singular/plural
  if (qty == 1) return u.name_singular || u.name || symbol;
  return u.name_plural || u.name_singular || u.name || symbol;
}
const isAdmin = userRole === "admin";
let productPackagesCache = {};

// Fetch packages for a product
async function getProductPackages(productId) {
  if (productPackagesCache[productId]) {
    return productPackagesCache[productId];
  }

  try {
    const response = await fetch(
      `api/get_product_packages.php?product_id=${productId}`,
    );
    const packages = await response.json();
    productPackagesCache[productId] = packages;
    return packages;
  } catch (error) {
    console.error("Error fetching packages:", error);
    return [];
  }
}

async function renderCart() {
  const tbody = document.getElementById("cart_items");
  const cartCount = document.getElementById("cart_item_count");

  if (!tbody) return;

  if (cart.length === 0) {
    tbody.innerHTML =
      '<tr><td colspan="6" class="text-center text-muted py-4">No items in cart. Search and add products above.</td></tr>';
    if (cartCount) cartCount.textContent = "(0)";
    return;
  }

  if (cartCount) cartCount.textContent = `(${cart.length})`;

  let html = "";

  for (let index = 0; index < cart.length; index++) {
    const item = cart[index];

    // Fetch packages for this product if not already fetched
    if (!item.packages) {
      item.packages = await getProductPackages(item.product_id);
    }

    let displayQty = item.display_quantity || item.actual_quantity || 0;
    displayQty = parseFloat(displayQty) || 0;

    const displayUnit = item.display_unit || item.base_unit || "Piece";
    const formattedUnit = formatUnit(item.base_unit || displayUnit, displayQty);
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
      item.packages.forEach((pkg) => {
        const selected = item.selected_package_id == pkg.id ? "selected" : "";
        packageOptions += `<option value="${pkg.id}" data-multiplier="${pkg.multiplier}" style="text-align:center;" dir="rtl" data-name="${pkg.package_name}" data-is-package="true" ${selected}>${pkg.package_name} ${Math.floor(pkg.multiplier)}</option>`;
      });
    }

    html += `
            <tr data-cart-index="${index}">
                <td style="min-width: 180px;">
                    <strong>${escapeHtml(item.product_name)}</strong>
                    ${
                      item.tier_info
                        ? `<br><small class="${
                            item.tier_info.includes("cross-type fallback")
                              ? "text-danger fw-bold"
                              : item.tier_info.includes("fallback")
                                ? "text-warning fw-bold"
                                : "text-muted"
                          }">${item.tier_info}</small>`
                        : ""
                    }
                </td>
                <td width="100">
                    <input type="number" class="form-control form-control-sm cart-qty" 
                           data-index="${index}"
                           value="${qtyValue}" step="any" 
                           onchange="updateCartItemQuantity(${index}, this.value)"
                           style="width: 100%;">
                    <small class="text-muted d-block text-center" dir="rtl" style="font-size:11px;">${formattedUnit}</small>
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
                    ${
                      isAdmin
                        ? `<input type="number" class="form-control form-control-sm cart-unit-price" 
                                data-index="${index}"
                                value="${unitPrice}" step="0.01" 
                                onchange="updateCartItemUnitPrice(${index}, this.value)"
                                style="width: 100px; background-color: #fff3cd;">`
                        : `<span class="cart-unit-price-display"><?php echo $settings['currency_symbol']; ?>${unitPrice}</span>`
                    }
                </td>
                <td width="120">
                    ${
                      isAdmin
                        ? `<input type="number" class="form-control form-control-sm cart-total" 
                                data-index="${index}"
                                value="${totalPrice}" step="0.01" 
                                onchange="updateCartItemTotal(${index}, this.value)"
                                style="width: 110px; background-color: #d1ecf1;">`
                        : `<span class="cart-total-display"><?php echo $settings['currency_symbol']; ?>${totalPrice}</span>`
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

  const isPackage = selectedOption.getAttribute("data-is-package") === "true";
  const multiplier = parseFloat(
    selectedOption.getAttribute("data-multiplier") || 1,
  );

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
    const packageName = selectedOption.getAttribute("data-name");

    item.selected_package_id = parseInt(packageId);
    item.display_unit = packageName;
    item.package_multiplier = multiplier;
    item.actual_quantity = currentDisplayQty * multiplier;
    item.display_quantity = currentDisplayQty;
  }

  const formData = new FormData();
  formData.append("product_id", item.product_id);
  formData.append("quantity", item.actual_quantity);
  formData.append("customer_type", customerType);

  try {
    const response = await fetch("api/get_price.php", {
      method: "POST",
      body: formData,
    });
    const text = await response.text();

    if (!text.startsWith("<")) {
      const priceData = JSON.parse(text);
      item.unit_price = priceData.unit_price || 0;
      item.total_price = priceData.total_price || 0;
      item.tier_info = priceData.tier_info || "";
    }
  } catch (error) {
    console.error("Error updating price:", error);
    item.total_price = (item.unit_price || 0) * item.actual_quantity;
  }

  renderCart();
  updateTotal();

  const unitName = isPackage
    ? selectedOption.getAttribute("data-name")
    : item.base_unit;
  if (typeof showNotification === "function") {
    showNotification("success", `Changed to ${unitName} - Price updated`);
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
  formData.append("product_id", item.product_id);
  formData.append("quantity", actualNewQty);
  formData.append("customer_type", customerType);

  try {
    const response = await fetch("api/get_price.php", {
      method: "POST",
      body: formData,
    });
    const text = await response.text();

    if (!text.startsWith("<")) {
      const priceData = JSON.parse(text);
      item.unit_price = priceData.unit_price || 0;
      item.total_price = priceData.total_price || 0;
      item.tier_info = priceData.tier_info || "";
    } else {
      item.total_price = (item.unit_price || 0) * actualNewQty;
    }
  } catch (error) {
    console.error("Error updating price:", error);
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

  if (typeof showNotification === "function") {
    showNotification(
      "success",
      `Unit price changed to <?php echo $settings['currency_symbol']; ?>${newUnitPrice.toFixed(2)}`,
    );
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
    alert("Unit price must be greater than 0 to calculate quantity");
    renderCart();
    return;
  }

  let actualNewQty = newTotal / unitPrice;

  const baseUnit = item.base_unit || "Piece";
  const discreteUnits = [
    "piece",
    "Piece",
    "packet",
    "dozen",
    "box",
    "bottle",
    "can",
  ];

  if (discreteUnits.includes(baseUnit)) {
    actualNewQty = Math.round(actualNewQty);
  } else {
    actualNewQty = Math.round(actualNewQty * 1000) / 1000;
  }

  if (actualNewQty <= 0) {
    alert(
      "Total amount must be greater than unit price to get at least 1 unit",
    );
    renderCart();
    return;
  }

  if (actualNewQty > item.max_stock) {
    alert(
      `This would require ${actualNewQty} ${baseUnit} but only ${item.max_stock} available!`,
    );
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
  if (typeof showNotification === "function") {
    showNotification(
      "success",
      `Total set to <?php echo $settings['currency_symbol']; ?>${newTotal.toFixed(2)} → Quantity: ${qtyDisplay} ${item.display_unit}`,
    );
  }
}

function removeFromCart(index) {
  cart.splice(index, 1);
  renderCart();
  updateTotal();
}

// Helper function for escaping HTML
function escapeHtml(text) {
  if (!text) return "";
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}

// Show notification function
if (typeof showNotification !== "function") {
  window.showNotification = function (type, message) {
    const alertDiv = document.createElement("div");
    alertDiv.className = `alert alert-${type === "success" ? "success" : "info"} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText =
      "top: 20px; right: 20px; z-index: 9999; min-width: 250px;";
    alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
    document.body.appendChild(alertDiv);
    setTimeout(() => alertDiv.remove(), 3000);
  };
}

// ===============================================
// PAGE LEAVE PROTECTION - Prevent losing cart items
// ===============================================

let leaveProtectionEnabled = true;
let userConfirmedLeave = false;

/**
 * Check if cart has items and show appropriate warning
 * @returns {string|boolean} Warning message or true to allow leaving
 */
function checkCartBeforeLeave() {
  // Don't show if protection is disabled or user already confirmed
  if (!leaveProtectionEnabled || userConfirmedLeave) {
    return true;
  }

  // Check if cart has items (global cart variable from billing page)
  if (typeof cart !== "undefined" && cart && cart.length > 0) {
    const itemCount = cart.length;
    const totalAmount =
      typeof getGrandTotal === "function" ? getGrandTotal() : 0;

    return (
      `⚠️ You have ${itemCount} item(s) in your cart (Total: <?php echo $settings['currency_symbol']; ?>${totalAmount.toFixed(2)})\n\n` +
      `Leaving this page will LOSE all unsaved items!\n\n` +
      `• Click "Cancel" to stay on this page\n` +
      `• Click "Save to Held Bills" to save your cart\n` +
      `• Click "OK" to leave anyway (items will be lost)`
    );
  }

  return true;
}

/**
 * Show a modal dialog with options to save cart before leaving
 * @param {Event} e - BeforeUnload event
 */
function showLeaveWarningModal(e) {
  // Don't show if already confirmed or protection disabled
  if (userConfirmedLeave || !leaveProtectionEnabled) {
    return;
  }

  // Check if cart has items
  if (typeof cart === "undefined" || !cart || cart.length === 0) {
    return;
  }

  // Standard beforeunload message (browsers show their own dialog)
  // This is the only reliable way to prevent navigation
  const message = checkCartBeforeLeave();
  if (message !== true) {
    e.preventDefault();
    e.returnValue = message;
    return message;
  }
}

/**
 * Create and show a custom modal for cart save options
 * This is shown when user tries to click a link or back button
 */
function showCartSaveModal(targetUrl) {
  // Don't show if already confirmed
  if (userConfirmedLeave) {
    window.location.href = targetUrl;
    return;
  }

  // Check if cart has items
  if (typeof cart === "undefined" || !cart || cart.length === 0) {
    window.location.href = targetUrl;
    return;
  }

  // Get cart summary
  const itemCount = cart.length;
  let total = 0;
  if (typeof updateTotal === "function") {
    // Calculate properly
    cart.forEach((item) => {
      total += item.total_price || 0;
    });
  }

  // Check if modal already exists
  let modal = document.getElementById("leaveWarningModal");
  if (modal) {
    modal.remove();
  }

  // Create modal HTML
  modal = document.createElement("div");
  modal.id = "leaveWarningModal";
  modal.className = "modal fade";
  modal.setAttribute("tabindex", "-1");
  modal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle-fill"></i> Unsaved Cart Items!
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="bi bi-cart-x" style="font-size: 48px; color: #ffc107;"></i>
                    </div>
                    <p class="lead text-center">
                        You have <strong class="text-danger">${itemCount} item(s)</strong> in your cart!
                    </p>
                    <p class="text-center text-muted">
                        Total: <strong><?php echo $settings['currency_symbol']; ?>${total.toFixed(2)}</strong>
                    </p>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle"></i>
                        <strong>Warning:</strong> Leaving this page will <strong>LOSE ALL UNSAVED ITEMS</strong>.
                    </div>
                    <div class="row mt-3">
                        <div class="col-12 mb-2">
                            <button class="btn btn-success w-100" id="saveAndLeaveBtn">
                                <i class="bi bi-pause-circle"></i> Save to Held Bills & Leave
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-arrow-left"></i> Stay on Page
                    </button>
                    <button type="button" class="btn btn-danger" id="forceLeaveBtn">
                        <i class="bi bi-trash3"></i> Leave Anyway (Lose Items)
                    </button>
                </div>
            </div>
        </div>
    `;

  document.body.appendChild(modal);

  // Show modal
  const bsModal = new bootstrap.Modal(modal);
  bsModal.show();

  // Handle Save & Leave
  document
    .getElementById("saveAndLeaveBtn")
    .addEventListener("click", async function () {
      this.disabled = true;
      this.innerHTML =
        '<span class="spinner-border spinner-border-sm me-2"></span> Saving...';

      try {
        // Call holdInvoice function if available
        if (typeof holdInvoice === "function") {
          await holdInvoice();
          userConfirmedLeave = true;
          window.location.href = targetUrl;
        } else if (typeof saveCartToHeld === "function") {
          await saveCartToHeld();
          userConfirmedLeave = true;
          window.location.href = targetUrl;
        } else {
          // Fallback: try to save via API directly
          await saveCartDirectly();
          userConfirmedLeave = true;
          window.location.href = targetUrl;
        }
      } catch (error) {
        console.error("Failed to save cart:", error);
        showNotification("error", "Failed to save cart. Please try again.");
        this.disabled = false;
        this.innerHTML =
          '<i class="bi bi-pause-circle"></i> Save to Held Bills & Leave';
      }
    });

  // Handle Force Leave
  document
    .getElementById("forceLeaveBtn")
    .addEventListener("click", function () {
      userConfirmedLeave = true;
      bsModal.hide();
      window.location.href = targetUrl;
    });

  // Cleanup modal on hide
  modal.addEventListener("hidden.bs.modal", function () {
    modal.remove();
  });
}

/**
 * Direct API call to save cart without relying on global functions
 */
async function saveCartDirectly() {
  if (!cart || cart.length === 0) return;

  const subtotal = cart.reduce((sum, item) => sum + (item.total_price || 0), 0);
  const discountInput = document.getElementById("discount_input");
  const discountType = document.getElementById("discount_type");

  let discount = 0;
  if (discountInput && discountType) {
    const discountValue = parseFloat(discountInput.value) || 0;
    if (discountType.value === "percent") {
      discount = subtotal * (discountValue / 100);
    } else {
      discount = discountValue;
    }
  }

  const total = Math.max(0, subtotal - discount);

  const invoiceData = {
    action: "save",
    customer_name: document.getElementById("customer_name")?.value || "",
    customer_phone: document.getElementById("customer_phone")?.value || "",
    customer_type: document.getElementById("customer_type")?.value || "retail",
    subtotal: subtotal,
    discount: discount,
    total: total,
    cart: cart.map((item) => ({
      product_id: item.product_id,
      product_name: item.product_name,
      base_unit: item.base_unit,
      display_unit: item.display_unit,
      actual_quantity: item.actual_quantity || item.quantity || 0,
      display_quantity: item.display_quantity || item.quantity,
      unit_price: item.unit_price || 0,
      total_price: item.total_price || 0,
      tier_info: item.tier_info || "",
      package_multiplier: item.package_multiplier || 1,
      selected_package_id: item.selected_package_id,
    })),
  };

  const response = await fetch("api/hold_invoice.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(invoiceData),
  });

  const result = await response.json();

  if (result.success) {
    showNotification("success", `Cart saved! Reference: ${result.hold_ref}`);
    // Clear cart after successful save
    cart = [];
    if (typeof renderCart === "function") renderCart();
    if (typeof updateTotal === "function") updateTotal();
  } else {
    throw new Error(result.error || "Failed to save cart");
  }
}

/**
 * Intercept all link clicks to show warning if cart has items
 */
function interceptNavigationLinks() {
  document.addEventListener("click", function (e) {
    // Find if clicked element is a link or inside a link
    let link = e.target.closest("a");
    if (!link) return;

    // Don't intercept if:
    // - User already confirmed leave
    // - Protection disabled
    // - Link has special attributes
    if (userConfirmedLeave || !leaveProtectionEnabled) return;
    if (link.getAttribute("target") === "_blank") return;
    if (link.href.startsWith("javascript:")) return;
    if (link.href.startsWith("#")) return;

    // Check if cart has items
    if (typeof cart !== "undefined" && cart && cart.length > 0) {
      e.preventDefault();
      e.stopPropagation();
      showCartSaveModal(link.href);
    }
  });
}

/**
 * Intercept back/forward navigation
 */
function interceptHistoryNavigation() {
  // Push a state to detect back button
  history.pushState({ page: "billing" }, "", window.location.href);

  window.addEventListener("popstate", function (e) {
    if (userConfirmedLeave || !leaveProtectionEnabled) return;

    if (typeof cart !== "undefined" && cart && cart.length > 0) {
      // Push state again to prevent immediate navigation
      history.pushState({ page: "billing" }, "", window.location.href);
      showCartSaveModal(null);
    } else {
      // Allow back navigation
      history.back();
    }
  });
}

/**
 * Enable or disable leave protection
 * @param {boolean} enabled - Enable/disable protection
 */
function setLeaveProtection(enabled) {
  leaveProtectionEnabled = enabled;
  if (!enabled) {
    userConfirmedLeave = true;
  }
}

/**
 * Reset leave protection (call after successful sale or cart clear)
 */
function resetLeaveProtection() {
  userConfirmedLeave = false;
  leaveProtectionEnabled = true;
}

// ===============================================
// INITIALIZE PAGE LEAVE PROTECTION
// ===============================================

// Set up beforeunload event (browser's native dialog)
window.addEventListener("beforeunload", function (e) {
  if (userConfirmedLeave) return;
  if (typeof cart !== "undefined" && cart && cart.length > 0) {
    const message = `You have ${cart.length} item(s) in your cart. Leaving will lose them!`;
    e.preventDefault();
    e.returnValue = message;
    return message;
  }
});

// Intercept link clicks
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", function () {
    interceptNavigationLinks();
    interceptHistoryNavigation();
  });
} else {
  interceptNavigationLinks();
  interceptHistoryNavigation();
}

// Also intercept form submissions that might navigate away
document.addEventListener("submit", function (e) {
  if (userConfirmedLeave || !leaveProtectionEnabled) return;

  const form = e.target;
  const action = form.getAttribute("action");

  // If form submits to a different page and cart has items
  if (
    action &&
    action !== "" &&
    action !== "#" &&
    typeof cart !== "undefined" &&
    cart &&
    cart.length > 0
  ) {
    // Check if it's a form that will navigate away
    if (
      !form.getAttribute("target") ||
      form.getAttribute("target") !== "_blank"
    ) {
      e.preventDefault();
      showCartSaveModal(action);
    }
  }
});

// Export functions for use in other scripts
window.cartLeaveProtection = {
  setEnabled: setLeaveProtection,
  reset: resetLeaveProtection,
  userConfirmed: () => userConfirmedLeave,
  forceSave: saveCartDirectly,
};
