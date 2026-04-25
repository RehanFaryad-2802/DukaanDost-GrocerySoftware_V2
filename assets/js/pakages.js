// test console.log('Packages JS loaded');
console.log("Packages JS loaded");
async function addPackageRow() {
  packageCount++;
  const container = document.getElementById("packagesContainer");
  if (!container) return;

  let unitsHtml = "";
  try {
    const response = await fetch("api/get_units.php");
    const units = await response.json();
    unitsHtml = units
      .map((u) => `<option value="${u.name}">${u.name}</option>`)
      .join("");
  } catch (e) {
    unitsHtml = `<option value="Dozen">Dozen</option><option value="Tray">Tray</option><option value="Box">Box</option>`;
  }

  const row = document.createElement("div");
  row.className = "input-group mb-2";
  row.id = `package_row_${packageCount}`;
  row.innerHTML = `
        <select name="package_name[]" class="form-select" required>
            <option value="">Select Unit</option>
            ${unitsHtml}
        </select>
        <span class="input-group-text">=</span>
        <input type="number" name="package_multiplier[]" class="form-control" placeholder="Qty (e.g., 12)" step="1" min="1" required>
        <button type="button" class="btn btn-outline-danger" onclick="removePackageRow(${packageCount})">
            <i class="bi bi-x"></i>
        </button>
    `;

  container.appendChild(row);
}

function removePackageRow(id) {
  const row = document.getElementById(`package_row_${id}`);
  if (row) row.remove();
}

function savePackage(productId) {
  const name = document.getElementById("new_package_name").value;
  const multiplier = document.getElementById("new_package_multiplier").value;

  if (!name || !multiplier) {
    alert("Please select a unit and enter multiplier");
    return;
  }

  const formData = new FormData();
  formData.append("product_id", productId);
  formData.append("package_name", name);
  formData.append("multiplier", multiplier);

  fetch("api/save_product_package.php", { method: "POST", body: formData })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        bootstrap.Modal.getInstance(
          document.getElementById("managePackagesModal"),
        ).hide();
        managePackages(
          productId,
          document
            .querySelector(".modal-title")
            .textContent.replace("Manage Packages - ", ""),
        );
      } else {
        alert(data.error);
      }
    });
}

function deletePackage(id) {
  if (!confirm("Delete this package?")) return;

  fetch(`api/delete_product_package.php?id=${id}`).then(() => {
    const modal = bootstrap.Modal.getInstance(
      document.getElementById("managePackagesModal"),
    );
    const productName = document
      .querySelector(".modal-title")
      .textContent.replace("Manage Packages - ", "");
    modal.hide();
    managePackages(currentUnitProductId, productName);
  });
}

async function managePackages(productId, productName) {
  currentUnitProductId = productId;

  let unitsHtml = "";
  try {
    const response = await fetch("api/get_units.php");
    const units = await response.json();
    unitsHtml = units
      .map((u) => `<option value="${u.name}">${u.name}</option>`)
      .join("");
  } catch (e) {
    unitsHtml = `<option value="Dozen">Dozen</option><option value="Tray">Tray</option><option value="Box">Box</option>`;
  }

  fetch(`api/get_product_packages.php?product_id=${productId}`)
    .then((response) => response.json())
    .then((packages) => {
      let html = `
                <div class="modal fade" id="managePackagesModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-secondary text-white">
                                <h5 class="modal-title">
                                    <i class="bi bi-boxes"></i> Manage Packages - ${productName}
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Package</th>
                                            <th>Multiplier</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="packagesList">
            `;

      if (packages.length === 0) {
        html += `<tr><td colspan="3" class="text-center text-muted">No packages added</td></tr>`;
      } else {
        packages.forEach((pkg) => {
          html += `
                        <tr>
                            <td>${pkg.package_name}</td>
                            <td>${pkg.multiplier}</td>
                            <td>
                                <button class="btn btn-sm btn-danger" onclick="deletePackage(${pkg.id})">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
        });
      }

      html += `
                                </tbody>
                            </table>
                            <hr>
                            <h6>Add New Package</h6>
                            <div class="input-group mb-2">
                                <select id="new_package_name" class="form-select">
                                    <option value="">Select Unit</option>
                                    ${unitsHtml}
                                </select>
                                <span class="input-group-text">=</span>
                                <input type="number" id="new_package_multiplier" class="form-control" placeholder="Qty (e.g., 12)">
                                <button class="btn btn-primary" onclick="savePackage(${productId})">Add</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            `;

      const existingModal = document.getElementById("managePackagesModal");
      if (existingModal) existingModal.remove();

      document.body.insertAdjacentHTML("beforeend", html);
      new bootstrap.Modal(
        document.getElementById("managePackagesModal"),
      ).show();
    });
}

async function changeItemPackage(index, packageName, multiplier) {
  const item = cart[index];
  if (!item) return;

  const newMultiplier = parseFloat(multiplier) || 1;
  const displayQty = item.display_quantity || item.quantity || 1;
  const newActualQty = displayQty * newMultiplier;
  const maxStock = item.max_stock || 999999;
  const baseUnit = item.base_unit || "Piece";

  if (newActualQty > maxStock) {
    alert(`Only ${maxStock} ${baseUnit} available!`);
    renderCart();
    return;
  }

  const formData = new FormData();
  formData.append("product_id", item.product_id);
  formData.append("quantity", newActualQty);
  formData.append("customer_type", customerType);

  try {
    const response = await fetch("api/get_price.php", {
      method: "POST",
      body: formData,
    });
    const priceData = await response.json();

    item.display_unit = packageName;
    item.actual_quantity = newActualQty;
    item.unit_price = priceData.unit_price || 0;
    item.total_price = priceData.total_price || 0;
    item.tier_info = priceData.tier_info || "";

    renderCart();
    updateTotal();
  } catch (error) {
    console.error("Change package error:", error);
  }
}
