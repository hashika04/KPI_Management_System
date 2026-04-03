

<?php
// includes/sidebar.php
$currentPage = $activePage ?? '';
$navItems = [
    ['href' => '../Dashboard/dashboard.php', 'page' => 'dashboard', 'icon' => 'ph ph-chart-line-up',  'label' => 'Overview'],
    ['href' => '../staff_masterlist/staff_masterlist.php',     'page' => 'staff',     'icon' => 'ph ph-users-three',     'label' => 'Staff List'],
    ['href' => 'analytics.php', 'page' => 'analytics', 'icon' => 'ph ph-chart-bar',       'label' => 'Analytics'],
    ['href' => 'reports.php',   'page' => 'reports',   'icon' => 'ph ph-file-text',       'label' => 'Reports'],
    ['href' => 'config.php',    'page' => 'config',    'icon' => 'ph ph-sliders-horizontal','label' => 'Configuration'],
];
$userName  = $_SESSION['name']  ?? 'DR';
$userEmail = $_SESSION['email'] ?? 'supervisor@company.com';
$userRole  = $_SESSION['role']  ?? 'Supervisor';
$initials  = strtoupper(substr($userName, 0, 2));
?>

<!-- Phosphor Icons — clean, modern, professional -->
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css" />
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/bold/style.css" />

<!-- TOP BAR — full width flush left to right -->
<header class="topbar">
    <div class="topbar-brand">
        <div class="logo">kpi</div>
        <div>
            <div class="brand-name">KPI Monitor</div>
            <div class="brand-sub">Performance Tracking</div>
        </div>
    </div>
    <div class="topbar-right">
        <div class="topbar-user">
            <div class="user-email"><?= htmlspecialchars($userEmail) ?></div>
            <div class="user-role"><?= htmlspecialchars($userRole) ?></div>
        </div>
        <div class="avatar-main"><?= $initials ?></div>
        <a href="../Login/logout.php" class="topbar-logout" title="Logout">
            <i class="ph ph-sign-out"></i>
        </a>
    </div>
</header>

<!-- SIDEBAR — floating rounded card, sits below topbar -->
<nav class="sidebar-menu">
    <div class="sidebar-top">
        <!-- Logo -->
        <div class="sidebar-logo">
            <div class="logo">kpi</div>
            <span>Monitor</span>
        </div>

        <!-- Nav items -->
        <?php foreach ($navItems as $item): ?>
            <a href="<?= $item['href'] ?>"
               class="nav-pill <?= ($currentPage === $item['page']) ? 'active' : '' ?>">
                <i class="<?= $item['icon'] ?>"></i>
                <?= $item['label'] ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Bottom intentionally empty — profile & logout are in topbar -->
    <div class="sidebar-bottom"></div>
</nav>