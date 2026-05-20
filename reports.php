<?php
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'config/functions.php';

// Date range handling
$quick = $_GET['quick'] ?? 'today';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// If custom dates not set, apply quick filter
if (empty($start_date) || empty($end_date)) {
    switch ($quick) {
        case 'week':
            $start_date = date('Y-m-d', strtotime('monday this week'));
            $end_date = date('Y-m-d');
            break;
        case 'month':
            $start_date = date('Y-m-01');
            $end_date = date('Y-m-d');
            break;
        case 'all':
            $start_date = '2000-01-01';
            $end_date = date('Y-m-d');
            break;
        default: // today
            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d');
            break;
    }
}

// ── Summary (total bills + sales in period) ──────────────────────────────────
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*)  AS total_bills,
        COALESCE(SUM(CASE WHEN customer_type='wholesale' THEN total_amount ELSE 0 END),0) AS wholesale_sales,
        COALESCE(SUM(CASE WHEN customer_type='retail'    THEN total_amount ELSE 0 END),0) AS retail_sales,
        COALESCE(SUM(total_amount),0)  AS total_sales,
        COALESCE(AVG(total_amount),0)  AS avg_bill
    FROM invoices
    WHERE DATE(created_at) BETWEEN ? AND ?
    AND payment_status = 'paid'
");
$stmt->execute([$start_date, $end_date]);
$summary = $stmt->fetch();

// ── Daily breakdown ───────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT 
        DATE(created_at) AS sale_date,
        COUNT(*)  AS total_bills,
        COALESCE(SUM(CASE WHEN customer_type='wholesale' THEN total_amount ELSE 0 END),0) AS wholesale_sales,
        COALESCE(SUM(CASE WHEN customer_type='retail'    THEN total_amount ELSE 0 END),0) AS retail_sales,
        COALESCE(SUM(total_amount),0) AS total_sales
    FROM invoices
    WHERE DATE(created_at) BETWEEN ? AND ?
    AND payment_status = 'paid'
    GROUP BY DATE(created_at)
    ORDER BY sale_date DESC
");
$stmt->execute([$start_date, $end_date]);
$sales_data = $stmt->fetchAll();

// ── Top selling products ──────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT 
        p.name,
        p.unit,
        p.category,
        COALESCE(SUM(ii.quantity),0)    AS total_qty,
        COALESCE(SUM(ii.total_price),0) AS total_revenue,
        COUNT(DISTINCT i.id)            AS invoice_count,
        MAX(DATE(i.created_at))         AS last_sold
    FROM invoice_items ii
    JOIN invoices  i ON ii.invoice_id  = i.id
    JOIN products  p ON ii.product_id  = p.id
    WHERE DATE(i.created_at) BETWEEN ? AND ?
    AND i.payment_status = 'paid'
    GROUP BY p.id
    ORDER BY total_revenue DESC
    LIMIT 15
");
$stmt->execute([$start_date, $end_date]);
$top_products = $stmt->fetchAll();

