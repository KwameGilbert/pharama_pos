<?php
define('ADMIN_BASE', './../');
?>
<div class="sidebar">
    <div class="d-flex flex-column flex-shrink-0 pt-3 px-3 h-100">
        <div class="sidebar-brand text-center mb-4">
            <h4>Pharmacy POS</h4>
            <p class="small mb-0 text-light opacity-75">Admin Panel</p>
        </div>

        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item">
                <a href="<?php echo ADMIN_BASE ."dashboard/dashboard.php"?>" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="<?php echo ADMIN_BASE ."sales/index.php"?>" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/sales/') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Sales Management</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="<?php echo ADMIN_BASE ."products/products.php"?>" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : ''; ?>">
                    <i class="fas fa-pills"></i>
                    <span>Products</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="<?php echo ADMIN_BASE ."products/inventory.php"?>" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/products/inventory') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-boxes"></i>
                    <span>Inventory</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="<?php echo ADMIN_BASE ."suppliers/index.php"?>" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/suppliers/') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-truck"></i>
                    <span>Suppliers</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="<?php echo ADMIN_BASE ."customers/index.php"?>" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/customers/') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Customers</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="<?php echo ADMIN_BASE . "users/index.php"?>" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/users/') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-user-tag"></i>
                    <span>Users</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="<?php echo ADMIN_BASE ."reports/reports.php"?>" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="<?php echo ADMIN_BASE . "settings/settings.php"?>" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>

            <li class="nav-item mt-3">
                <a href="<?php echo ADMIN_BASE ."login/logout.php"?>" class="nav-link text-danger">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>
</div>