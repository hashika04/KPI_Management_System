<?php
include("../includes/auth.php");
include("../config/db.php");


$activePage = 'staff';


$sql = "
    SELECT
        s.id,
        s.full_name,
        s.staff_code,
        s.department,
        s.profile_photo,
        ROUND((AVG(k.Score)/5)*100, 1) AS avg_percentage,
        ROUND(AVG(CASE WHEN YEAR(k.Date) = 2025 THEN k.Score END), 2) AS score_2025,
        ROUND(AVG(CASE WHEN YEAR(k.Date) = 2024 THEN k.Score END), 2) AS score_2024
    FROM staff s
    LEFT JOIN kpi_data k ON s.full_name = k.Name
    GROUP BY s.id
    ORDER BY avg_percentage DESC
";  


$result = $conn->query($sql);
?>


<!DOCTYPE html>
<html>
<head>
    <title>Staff Profiles</title>
    <link rel="stylesheet" href="../asset/universal.css">
    <link rel="stylesheet" href="../asset/stafflist.css">
    <link rel="stylesheet" href="../asset/dashboard.css">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/bold/style.css">
</head>


<body>


<div class="dashboard">
<?php include("../includes/sidebar.php"); ?>


<div class="staff-masterlist-content">
    <div class="sticky-header">
        <div class="staff-masterlist-header">
            <h1>Staff Masterlist</h1>
            <p class="subtitle">View and manage individual performance</p>


            <div class="staff-toolbar">
                <input style="width: 230px;" type="text" id="staffSearch" placeholder="Search by name or staff ID...">


                <select id="departmentFilter">
                    <option value="all">All Departments</option>
                    <option>Electronics</option>
                    <option>Fashion</option>
                    <option>Home & Living</option>
                    <option>Sports</option>
                    <option>Beauty & Health</option>
                </select>


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
    </div>
    <div class="staff-list-scroll">
        <div class="staff-grid" id="staffGrid">
            <?php while($row = $result->fetch_assoc()):
                $percent = $row['avg_percentage'] ?? 0;
                $score = $percent / 20;


                // REAL TREND LOGIC: Compare 2025 vs 2024
                $currentYear = (float)($row['score_2025'] ?? 0);
                $previousYear = (float)($row['score_2024'] ?? 0);


                // 2. Calculate raw difference (DO NOT ROUND YET)
                $diff = ($previousYear > 0) ? ($currentYear - $previousYear) : 0;


                // 3. Determine status based on raw math
                if ($diff > 0) {
                    $trendClass = "trend-up";
                    $trendIcon  = '<i class="ph-bold ph-trend-up"></i>';
                    $trendText  = "Improved";
                } elseif ($diff < 0) {
                    $trendClass = "trend-down";
                    $trendIcon  = '<i class="ph-bold ph-trend-down"></i>';
                    $trendText  = "Declined";
                } else {
                    $trendClass = "trend-stable";
                    $trendIcon  = '<i class="ph-bold ph-minus"></i>';
                    $trendText  = "Stable";
                }
            ?>


                <div class="staff-card"
                    data-staffcode="<?= strtolower($row['staff_code']) ?>"
                    data-name="<?= strtolower($row['full_name']) ?>"
                    data-department="<?= $row['department'] ?>"
                    data-score="<?= $percent ?>">


                    <div class="staff-header-row">


                        <div class="staff-left">
                            <div class="staff-avatar">
                                <img src="<?= $row['profile_photo'] ?: '../asset/images/staff/default-profile.jpg' ?>">
                            </div>


                            <div class="staff-info">
                                <h3>
                                    <a href="staffprofile.php?id=<?= $row['id'] ?>">
                                        <?= $row['full_name'] ?>
                                    </a>
                                </h3>
                                <p><?= $row['staff_code'] ?></p>
                                <p><?= $row['department'] ?></p>
                            </div>
                        </div>


                        <div class="staff-kpi">
                            <h2><?= round($percent,1) ?>%</h2>
                            <button class="edit-btn" onclick="openAddKPIModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['full_name'], ENT_QUOTES) ?>')">
                                Edit KPI
                            </button>
                        </div>


                    </div>


                    <div class="staff-bottom">


                        <div>
                            <small>Overall KPI</small>
                            <div class="stars">
                                <?= str_repeat("★", round($score)) ?>
                                <span class="score-num"><?= number_format($score,1) ?></span>
                            </div>
                        </div>


                        <div class="trend" style="display: flex; flex-direction: column; align-items: flex-end; gap: 2px;">
                            <small style="color: #666; font-size: 0.75rem;">
                                2025 Avg: <strong><?= number_format($currentYear, 1) ?></strong>
                            </small>


                            <?php if ($previousYear > 0): ?>
                                <small style="color: #999; font-size: 0.75rem; margin-bottom: 4px;">
                                    2024 Avg: <?= number_format($previousYear, 1) ?>
                                </small>
                            <?php endif; ?>


                            <span class="<?= $trendClass ?>" style="font-size: 0.85rem; font-weight: 600;">
                                <?= $trendIcon ?> <?= $trendText ?>
                            </span>
                        </div>
                    </div>


                </div>


                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>


<div id="addKPIModal" class="modal">
    <div class="modal-content"
         style="max-width:900px; width:95%; max-height:92vh; padding:0; border-radius:20px;">
        <div id="modalContentTarget">
            Loading...
        </div>
    </div>
</div>
<script src="staff.js"></script>
</body>
</html>
