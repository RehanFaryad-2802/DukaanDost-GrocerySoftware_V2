<!-- Product Search -->
<div class="card mb-3">
    <div class="p-2">
        <div class="input-group mb-3">
            <input dir="rtl" type="text" id="search_product" data-voice="true" class="form-control form-control-lg"
                placeholder="تلاش کریں۔۔۔" oninput="handleSearchInput(this.value)" autofocus>
            <button class="btn btn-outline-secondary" type="button" onclick="clearSearch()">
                <i class="bi bi-x"></i>
            </button>
            <button class="btn btn-outline-success" type="button" id="voiceBtn" onclick="toggleVoiceInput()"
                title="🎤 Voice Add Products (Batch)">
                <i class="bi bi-mic"></i>
            </button>
        </div>
        <div id="search_results" class="list-group" style="max-height: 200px; overflow-y: auto;"></div>

        <!-- Quick Add New Product Section -->
        <div id="quick_add_section" style="display: none;" class="mt-3">
            <div class="alert alert-info" id="quick_add_alert">
                <i class="bi bi-info-circle"></i> Product not found? Add a new one:
            </div>
            <div class="row g-2">
                <div class="col-md-12 mb-2">
                    <input type="text" id="quick_product_name" class="form-control" placeholder="Product Name *"
                        dir="rtl">
                </div>
                <div class="col-md-6 mb-2">
                    <select id="quick_unit" class="form-select">
                        <option value="">Select Unit *</option>
                    </select>
                </div>
                <div class="col-md-6 mb-2">
                    <select id="quick_category" class="form-select">
                        <option value="">Select Category</option>
                    </select>
                </div>
                <div class="col-md-4 mb-2">
                    <input type="number" id="quick_retail_price" class="form-control" placeholder="Retail Price *"
                        step="0.01">
                </div>
                <div class="col-md-4 mb-2">
                    <input type="number" id="quick_wholesale_price" class="form-control" placeholder="Wholesale Price"
                        step="0.01">
                </div>
                <div class="col-md-4 mb-2">
                    <input type="number" id="quick_quantity" class="form-control" placeholder="Quantity to Add"
                        value="1" step="any">
                </div>
                <div class="col-md-12 mb-2">
                    <button class="btn btn-primary w-100" onclick="addToProductListAndCart(event)">
                        <i class="bi bi-database-add"></i> Save to Products & Add to Cart
                    </button>
                    <small class="text-muted">Saves to product list first, then adds to cart</small>
                </div>
            </div>
        </div>
    </div>
</div>