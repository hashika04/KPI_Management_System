<?php
include("../includes/auth.php");
include 'data.php';
$activePage = 'dashboard';
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
        <?php include("../includes/sidebar.php"); ?>

        <div class="main-grid">
            <div class="left-panel">
                <div class="hero-row">
                    <a href="profile.php" class="welcome-card">
                        <p class="small">Welcome back,</p>
                        <h1>Darlene<br>Robertson</h1>
                        <span class="badge">Supervisor</span>
                    </a>

                    <div class="stats-grid">
                        <div class="card kpi-overview-card">
                            <div class="expand-btn">↗</div>

                            <h2 class="kpi-overview-title">
                                Average KPI Overview
                            </h2>

                            <div class="kpi-chart-container">
                                <canvas id="kpiYearChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const kpiYears = <?php echo json_encode($kpiYears); ?>;
        const kpiYearPercentages = <?php echo json_encode($kpiYearPercentages); ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="script.js"></script>
</body>
</html>