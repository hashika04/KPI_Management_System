

<?php
// includes/sidebar.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = $activePage ?? '';
$navItems = [
    ['href' => '../Dashboard/overview.php', 'page' => 'dashboard', 'icon' => 'ph ph-chart-line-up',  'label' => 'Overview'],
    ['href' => '../staff_masterlist/stafflist.php',     'page' => 'staff',     'icon' => 'ph ph-users-three',     'label' => 'Staff List'],
    ['href' => '../Dashboard/analytics_patched.php', 'page' => 'analytics', 'icon' => 'ph ph-chart-bar',       'label' => 'Analytics'],
    ['href' => '../reports/reporting.php',   'page' => 'reports',   'icon' => 'ph ph-file-text',       'label' => 'Reports'],
    ['href' => '../Configuration/kpi_template_management.php',    'page' => 'config',    'icon' => 'ph ph-sliders-horizontal','label' => 'Configuration'],
];
$fullName  = $_SESSION['full_name']  ?? 'Guest';
$userEmail = $_SESSION['email'] ?? 'supervisor@company.com';
$userRole  = $_SESSION['position']  ?? 'Public Viewer';

if ($fullName !== 'Guest') { 
    $nameParts = explode(' ', trim($fullName));
    if (count($nameParts) > 1) {
        $initials = strtoupper(substr($nameParts[0], 0, 1) . substr(end($nameParts), 0, 1));
    } else {
        $initials = strtoupper(substr($fullName, 0, 2));
    }
} else {
    $initials = 'G';
}
?>

<!-- Phosphor Icons — clean, modern, professional -->
 <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css" />
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css" />
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/bold/style.css" />

<!-- TOP BAR — full width flush left to right -->
<header class="topbar">
    <div class="topbar-brand">
        <div class="logo">
            <i class="ph ph-chart-bar"></i>
        </div>
        <div>
            <div class="brand-name">KPI Monitor</div>
            <div class="brand-sub">Performance Tracking</div>
        </div>
    </div>
    <div class="topbar-right">
        <a href="../Dashboard/profile.php" class="topbar-user-link">
            <div class="topbar-user">
                <div class="user-email"><?= htmlspecialchars($fullName) ?></div>
                <div class="user-role"><?= htmlspecialchars($userRole) ?></div>
            </div>
            <div class="avatar-main"><?= $initials ?></div>
        </a>

        <a href="../Login/logout.php" class="topbar-logout" title="Logout">
            <i class="ph ph-sign-out"></i>
        </a>
    </div>
</header>

<!-- SIDEBAR — floating rounded card, sits below topbar -->
<nav class="sidebar-menu">
    <div class="sidebar-top">

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