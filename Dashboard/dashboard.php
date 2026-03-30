<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../Login/index.php");
    exit();
}
include 'data.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="../asset/universal.css">
    <link rel="stylesheet" href="../asset/dashboard.css">
</head>
<body>
    <div class="dashboard">
        <div class="sidebar-menu">
            <div class="sidebar-top">
                <div class="logo">kpi</div>
                <button class="nav-pill active">Overview</button>
                <a href="../Staff_Masterlist/staff_masterlist.php">
                    <button class="nav-pill">Staff Masterlist</button>
                </a>
                <button class="nav-pill">Analytics</button>
                <button class="nav-pill">Reports</button>
            </div>

            <div class="sidebar-bottom">
                <a href="../Login/logout.php" class="logout-btn">
                    <img src="../asset/images/logout.jpg" alt="Logout">
                </a>
            </div>
        </div>

        <div class="main-grid">
            <div class="left-panel">
                <div class="hero-row">
                    <a href="profile.php" class="welcome-card">
                        <p class="small">Welcome back,</p>
                        <h1>Darlene<br>Robertson</h1>
                        <span class="badge">Supervisor</span>
                    </a>

                    <div class="stats-grid">
                        <?php foreach ($cards as $card): ?>
                            <div class="card <?php echo $card['highlight'] ? 'highlight' : ''; ?>">
                                <div class="expand-btn">↗</div>
                                <p class="mini-title"><?php echo $card['title']; ?></p>
                                <div class="value"><?php echo $card['value']; ?></div>
                                <p class="change"><?php echo $card['change']; ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>