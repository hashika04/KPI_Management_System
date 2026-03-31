<div class="sidebar-menu">

    <div class="sidebar-top">

        <div class="logo">kpi</div>

        <a href="../Dashboard/dashboard.php"
           class="nav-pill <?php if($activePage=='dashboard') echo 'active'; ?>">
           Overview
        </a>

        <a href="../staff_masterlist/staff_masterlist.php"
           class="nav-pill <?php if($activePage=='staff') echo 'active'; ?>">
           Staff Masterlist
        </a>

        <a href="#" class="nav-pill">Analytics</a>
        <a href="#" class="nav-pill">Reports</a>

    </div>

    <div class="sidebar-bottom">

        <a href="../Dashboard/profile.php" class="profile-btn">
            <?php echo $initials; ?>
        </a>

        <a href="../Login/logout.php" class="logout-btn">
            <img src="../asset/images/logout.jpg" alt="Logout">
        </a>

    </div>

</div>