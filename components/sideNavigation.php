<?php
require_once __DIR__ . '/../app/Helper.php';

// Get current page name
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- ======= Sidebar ======= -->
<aside id="sidebar" class="sidebar">

    <ul class="sidebar-nav" id="sidebar-nav">

        <!-- Dashboard - All Users -->
        <li class="nav-item">
            <a class="nav-link <?= $current_page !== 'index.php' ? 'collapsed' : '' ?>" href="index.php">
                <i class="bi bi-house-door"></i>
                <span>Dashboard</span>
            </a>
        </li><!-- End Dashboard Nav -->

        <!-- Shelters - All Users -->
        <li class="nav-item">
            <a class="nav-link <?= $current_page !== 'shelters.php' ? 'collapsed' : '' ?>" href="shelters.php">
                <i class="bi bi-building"></i>
                <span>Shelters</span>
            </a>
        </li><!-- End Shelters Nav -->

        <!-- Disasters - All Users -->
        <li class="nav-item">
            <a class="nav-link <?= $current_page !== 'disasters.php' ? 'collapsed' : '' ?>" href="disasters.php">
                <i class="bi bi-exclamation-triangle"></i>
                <span>Disasters</span>
            </a>
        </li><!-- End Disasters Nav -->

        <!-- Emergency Hotlines - All Users -->
        <li class="nav-item">
            <a class="nav-link <?= $current_page !== 'hotlines.php' ? 'collapsed' : '' ?>" href="hotlines.php">
                <i class="bi bi-telephone"></i>
                <span>Emergency Hotlines</span>
            </a>
        </li><!-- End Emergency Hotlines Nav -->

        <?php if (isAdmin()): ?>
        <!-- Accounts - Admin Only -->
        <li class="nav-item">
            <a class="nav-link <?= $current_page !== 'accounts.php' ? 'collapsed' : '' ?>" href="accounts.php">
                <i class="bi bi-people"></i>
                <span>Accounts</span>
            </a>
        </li><!-- End Accounts Nav -->
        <?php endif; ?>
    </ul>
</aside><!-- End Sidebar-->
