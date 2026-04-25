<!-- Cart Items -->
<div class="card">
    <div class="card-header bg-primary text-white">
        <i class="bi bi-cart"></i> Current Bill Items
        <span class="badge bg-light text-dark" id="cart_item_count">(renderCart)</span>
    </div>
    <div class="card-body p-0">
        <table class="table table-striped mb-0">
            <thead>
                <tr>
                    <th>Product</th>
                    <th width="100">Quantity</th>
                    <th width="80">Unit</th>
                    <th width="120">Unit Price</th>
                    <th width="120">Total</th>
                    <th width="50"></th>
                </tr>
            </thead>
            <tbody id="cart_items">
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">
                        No items in cart. Search and add products above.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>