<!-- Customer Details -->
<div class="card mb-3">
    <div class="p-2">
        <div class="row">
            <div class="col-md-3">
                <label>Customer Type</label>
                <select id="customer_type" class="form-select" onchange="updateCustomerType()">
                    <option value="">-- Select Type --</option>
                    <option value="retail">🛒 Retail</option>
                    <option value="wholesale">📦 Wholesale</option>
                </select>
                <small id="pricing_note" class="text-success">Retail pricing applied</small>
            </div>
            <div class="col-md-4">
                <label>Select Customer</label>
                <select id="customer_select" class="form-select" onchange="onCustomerSelect(this.value)">
                    <option value="">-- Walk-in Customer --</option>
                </select>
                <small class="text-muted">
                    <a href="customers.php" target="_blank">Manage Customers</a>
                </small>
            </div>
            <div class="col-md-5">
                <div class="row">
                    <div class="col-md-7">
                        <label>Customer Name</label>
                        <input type="text" id="customer_name" class="form-control"
                            placeholder="Walk-in customer" readonly style="background-color: #f8f9fa;">
                    </div>
                    <div class="col-md-5">
                        <label>Phone</label>
                        <input type="text" id="customer_phone" class="form-control" placeholder="Optional"
                            readonly style="background-color: #f8f9fa;">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>