<?php
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'config/functions.php';

$today_sales = getTodaySales($pdo);
$low_stock = getLowStockProducts($pdo);

$stmt = $pdo->prepare("
    SELECT i.*, u.full_name 
    FROM invoices i
    JOIN users u ON i.created_by = u.id
    ORDER BY i.created_at DESC 
    LIMIT 999999999
");
$stmt->execute();
$recent_invoices = $stmt->fetchAll();
?>
<button class="btn btn-warning me-2" onclick="showEditableInvoices()">
    <i class="bi bi-pencil-square"></i> Edit Invoices
</button>
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Dashboard</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary">Today</button>
            <button type="button" class="btn btn-sm btn-outline-secondary">Week</button>
            <button type="button" class="btn btn-sm btn-outline-secondary">Month</button>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <h5 class="card-title">Today's Bills</h5>
                <h2><?php echo $today_sales['total_bills']; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <h5 class="card-title">Wholesale Sales</h5>
                <h2><?php echo $settings['currency_symbol']; ?>
                    <?php echo number_format($today_sales['wholesale_sales'], 2); ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <h5 class="card-title">Retail Sales</h5>
                <h2><?php echo $settings['currency_symbol']; ?>
                    <?php echo number_format($today_sales['retail_sales'], 2); ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <h5 class="card-title">Total Sales</h5>
                <h2><?php echo $settings['currency_symbol']; ?>
                    <?php echo number_format($today_sales['total_sales'], 2); ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Low Stock Alert -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <i class="bi bi-exclamation-triangle"></i> Low Stock Alert
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Current Stock</th>
                            <th>Min Alert</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($low_stock as $product): ?>
                            <tr>
                                <td><?php echo $product['name']; ?></td>
                                <td class="text-danger"><?php echo $product['current_stock']; ?>
                                    <?php echo $product['unit']; ?></td>
                                <td><?php echo $product['min_stock_alert']; ?>     <?php echo $product['unit']; ?></td>
                                <td>
                                    <a href="products.php?edit=<?php echo $product['id']; ?>"
                                        class="btn btn-sm btn-warning">Restock</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Invoices -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-receipt"></i> Recent Invoices
            </div>
            <div class="card-body recent_bill">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Customer</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Print</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_invoices as $invoice): ?>
                            <tr>
                                <td><?php echo $invoice['invoice_no']; ?></td>
                                <td><?php echo $invoice['customer_name'] ?: 'Walk-in'; ?></td>
                                <td><span
                                        class="badge bg-<?php echo $invoice['customer_type'] == 'wholesale' ? 'success' : 'info'; ?>"><?php echo $invoice['customer_type']; ?></span>
                                </td>
                                <td><?php echo $settings['currency_symbol']; ?>
                                    <?php echo number_format($invoice['total_amount'], 2); ?></td>
                                
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="api/print_receipt.php?id=<?php echo $invoice['id']; ?>" target="_blank"
                                            class="btn btn-outline-secondary">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                        <button class="btn btn-outline-warning"
                                            onclick="editInvoice(<?php echo $invoice['id']; ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php if ($invoice['edit_count'] > 0): ?>
                                            <span class="badge bg-info"
                                                title="Edited <?php echo $invoice['edit_count']; ?> times">
                                                <?php echo $invoice['edit_count']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>


<style>
    .recent_bill{
        height: 500px;
        overflow-y: auto;
    }
</style>

<script>
    // Edit invoice - redirect to billing page with invoice data
function editInvoice(invoiceId) {
    if (confirm('Edit this invoice? A new version will be created.')) {
        // Store invoice ID in session storage
        sessionStorage.setItem('editing_invoice_id', invoiceId);
        window.location.href = 'billing.php?edit=' + invoiceId;
    }
}

// Show all editable invoices modal
async function showEditableInvoices() {
    try {
        const response = await fetch('api/edit_invoice.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'list_editable'})
        });
        
        const result = await response.json();
        
        if (!result.success) {
            alert('Failed to load invoices');
            return;
        }
        
        const modalHtml = `
            <div class="modal fade" id="editableInvoicesModal" tabindex="-1">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Saved Invoices</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Customer</th>
                                        <th>Type</th>
                                        <th>Total</th>
                                        <th>Date</th>
                                        <th>Edits</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${result.invoices.map(inv => `
                                        <tr>
                                            <td><strong>${inv.invoice_no}</strong></td>
                                            <td>${inv.customer_name || 'Walk-in'}</td>
                                            <td><span class="badge bg-${inv.customer_type === 'wholesale' ? 'success' : 'info'}">${inv.customer_type}</span></td>
                                            <td>Rs. ${parseFloat(inv.total_amount).toFixed(2)}</td>
                                            <td>${new Date(inv.created_at).toLocaleDateString()}</td>
                                            <td>${inv.edit_count > 0 ? `<span class="badge bg-info">${inv.edit_count}</span>` : '-'}</td>
                                            <td>
                                                <button class="btn btn-sm btn-warning" onclick="editInvoice(${inv.id})">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </button>
                                                <a href="api/print_receipt.php?id=${inv.id}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                                    <i class="bi bi-printer"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        new bootstrap.Modal(document.getElementById('editableInvoicesModal')).show();
        
    } catch (error) {
        alert('Error loading invoices');
    }
}
</script>