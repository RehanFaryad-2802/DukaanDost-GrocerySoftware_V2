<div class="container-fluid">
    <div class="row">
        <nav class="col-md-2 d-md-block sidebar"
            style="position: fixed; top: 0; bottom: 0; left: 0; z-index: 100; padding-top: 20px; overflow-y: auto;">
            <div class="position-sticky">
                <h5 class="text-white px-3 mb-3">Grocery Billing</h5>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active bg-primary' : ''; ?>"
                            href="dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'billing.php' ? 'active bg-primary' : ''; ?>"
                            href="billing.php">
                            <i class="bi bi-cart-plus"></i> New Sale
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active bg-primary' : ''; ?>"
                            href="products.php">
                            <i class="bi bi-box"></i> Products
                        </a>
                    </li>
                    <li
                        class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active bg-primary' : ''; ?>">
                        <a class="nav-link" href="categories.php">
                            <i class="bi bi-tags"></i> Categories
                        </a>
                    </li>
                    <li
                        class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'units.php' ? 'active bg-primary' : ''; ?>">
                        <a class="nav-link" href="units.php">
                            <i class="bi bi-rulers"></i> Units
                        </a>
                    </li>

                    <?php if ($_SESSION['user_role'] == 'admin' || $_SESSION['user_role'] == 'manager'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'customers.php' ? 'active bg-primary' : 'text-white'; ?>"
                                href="customers.php">
                                <i class="bi bi-people"></i> Customers
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active bg-primary' : ''; ?>"
                            href="reports.php">
                            <i class="bi bi-graph-up"></i> Reports
                        </a>
                    </li>
                    <?php if ($_SESSION['user_role'] == 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active bg-primary' : ''; ?>"
                                href="users.php">
                                <i class="bi bi-people"></i> Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active bg-primary' : ''; ?>"
                                href="settings.php">
                                <i class="bi bi-gear"></i> Settings
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
                <hr class="text-white-50">
                <div class="text-white px-3">
                    <small>
                        <i class="bi bi-person-circle"></i>
                        <?php echo $_SESSION['full_name']; ?><br>
                        <span class="badge bg-info"><?php echo ucfirst($_SESSION['user_role']); ?></span>
                    </small>
                </div>
                <div class="px-3 mt-3 mb-4">
                    <a href="logout.php" class="btn btn-sm btn-outline-light w-100">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </div>
            </div>
        </nav>
        <main class="col-md-10 ms-sm-auto px-md-4" style="margin-left: 16.666667%;">