<!-- Bill Summary -->
<div class="col-md-12 position-relative">
    <div class="card sticky-top" style="top: 20px;">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">Bill Summary</h5>
        </div>

        <div class="card-body">
            <table class="table table-sm">
                <tr style="display: none;">
                    <td>Subtotal:</td>
                    <td class="text-end"><span id="subtotal">0.00</span></td>
                </tr>
                <tr style="display: none;">
                    <td>Discount:</td>
                    <td class="text-end">
                        <input type="number" id="discount_input" class="form-control form-control-sm d-inline-block"
                            style="width: 80px;" value="0" min="0" onchange="updateTotal()">
                        <select id="discount_type" class="form-select form-select-sm d-inline-block"
                            style="width: 70px;" onchange="updateTotal()">
                            <option value="fixed">Rs.</option>
                            <option value="percent">%</option>
                        </select>
                    </td>
                </tr>
                <tr class="table-primary">
                    <th>GRAND TOTAL:</th>
                    <th class="text-end">
                        <h4 id="grand_total">0.00</h4>
                    </th>
                </tr>
            </table>

            <hr>

            <!-- Payment Received Section -->
            <div class="mb-3">
                <label class="form-label fw-bold">
                    <i class="bi bi-cash"></i> Amount Received
                </label>
                <div class="input-group input-group-lg">
                    <span class="input-group-text">Rs.</span>
                    <input type="number" id="amount_received" class="form-control text-end" style="font-size: 20px;"
                        min="0" step="1" onkeyup="calculateChange()" onchange="calculateChange()"
                        placeholder="رقم درج کریں۔۔۔">
                </div>
            </div>

            <!-- Balance/Change Display -->
            <div id="payment_status" class="alert alert-secondary mb-3" style="display: none;">
                <div class="d-flex justify-content-between align-items-center">
                    <span id="status_label">Balance:</span>
                    <span id="balance_amount" class="fw-bold fs-5">Rs. 0.00</span>
                </div>
            </div>

            <!-- Quick Amount Buttons -->
            <div class="mb-3">
                <label class="form-label small text-muted">Quick Amount</label>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setReceivedAmount('exact')">
                        Exact
                    </button>
                </div>
            </div>

            <hr>

            <div class="mb-3 position-absolute" style="opacity: 0">
                <label>Payment Method</label>
                <select id="payment_method" class="form-select">
                    <option value="cash">Cash</option>
                    <option value="card">Card</option>
                    <option value="upi">UPI</option>
                    <option value="credit">Credit</option>
                </select>
            </div>

            <!-- ACTION BUTTONS -->
            <button class="btn btn-success btn-lg w-100 mb-2" onclick="completeSale()" id="completeSaleBtn">
                <i class="bi bi-check-circle"></i> Complete Sale (F12)
            </button>

            <div class="btn-group w-100">
                <button class="btn btn-warning" onclick="holdInvoice()">
                    <i class="bi bi-pause-circle"></i> Save
                </button>
                <button class="btn btn-info" onclick="showHeldInvoices()">
                    <i class="bi bi-list-ul"></i> Held Bills
                </button>
                <button class="btn btn-secondary" onclick="clearCart()">
                    <i class="bi bi-trash"></i> Clear
                </button>
            </div>
        </div>
    </div>
</div>