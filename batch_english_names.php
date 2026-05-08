<?php
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

if ($_SESSION['user_role'] != 'admin') {
    header('Location: dashboard.php');
    exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['ben_skipped'])) $_SESSION['ben_skipped'] = [];

$PER_PAGE = 10;
$saved = 0;
$errors = [];

// ── SAVE action ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $ids    = $_POST['product_id']    ?? [];
    $names  = $_POST['english_name']  ?? [];
    $skips  = $_POST['skip_ids']      ?? [];   // hidden field, comma-separated

    // Add newly skipped to session
    foreach ((array)$skips as $sid) {
        $sid = intval($sid);
        if ($sid > 0 && !in_array($sid, $_SESSION['ben_skipped'])) {
            $_SESSION['ben_skipped'][] = $sid;
        }
    }

    foreach ($ids as $i => $id) {
        $id   = intval($id);
        $name = trim($names[$i] ?? '');
        if ($name === '') continue;          // empty → skip silently
        try {
            $stmt = $pdo->prepare("UPDATE products SET english_name = ? WHERE id = ?");
            $stmt->execute([$name, $id]);
            $saved++;
        } catch (Exception $e) {
            $errors[] = "ID $id: " . $e->getMessage();
        }
    }
}

// ── SINGLE SKIP (GET) ─────────────────────────────────────────────────────────
if (isset($_GET['skip'])) {
    $sid = intval($_GET['skip']);
    if ($sid > 0 && !in_array($sid, $_SESSION['ben_skipped'])) {
        $_SESSION['ben_skipped'][] = $sid;
    }
    header('Location: batch_english_names.php');
    exit;
}

// ── RESET SKIPS ───────────────────────────────────────────────────────────────
if (isset($_GET['reset_skips'])) {
    $_SESSION['ben_skipped'] = [];
    header('Location: batch_english_names.php');
    exit;
}

// ── LOAD products without english_name ───────────────────────────────────────
$skippedList = $_SESSION['ben_skipped'];

$excludeSql = '';
$excludeParams = [];
if (!empty($skippedList)) {
    $placeholders = implode(',', array_fill(0, count($skippedList), '?'));
    $excludeSql   = "AND id NOT IN ($placeholders)";
    $excludeParams = $skippedList;
}

$countSql = "SELECT COUNT(*) FROM products 
             WHERE status='active' 
               AND (english_name IS NULL OR english_name = '') 
               $excludeSql";
$stmt = $pdo->prepare($countSql);
$stmt->execute($excludeParams);
$total = (int)$stmt->fetchColumn();

$countSkipped = count($skippedList);

$stmt = $pdo->prepare(
    "SELECT id, name, category, unit FROM products 
     WHERE status='active' 
       AND (english_name IS NULL OR english_name = '') 
       $excludeSql
     ORDER BY name 
     LIMIT $PER_PAGE"
);
$stmt->execute($excludeParams);
$products = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h4"><i class="bi bi-translate"></i> Batch: Add English / Phonetic Names</h1>
    <div>
        <?php if ($countSkipped > 0): ?>
            <a href="batch_english_names.php?reset_skips=1" class="btn btn-sm btn-outline-warning me-2">
                <i class="bi bi-arrow-counterclockwise"></i> Reset Skips (<?= $countSkipped ?> skipped)
            </a>
        <?php endif; ?>
        <a href="products.php" class="btn btn-sm btn-secondary">
            <i class="bi bi-arrow-left"></i> Back to Products
        </a>
    </div>
</div>

<?php if ($saved > 0): ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle"></i> Saved <strong><?= $saved ?></strong> English name(s). Showing next batch.
    </div>
<?php endif; ?>

<?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>

<?php if (empty($products)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <?php if ($countSkipped > 0 && $total === 0): ?>
                <i class="bi bi-check-circle text-warning" style="font-size:3rem;"></i>
                <p class="mt-3 fs-5">All remaining products are skipped.</p>
                <a href="batch_english_names.php?reset_skips=1" class="btn btn-warning">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset Skips &amp; Continue
                </a>
            <?php else: ?>
                <i class="bi bi-check2-all text-success" style="font-size:3rem;"></i>
                <p class="mt-3 fs-5 text-success">All products have English names!</p>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>

    <div class="alert alert-info d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-info-circle"></i>
            <strong><?= $total ?></strong> product(s) still need an English name
            <?= $countSkipped > 0 ? "(<strong>$countSkipped</strong> skipped this session)" : '' ?>.
            Showing up to <strong><?= min($PER_PAGE, $total) ?></strong>.
        </span>
        <small class="text-muted">English names are for <em>internal search only</em> — not shown on invoices.</small>
    </div>

    <form method="POST" action="batch_english_names.php">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="skip_ids" id="skipIdsField" value="">

        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-pencil-square"></i> Enter English / Phonetic Names
            </div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th>Original Name</th>
                            <th style="width:120px">Category</th>
                            <th style="width:90px">Unit</th>
                            <th>English / Phonetic Name</th>
                            <th style="width:90px">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $i => $p): ?>
                            <tr id="row-<?= $p['id'] ?>">
                                <td class="text-muted"><?= $i + 1 ?></td>
                                <td>
                                    <strong dir="rtl" style="font-family:'Noto Nastaliq Urdu',serif;">
                                        <?= htmlspecialchars($p['name']) ?>
                                    </strong>
                                    <input type="hidden" name="product_id[]" value="<?= $p['id'] ?>">
                                </td>
                                <td><small class="text-muted"><?= htmlspecialchars($p['category'] ?? '—') ?></small></td>
                                <td><small class="badge bg-secondary"><?= htmlspecialchars($p['unit']) ?></small></td>
                                <td>
                                    <input type="text"
                                           name="english_name[]"
                                           id="en-<?= $p['id'] ?>"
                                           class="form-control form-control-sm"
                                           placeholder="e.g., chini, sugar"
                                           autocomplete="off">
                                </td>
                                <td>
                                    <button type="button"
                                            class="btn btn-sm btn-outline-secondary"
                                            onclick="skipRow(<?= $p['id'] ?>)">
                                        <i class="bi bi-skip-forward"></i> Skip
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center">
                <small class="text-muted">
                    Leave empty to skip without marking — those will reappear in the next batch.
                </small>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-circle"></i> Save &amp; Next Batch
                </button>
            </div>
        </div>
    </form>

<?php endif; ?>

<script>
    var pendingSkips = [];

    function skipRow(id) {
        var row = document.getElementById('row-' + id);
        if (row) {
            row.style.opacity = '0.3';
            row.style.pointerEvents = 'none';
        }
        if (!pendingSkips.includes(id)) pendingSkips.push(id);
        document.getElementById('skipIdsField').value = pendingSkips.join(',');
    }
</script>

<?php require_once 'includes/footer.php'; ?>
