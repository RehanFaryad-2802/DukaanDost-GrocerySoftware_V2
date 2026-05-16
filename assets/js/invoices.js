

async function showHeldInvoices() {
  try {
    const response = await fetch("api/hold_invoice.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action: "list" }),
    });

    const result = await response.json();

    if (!result.success) {
      showNotification("error", "Failed to load held invoices");
      return;
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
                            ${result.held_invoices.length === 0
        ? '<p class="text-center text-muted py-4">No held invoices</p>'
        : `<table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Reference</th>
                                            <th>Customer</th>
                                            <th>Type</th>
                                            <th>Items</th>
                                            <th>Total</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${result.held_invoices
          .map((inv) => {
            const cartData = JSON.parse(
              inv.cart_data,
            );
            return `
                                                <tr>
                                                    <td><strong>${inv.hold_reference}</strong><br><small>${new Date(inv.created_at).toLocaleString()}</small></td>
                                                    <td>${inv.customer_name || "Walk-in"}</td>
                                                    <td><span class="badge bg-${inv.customer_type === "wholesale" ? "success" : "info"}">${inv.customer_type}</span></td>
                                                    <td>${cartData.length} items</td>
                                                    <td><strong><?php echo $settings['currency_symbol']; ?>${parseFloat(inv.total_amount).toFixed(2)}</strong></td>
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
          })
          .join("")}
                                    </tbody>
                                </table>`
      }
                        </div>
                    </div>
                </div>
            </div>
        `;

    const existingModal = document.getElementById("heldInvoicesModal");
    if (existingModal) existingModal.remove();

    document.body.insertAdjacentHTML("beforeend", modalHtml);
    new bootstrap.Modal(document.getElementById("heldInvoicesModal")).show();
  } catch (error) {
    showNotification("error", "Error loading held invoices");
  }
}

async function resumeHeldInvoice(holdId) {
  try {
    const response = await fetch("api/hold_invoice.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action: "get", hold_id: holdId }),
    });

    const result = await response.json();

    if (result.success) {
      const inv = result.invoice;

      document.getElementById("customer_name").value = inv.customer_name || "";
      document.getElementById("customer_phone").value =
        inv.customer_phone || "";
      document.getElementById("customer_type").value = inv.customer_type;
      customerType = inv.customer_type;

      cart = inv.cart_data;
      renderCart();
      updateTotal();

      bootstrap.Modal.getInstance(
        document.getElementById("heldInvoicesModal"),
      ).hide();

      showNotification("success", "Invoice resumed!");
    }
  } catch (error) {
    showNotification("error", "Error resuming invoice");
  }
}

async function deleteHeldInvoice(holdId) {
  if (!confirm("Delete this held invoice permanently?")) return;

  try {
    await fetch("api/hold_invoice.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action: "delete", hold_id: holdId }),
    });

    bootstrap.Modal.getInstance(
      document.getElementById("heldInvoicesModal"),
    ).hide();
    showHeldInvoices();

    showNotification("success", "Held invoice deleted!");
  } catch (error) {
    showNotification("error", "Error deleting invoice");
  }
}

function editInvoice(invoiceId) {
  if (confirm("Edit this invoice? A new version will be created.")) {
    window.location.href = "billing.php?edit=" + invoiceId;
  }
}
