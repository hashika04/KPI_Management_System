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
    <title>Dashboard – KPI Monitor</title>
    <link rel="stylesheet" href="../asset/universal.css">
    <link rel="stylesheet" href="../asset/dashboard.css">
</head>
<body>

<?php include("../includes/sidebar.php"); ?>

<div class="dashboard">

    <!-- ── Page header ── -->
    <div class="page-header">
        <h2 class="page-title">KPI Overview</h2>
        <p class="page-sub">Track and monitor key performance indicators</p>
    </div>

    <!-- ══════════════════════════════
         GRADIENT METRIC CARDS ROW
    ══════════════════════════════ -->
    <div class="metrics-row">

        <div class="metric-card grad-teal">
            <div class="metric-icon-wrap">
                <i class="ph ph-currency-dollar"></i>
            </div>
            <div class="metric-label">Total Revenue</div>
            <div class="metric-value">$124,500</div>
            <div class="metric-change">
                <i class="ph ph-trend-up"></i>
                +12.5% than last month
            </div>
        </div>

        <div class="metric-card grad-blue">
            <div class="metric-icon-wrap">
                <i class="ph ph-users-three"></i>
            </div>
            <div class="metric-label">Active Staff</div>
            <div class="metric-value">156</div>
            <div class="metric-change">
                <i class="ph ph-trend-up"></i>
                +8.2% than last month
            </div>
        </div>

        <div class="metric-card grad-pink">
            <div class="metric-icon-wrap">
                <i class="ph ph-target"></i>
            </div>
            <div class="metric-label">Goals Met</div>
            <div class="metric-value">89%</div>
            <div class="metric-change">
                <i class="ph ph-trend-up"></i>
                +5.1% than last month
            </div>
        </div>

        <div class="metric-card grad-purple">
            <div class="metric-icon-wrap">
                <i class="ph ph-medal"></i>
            </div>
            <div class="metric-label">Performance Score</div>
            <div class="metric-value">92.4</div>
            <div class="metric-change">
                <i class="ph ph-trend-down"></i>
                -2.3% than last month
            </div>
        </div>

    </div>

    <!-- ══════════════════════════════
         MAIN CONTENT GRID
    ══════════════════════════════ -->
    <div class="main-grid">

        <!-- LEFT PANEL -->
        <div class="left-panel">

            <!-- Average KPI chart card -->
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title-wrap">
                        <div class="chart-title">Average KPI Overview</div>
                        <div class="chart-subtitle">Overall performance score per year</div>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <button class="qr-btn" title="Download QR">
                            <i class="ph ph-qr-code"></i>
                        </button>
                        <button class="expand-btn" title="Expand">
                            <i class="ph ph-arrows-out"></i>
                        </button>
                    </div>
                </div>

                <div class="kpi-chart-container">
                    <canvas id="kpiYearChart"></canvas>
                </div>

                <!-- Insight box -->
                <div class="chart-insight">
                    <span class="insight-icon"><i class="ph ph-lightbulb"></i></span>
                    <p>
                        <strong>Insight:</strong> Average KPI has shown a declining trend from 2022 to 2024,
                        with early signs of recovery in 2025. Consider reviewing departmental targets
                        to sustain the upward momentum.
                    </p>
                </div>
            </div>

        </div>

        <!-- RIGHT PANEL -->
        <div class="right-panel">

            <!-- Welcome card -->
            <a href="profile.php" class="welcome-card">
                <p class="small">Welcome back,</p>
                <h1>Darlene<br>Robertson</h1>
                <span class="badge">Supervisor</span>
            </a>

        </div>
    </div><!-- /.main-grid -->

</div><!-- /.dashboard -->

<script>
    const kpiYears           = <?php echo json_encode($kpiYears); ?>;
    const kpiYearPercentages = <?php echo json_encode($kpiYearPercentages); ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="script.js"></script>

</body>
</html>