<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'config/functions.php';

$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$report_type = $_GET['report_type'] ?? 'daily';

if (empty($start_date)) $start_date = date('Y-m-d');
if (empty($end_date)) $end_date = date('Y-m-d');

if ($report_type == 'daily') {
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as sale_date,
            COUNT(*) as total_bills,
            SUM(CASE WHEN customer_type = 'wholesale' THEN total_amount ELSE 0 END) as wholesale_sales,
            SUM(CASE WHEN customer_type = 'retail' THEN total_amount ELSE 0 END) as retail_sales,
            SUM(total_amount) as total_sales
        FROM invoices 
        WHERE DATE(created_at) BETWEEN ? AND ?
        AND payment_status = 'paid'
        GROUP BY DATE(created_at)
        ORDER BY sale_date DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $sales_data = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_bills,
            SUM(CASE WHEN customer_type = 'wholesale' THEN total_amount ELSE 0 END) as wholesale_sales,
            SUM(CASE WHEN customer_type = 'retail' THEN total_amount ELSE 0 END) as retail_sales,
            SUM(total_amount) as total_sales,
            AVG(total_amount) as avg_bill_value
        FROM invoices 
        WHERE DATE(created_at) BETWEEN ? AND ?
        AND payment_status = 'paid'
    ");
    $stmt->execute([$start_date, $end_date]);
    $summary = $stmt->fetch();
}

// Top Selling Products
$stmt = $pdo->prepare("
    SELECT 
        p.name,
        p.code,
        SUM(ii.quantity) as total_qty,
        SUM(ii.total_price) as total_revenue,
        COUNT(DISTINCT i.id) as invoice_count
    FROM invoice_items ii
    JOIN invoices i ON ii.invoice_id = i.id
    JOIN products p ON ii.product_id = p.id
    WHERE DATE(i.created_at) BETWEEN ? AND ?
    AND i.payment_status = 'paid'
    GROUP BY p.id
    ORDER BY total_revenue DESC
    LIMIT 10
");
$stmt->execute([$start_date, $end_date]);
$top_products = $stmt->fetchAll();

// Profit Calculation (Simple)
$stmt = $pdo->prepare("
    SELECT 
        SUM(ii.total_price) as total_revenue,
        SUM(ii.quantity * p.purchase_price) as total_cost
    FROM invoice_items ii
    JOIN invoices i ON ii.invoice_id = i.id
    JOIN products p ON ii.product_id = p.id
    WHERE DATE(i.created_at) BETWEEN ? AND ?
    AND i.payment_status = 'paid'
");
$stmt->execute([$start_date, $end_date]);
$profit_data = $stmt->fetch();
$profit = ($profit_data['total_revenue'] ?? 0) - ($profit_data['total_cost'] ?? 0);

// Today's summary
$today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as bill_count,
        SUM(total_amount) as today_sales
    FROM invoices 
    WHERE DATE(created_at) = ?
    AND payment_status = 'paid'
");
$stmt->execute([$today]);
$today_summary = $stmt->fetch();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-graph-up"></i> Sales Reports
    </h1>
    <div>
        <button class="btn btn-outline-primary me-2" onclick="window.print()">
            <i class="bi bi-printer"></i> Print Report
        </button>
        <button class="btn btn-success" onclick="exportToCSV()">
            <i class="bi bi-download"></i> Export CSV
        </button>
    </div>
</div>

<!-- Date Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label>Report Type</label>
                <select name="report_type" class="form-select">
                    <option value="daily" <?php echo $report_type == 'daily' ? 'selected' : ''; ?>>Daily Breakdown</option>
                    <option value="summary" <?php echo $report_type == 'summary' ? 'selected' : ''; ?>>Summary</option>
                </select>
            </div>
            <div class="col-md-3">
                <label>Start Date</label>
                <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
            </div>
            <div class="col-md-3">
                <label>End Date</label>
                <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
            </div>
            <div class="col-md-3">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-filter"></i> Apply Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h6 class="card-title">Total Bills</h6>
                <h3><?php echo number_format($summary['total_bills'] ?? count($sales_data)); ?></h3>
                <small>In selected period</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6 class="card-title">Total Sales</h6>
                <h3>Rs. <?php echo number_format($summary['total_sales'] ?? 0); ?></h3>
                <small>Wholesale: Rs. <?php echo number_format($summary['wholesale_sales'] ?? 0); ?></small><br>
                <small>Retail: Rs. <?php echo number_format($summary['retail_sales'] ?? 0); ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h6 class="card-title">Estimated Profit</h6>
                <h3>Rs. <?php echo number_format($profit, 2); ?></h3>
                <small>Revenue - Cost</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <h6 class="card-title">Avg Bill Value</h6>
                <h3>Rs. <?php echo number_format($summary['avg_bill_value'] ?? 0, 2); ?></h3>
                <small>Per invoice</small>
            </div>
        </div>
    </div>
</div>

<!-- Daily Breakdown Table -->
<?php if ($report_type == 'daily' && !empty($sales_data)): ?>
<div class="card mb-4">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0">Daily Sales Breakdown</h5>
    </div>
    <div class="card-body p-0">
        <table class="table table-striped table-hover mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Bills</th>
                    <th>Wholesale Sales</th>
                    <th>Retail Sales</th>
                    <th>Total Sales</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sales_data as $day): ?>
                <tr>
                    <td><strong><?php echo date('d-m-Y', strtotime($day['sale_date'])); ?></strong></td>
                    <td><?php echo $day['total_bills']; ?></td>
                    <td>Rs. <?php echo number_format($day['wholesale_sales'] ?? 0); ?></td>
                    <td>Rs. <?php echo number_format($day['retail_sales'] ?? 0); ?></td>
                    <td><strong>Rs. <?php echo number_format($day['total_sales']); ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Top Selling Products -->
<div class="card">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0">Top Selling Products</h5>
    </div>
    <div class="card-body p-0">
        <?php if (!empty($top_products)): ?>
        <table class="table table-striped mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th>Code</th>
                    <th>Quantity Sold</th>
                    <th>Revenue</th>
                    <th>Invoices</th>
                </tr>
            </thead>
            <tbody>
                <?php $rank = 1; foreach ($top_products as $product): ?>
                <tr>
                    <td><?php echo $rank++; ?></td>
                    <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                    <td><?php echo $product['code']; ?></td>
                    <td><?php echo number_format($product['total_qty'], 2); ?></td>
                    <td>Rs. <?php echo number_format($product['total_revenue']); ?></td>
                    <td><?php echo $product['invoice_count']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="text-center text-muted py-4">No sales data for this period</p>
        <?php endif; ?>
    </div>
</div>

<script>
function exportToCSV() {
    // Get table data and download as CSV
    const rows = [];
    const table = document.querySelector('table');
    const headers = [];
    
    table.querySelectorAll('thead th').forEach(th => {
        headers.push(th.textContent.trim());
    });
    rows.push(headers.join(','));
    
    table.querySelectorAll('tbody tr').forEach(tr => {
        const row = [];
        tr.querySelectorAll('td').forEach(td => {
            row.push('"' + td.textContent.trim().replace(/"/g, '""') + '"');
        });
        rows.push(row.join(','));
    });
    
    const csv = rows.join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'sales_report_<?php echo date('Y-m-d'); ?>.csv';
    a.click();
}
</script>

<?php require_once 'includes/footer.php'; ?>