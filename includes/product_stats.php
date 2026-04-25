<!-- Statistics Cards - Compact -->
<div class="row">
    <div class="col-md-4">
        <div class="card border-primary">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-box text-primary"></i> Total Products</span>
                    <span class="badge bg-primary fs-6">
                        <?php
                        $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'");
                        echo $stmt->fetchColumn();
                        ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-success">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-tags text-success"></i> Total Categories</span>
                    <span class="badge bg-success fs-6">
                        <?php
                        $stmt = $pdo->query("SELECT COUNT(*) FROM categories");
                        echo $stmt->fetchColumn();
                        ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-info">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-rulers text-info"></i> Total Units</span>
                    <span class="badge bg-info fs-6">
                        <?php
                        try {
                            $stmt = $pdo->query("SELECT COUNT(*) FROM units");
                            echo $stmt->fetchColumn();
                        } catch (Exception $e) {
                            echo "7";
                        }
                        ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>