// ── Least sold products (sold at least once but very little) ─────────────────
$stmt = $pdo->prepare("
    SELECT 
        p.name,
        p.unit,
        p.category,
        COALESCE(SUM(ii.quantity),0)    AS total_qty,
        COALESCE(SUM(ii.total_price),0) AS total_revenue,
        COUNT(DISTINCT i.id)            AS invoice_count,
        MAX(DATE(i.created_at))         AS last_sold
    FROM invoice_items ii
    JOIN invoices  i ON ii.invoice_id  = i.id
    JOIN products  p ON ii.product_id  = p.id
    WHERE DATE(i.created_at) BETWEEN ? AND ?
    AND i.payment_status = 'paid'
    AND p.status = 'active'
    GROUP BY p.id
    ORDER BY total_revenue ASC
    LIMIT 15
");
$stmt->execute([$start_date, $end_date]);
$least_products = $stmt->fetchAll();

// ── Never sold in period ──────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT p.name, p.unit, p.category, p.current_stock, p.status
    FROM products p
    WHERE p.status = 'active'
    AND p.is_hidden = 0
    AND p.id NOT IN (
        SELECT DISTINCT ii.product_id
        FROM invoice_items ii
        JOIN invoices i ON ii.invoice_id = i.id
        WHERE DATE(i.created_at) BETWEEN ? AND ?
        AND i.payment_status = 'paid'
    )
    ORDER BY p.name
");
$stmt->execute([$start_date, $end_date]);
$never_sold = $stmt->fetchAll();

// ── Not sold since X days (across ALL time, not just period) ─────────────────
$not_sold_15 = [];
$not_sold_30 = [];

$stmt = $pdo->query("
    SELECT 
        p.name, p.unit, p.category, p.current_stock,
        MAX(DATE(i.created_at)) AS last_sold_date,
        DATEDIFF(CURDATE(), MAX(DATE(i.created_at))) AS days_since
    FROM products p
    JOIN invoice_items ii ON ii.product_id = p.id
    JOIN invoices i ON ii.invoice_id = i.id
    WHERE i.payment_status = 'paid'
    AND p.status = 'active'
    AND p.is_hidden = 0
    GROUP BY p.id
    HAVING days_since >= 15
    ORDER BY days_since DESC
");
$stale_products = $stmt->fetchAll();

foreach ($stale_products as $p) {
    if ($p['days_since'] >= 30) {
        $not_sold_30[] = $p;
    } else {
        $not_sold_15[] = $p;
    }
}

// ── Wholesale vs Retail product breakdown ────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT 
        p.name,
        COALESCE(SUM(CASE WHEN i.customer_type='wholesale' THEN ii.quantity ELSE 0 END),0) AS wholesale_qty,
        COALESCE(SUM(CASE WHEN i.customer_type='retail'    THEN ii.quantity ELSE 0 END),0) AS retail_qty,
        COALESCE(SUM(ii.total_price),0) AS total_revenue
    FROM invoice_items ii
    JOIN invoices  i ON ii.invoice_id = i.id
    JOIN products  p ON ii.product_id = p.id
    WHERE DATE(i.created_at) BETWEEN ? AND ?
    AND i.payment_status = 'paid'
    GROUP BY p.id
    ORDER BY total_revenue DESC
    LIMIT 10
");
$stmt->execute([$start_date, $end_date]);
$ws_retail = $stmt->fetchAll();

// ── Customer analysis ────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(NULLIF(customer_name,''), 'Walk-in') AS customer,
        customer_type,
        COUNT(*) AS bill_count,
        COALESCE(SUM(total_amount),0) AS total_spent
    FROM invoices
    WHERE DATE(created_at) BETWEEN ? AND ?
    AND payment_status = 'paid'
    AND customer_name != '' AND customer_name IS NOT NULL
    GROUP BY customer_name, customer_type
    ORDER BY total_spent DESC
    LIMIT 10
");
$stmt->execute([$start_date, $end_date]);
$top_customers = $stmt->fetchAll();

$currency = $settings['currency_symbol'] ?? 'Rs.';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-graph-up"></i> Sales Reports</h1>
    <div>
        <button class="btn btn-outline-primary me-2" onclick="window.print()">
            <i class="bi bi-printer"></i> Print
        </button>
        <button class="btn btn-success" onclick="exportToCSV()">
            <i class="bi bi-download"></i> Export CSV
        </button>
    </div>
</div>

<!-- ── Quick Filter Buttons ── -->
<div class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <div class="btn-group">
                    <a href="?quick=today"
                        class="btn btn-sm <?= $quick == 'today' && empty($_GET['start_date']) ? 'btn-primary' : 'btn-outline-primary' ?>">Today</a>
                    <a href="?quick=week"
                        class="btn btn-sm <?= $quick == 'week' && empty($_GET['start_date']) ? 'btn-primary' : 'btn-outline-primary' ?>">This
                        Week</a>
                    <a href="?quick=month"
                        class="btn btn-sm <?= $quick == 'month' && empty($_GET['start_date']) ? 'btn-primary' : 'btn-outline-primary' ?>">This
                        Month</a>
                    <a href="?quick=all"
                        class="btn btn-sm <?= $quick == 'all' && empty($_GET['start_date']) ? 'btn-primary' : 'btn-outline-primary' ?>">All
                        Time</a>
                </div>
            </div>
            <div class="col-auto ms-3">
                <form method="GET" class="d-flex gap-2 align-items-end">
                    <div>
                        <label class="form-label mb-1 small">From</label>
                        <input type="date" name="start_date" class="form-control form-control-sm"
                            value="<?= $start_date ?>">
                    </div>
                    <div>
                        <label class="form-label mb-1 small">To</label>
                        <input type="date" name="end_date" class="form-control form-control-sm"
                            value="<?= $end_date ?>">
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary">Go</button>
                </form>
            </div>
            <div class="col-auto ms-auto">
                <small class="text-muted">
                    Showing: <strong><?= date('d M Y', strtotime($start_date)) ?></strong>
                    <?= $start_date != $end_date ? ' — <strong>' . date('d M Y', strtotime($end_date)) . '</strong>' : '' ?>
                </small>
            </div>
        </div>
    </div>
</div>

<!-- ── Summary Cards ── -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h6>Total Bills</h6>
                <h3><?= number_format($summary['total_bills']) ?></h3>
                <small>In selected period</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6>Total Sales</h6>
                <h3><?= $currency ?> <?= number_format($summary['total_sales']) ?></h3>
                <small>W: <?= $currency ?><?= number_format($summary['wholesale_sales']) ?> &nbsp;|&nbsp; R:
                    <?= $currency ?><?= number_format($summary['retail_sales']) ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h6>Avg Bill Value</h6>
                <h3><?= $currency ?> <?= number_format($summary['avg_bill'], 0) ?></h3>
                <small>Per invoice</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <h6>Not Sold (Period)</h6>
                <h3><?= count($never_sold) ?></h3>
                <small>Active products with 0 sales</small>
            </div>
        </div>
    </div>
</div>

<!-- ── Daily Breakdown ── -->
<?php if (!empty($sales_data)): ?>
    <div class="card mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="bi bi-calendar3"></i> Daily Breakdown</h5>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Bills</th>
                        <th>Wholesale</th>
                        <th>Retail</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sales_data as $day): ?>
                        <tr>
                            <td><strong><?= date('d-m-Y', strtotime($day['sale_date'])) ?></strong></td>
                            <td><?= $day['total_bills'] ?></td>
                            <td><?= $currency ?><?= number_format($day['wholesale_sales']) ?></td>
                            <td><?= $currency ?><?= number_format($day['retail_sales']) ?></td>
                            <td><strong><?= $currency ?><?= number_format($day['total_sales']) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- ── Top & Least Selling Products ── -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-trophy"></i> Top Selling Products</h5>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($top_products)): ?>
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Revenue</th>
                                <th>Last Sold</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $r = 1;
                            foreach ($top_products as $p): ?>
                                <tr>
                                    <td><?= $r++ ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($p['name']) ?></strong>
                                        <?php if ($p['category']): ?><br><small
                                                class="text-muted"><?= htmlspecialchars($p['category']) ?></small><?php endif; ?>
                                    </td>
                                    <td><?= number_format($p['total_qty'], 2) ?>         <?= $p['unit'] ?></td>
                                    <td><?= $currency ?><?= number_format($p['total_revenue']) ?></td>
                                    <td><small><?= $p['last_sold'] ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-center text-muted py-4">No sales in this period</p><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="bi bi-graph-down"></i> Least Selling Products</h5>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($least_products)): ?>
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Revenue</th>
                                <th>Last Sold</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $r = 1;
                            foreach ($least_products as $p): ?>
                                <tr>
                                    <td><?= $r++ ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($p['name']) ?></strong>
                                        <?php if ($p['category']): ?><br><small
                                                class="text-muted"><?= htmlspecialchars($p['category']) ?></small><?php endif; ?>
                                    </td>
                                    <td><?= number_format($p['total_qty'], 2) ?>         <?= $p['unit'] ?></td>
                                    <td><?= $currency ?><?= number_format($p['total_revenue']) ?></td>
                                    <td><small><?= $p['last_sold'] ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-center text-muted py-4">No sales in this period</p><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Wholesale vs Retail per Product ── -->
<?php if (!empty($ws_retail)): ?>
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-arrow-left-right"></i> Wholesale vs Retail — Top Products</h5>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Wholesale Qty</th>
                        <th>Retail Qty</th>
                        <th>Total Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ws_retail as $p): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                            <td><?= number_format($p['wholesale_qty'], 2) ?></td>
                            <td><?= number_format($p['retail_qty'], 2) ?></td>
                            <td><?= $currency ?><?= number_format($p['total_revenue']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- ── Top Customers ── -->
<?php if (!empty($top_customers)): ?>
    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="bi bi-people"></i> Top Customers</h5>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Customer</th>
                        <th>Type</th>
                        <th>Bills</th>
                        <th>Total Spent</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $r = 1;
                    foreach ($top_customers as $c): ?>
                        <tr>
                            <td><?= $r++ ?></td>
                            <td><?= htmlspecialchars($c['customer']) ?></td>
                            <td><span
                                    class="badge bg-<?= $c['customer_type'] == 'wholesale' ? 'success' : 'info' ?>"><?= $c['customer_type'] ?></span>
                            </td>
                            <td><?= $c['bill_count'] ?></td>
                            <td><strong><?= $currency ?><?= number_format($c['total_spent']) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- ── Not Sold in Period ── -->
<div class="card mb-4">
    <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-slash-circle"></i> Not Sold in Selected Period</h5>
        <span class="badge bg-light text-dark"><?= count($never_sold) ?> products</span>
    </div>
    <?php if (!empty($never_sold)): ?>
        <div class="card-body p-0" style="max-height:300px;overflow-y:auto;">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th>Stock</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($never_sold as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['name']) ?></td>
                            <td><small><?= htmlspecialchars($p['category'] ?: '-') ?></small></td>
                            <td><?= $p['unit'] ?></td>
                            <td><?= number_format($p['current_stock'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="card-body text-muted">All active products were sold in this period!</div><?php endif; ?>
</div>

<!-- ── Not Sold Since 15+ Days ── -->
<?php if (!empty($not_sold_15) || !empty($not_sold_30)): ?>
    <div class="row mb-4">
        <?php if (!empty($not_sold_30)): ?>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-danger text-white d-flex justify-content-between">
                        <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Not Sold Since 30+ Days</h5>
                        <span class="badge bg-light text-dark"><?= count($not_sold_30) ?></span>
                    </div>
                    <div class="card-body p-0" style="max-height:300px;overflow-y:auto;">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Last Sold</th>
                                    <th>Days Ago</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($not_sold_30 as $p): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($p['name']) ?></td>
                                        <td><small><?= $p['last_sold_date'] ?></small></td>
                                        <td><span class="badge bg-danger"><?= $p['days_since'] ?> days</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <?php if (!empty($not_sold_15)): ?>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-warning text-dark d-flex justify-content-between">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Not Sold Since 15–29 Days</h5>
                        <span class="badge bg-light text-dark"><?= count($not_sold_15) ?></span>
                    </div>
                    <div class="card-body p-0" style="max-height:300px;overflow-y:auto;">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Last Sold</th>
                                    <th>Days Ago</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($not_sold_15 as $p): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($p['name']) ?></td>
                                        <td><small><?= $p['last_sold_date'] ?></small></td>
                                        <td><span class="badge bg-warning text-dark"><?= $p['days_since'] ?> days</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
    function exportToCSV() {
        const table = document.querySelector('table');
        if (!table) return;
        const rows = [];
        table.querySelectorAll('thead th').forEach(th => rows.push ? null : null);
        const headers = [...table.querySelectorAll('thead th')].map(th => th.textContent.trim());
        const allRows = [headers.join(',')];
        table.querySelectorAll('tbody tr').forEach(tr => {
            const row = [...tr.querySelectorAll('td')].map(td => '"' + td.textContent.trim().replace(/"/g, '""') + '"');
            allRows.push(row.join(','));
        });
        const blob = new Blob([allRows.join('\n')], { type: 'text/csv' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'report_<?= date('Y-m-d') ?>.csv';
        a.click();
    }
</script>

<?php require_once 'includes/footer.php'; ?>