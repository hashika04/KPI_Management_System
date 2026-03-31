<?php

include("../includes/auth.php");
$activePage = 'staff';

$sql = "SELECT 
        s.id,
        s.full_name,
        s.email,
        s.profile_photo,
        ROUND(AVG(k.Score),2) AS avg_score,
        ROUND((AVG(k.Score)/5)*100,2) AS avg_percentage
    FROM staff s
    LEFT JOIN kpi_data k
    ON s.full_name = k.Name
    GROUP BY s.id, s.full_name, s.email, s.profile_photo
    ORDER BY s.full_name";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Staff Masterlist</title>
    <link rel="stylesheet" href="../asset/universal.css">
    <link rel="stylesheet" href="../asset/dashboard.css">
    <link rel="stylesheet" href="../asset/staff_masterlist.css">
</head>
<body>

<div class="dashboard">
    <?php include("../includes/sidebar.php"); ?>

   <div class="staff-masterlist-content">

        <div class="staff-masterlist-header">
            <h1>Staff Masterlist</h1>

            <div class="staff-toolbar">
                <input type="text" id="staffSearch" placeholder="Search staff...">

                <select id="staffFilter">
                    <option value="all">All Status</option>
                    <option value="excellence">Excellence</option>
                    <option value="good">Good</option>
                    <option value="moderate">Moderate</option>
                    <option value="at risk">At Risk</option>
                </select>

                <select id="staffSort">
                    <option value="name-asc">Sort: Name A-Z</option>
                    <option value="name-desc">Sort: Name Z-A</option>
                    <option value="score-high">Sort: KPI High-Low</option>
                    <option value="score-low">Sort: KPI Low-High</option>
                </select>
            </div>
        </div>

        <div class="staff-list-scroll">
            <div class="staff-grid" id="staffGrid">
                <?php while($row = $result->fetch_assoc()): ?>
                <?php
                $score = (float)($row['avg_score'] ?? 0);
                $scorePercent = (float)($row['avg_percentage'] ?? 0);

                if ($score >= 4.5) {
                    $scoreClass = "score-green";
                    $statusText = "Excellence";
                } elseif ($score >= 3.5) {
                    $scoreClass = "score-yellow";
                    $statusText = "Good";
                } elseif ($score >= 2.5) {
                    $scoreClass = "score-orange";
                    $statusText = "Moderate";
                } else {
                    $scoreClass = "score-red";
                    $statusText = "At Risk";
                }
                ?>

                    <div class="staff-card"
                         data-name="<?php echo strtolower(htmlspecialchars($row['full_name'])); ?>"
                         data-status="<?php echo strtolower($statusText); ?>"
                         data-score="<?php echo $scorePercent; ?>">

                        <div class="staff-top">
                            <div class="staff-avatar">
                                <img src="<?php echo htmlspecialchars($row['profile_photo'] ?: '../asset/images/staff/default-profile.jpg'); ?>" alt="Staff Photo">
                            </div>

                            <div class="staff-info">
                                <h3><?php echo htmlspecialchars($row['full_name']); ?></h3>
                                <p><?php echo htmlspecialchars($row['email'] ?? 'No email'); ?></p>
                            </div>
                        </div>

                        <div class="staff-score-section">
                            <div class="score-label">
                                <span><?php echo $statusText; ?></span>
                                <span><?php echo number_format($score, 2); ?>/5 (<?php echo round($scorePercent); ?>%)</span>
                            </div>

                            <div class="score-bar">
                                <div class="score-fill <?php echo $scoreClass; ?>" 
                                    style="width: <?php echo $scorePercent; ?>%;">
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

    </div>
</div>

<script src="staff_masterlist.js"></script>

</body>
</html>