<?php
include("../includes/auth.php");
$activePage = 'staff';

$sql = "SELECT 
        s.id,
        s.full_name,
        s.staff_code,
        s.department,
        s.profile_photo,
        ROUND(AVG(k.Score),2) AS avg_score,
        ROUND((AVG(k.Score)/5)*100,2) AS avg_percentage
    FROM staff s
    LEFT JOIN kpi_data k ON s.full_name = k.Name
    GROUP BY s.id
    ORDER BY avg_percentage DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Staff Profiles</title>
    <link rel="stylesheet" href="../asset/universal.css">
    /<link rel="stylesheet" href="../asset/dashboard.css">
    <link rel="stylesheet" href="../asset/stafflist.css">
</head>

<body>

<div class="dashboard">
<?php include("../includes/sidebar.php"); ?>

<div class="staff-masterlist-content">

    <div class="staff-masterlist-header">
        <h1>Staff Profiles</h1>
        <p class="subtitle">View and manage individual performance</p>

        <div class="staff-toolbar">
            <input type="text" id="staffSearch" placeholder="Search by name or staff ID...">

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
 <div class="staff-list-scroll">
    <div class="staff-grid" id="staffGrid">
    <?php while($row = $result->fetch_assoc()): 
        $score = $row['avg_score'] ?? 0;
        $percent = $row['avg_percentage'] ?? 0;

         // TREND (simple demo logic)
         $prev = $score - rand(0,1);
        $diff = round($score - $prev,1);
         $trend = $diff > 0 ? "Improving" : "Stable";
    ?>

        <div class="staff-card"
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
                    <button class="edit-btn">Edit KPI</button>
                </div>

            </div>

            <div class="staff-bottom">

                <div>
                    <small>Overall KPI</small>
                    <div class="stars">
                        <?= str_repeat("★", round($score)) ?>
                        <?= number_format($score,1) ?>
                    </div>
                </div>

                <div class="trend">
                    Prev: <?= round($prev,1) ?>
                    <span><?= $trend ?> <?= abs($diff) ?></span>
                </div>

            </div>

        </div>

        <?php endwhile; ?>

            </div>
        </div>
        </div>

<script src="stafflist.js"></script>
</body>
</html>