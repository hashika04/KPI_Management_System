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
    <link rel="stylesheet" href="../asset/stafflist.css">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/bold/style.css">
</head>

<body>

<div class="dashboard">
<?php include("../includes/sidebar.php"); ?>

<div class="staff-masterlist-content">
    <div class="sticky-header">
        <div class="staff-masterlist-header">
            <h1>Staff Profiles</h1>
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
                $score = $row['avg_score'] ?? 0;
                $percent = $row['avg_percentage'] ?? 0;

                // TREND (simple demo logic)
                $prev = $score - rand(0, 1); // Your existing logic
                $diff = round($score - $prev, 1);

                if ($diff > 0) {
                    $trendClass = "trend-up";
                    $trendIcon  = '<i class="ph-bold ph-trend-up"></i>';
                    $trendText  = "Improving";
                } elseif ($diff < 0) {
                    $trendClass = "trend-down";
                    $trendIcon  = '<i class="ph-bold ph-trend-down"></i>';
                    $trendText  = "Declining";
                } else {
                    $trendClass = "trend-stable";
                    $trendIcon  = '<i class="ph-bold ph-minus"></i>'; // Straight line icon
                    $trendText  = "Stable";
                }
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
                            <button class="edit-btn" onclick="openAddKPIModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['full_name']) ?>')">
                                Edit KPI
                            </button>
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
                            Prev: <?= round($prev, 1) ?> 
                            <span><?= $trendIcon ?> <?= $trendText ?></span>
                        </div>

                    </div>

                </div>

                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<div id="addKPIModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add KPI Score</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form id="addKPIForm" action="add_kpi.php" method="POST">
            <input type="hidden" name="staff_id" id="modalStaffId">
            <input type="hidden" name="staff_name" id="modalStaffName">
            
            <div class="form-group">
                <label>Staff Member</label>
                <input type="text" id="modalStaffNameDisplay" readonly>
            </div>
            
            <div class="form-group">
                <label>KPI Category</label>
                <select name="kpi_code" required>
                    <option value="">Select KPI Category</option>
                    <?php
                    $kpiSql = "SELECT kpi_code, kpi_group, kpi_description FROM kpi_master_list ORDER BY kpi_group";
                    $kpiResult = $conn->query($kpiSql);
                    $currentGroup = '';
                    while ($kpi = $kpiResult->fetch_assoc()) {
                        if ($currentGroup != $kpi['kpi_group']) {
                            if ($currentGroup != '') echo '</optgroup>';
                            echo '<optgroup label="' . htmlspecialchars($kpi['kpi_group']) . '">';
                            $currentGroup = $kpi['kpi_group'];
                        }
                        echo '<option value="' . $kpi['kpi_code'] . '">' . htmlspecialchars($kpi['kpi_description']) . '</option>';
                    }
                    echo '</optgroup>';
                    ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Score (1-5)</label>
                <input type="number" name="score" min="1" max="5" step="0.5" required>
            </div>
            
            <div class="form-group">
                <label>Date</label>
                <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-submit">Add Score</button>
            </div>
        </form>
    </div>
</div>

<script src="stafflist.js"></script>
<script src="staffprofile.js"></script>
</body>
</html>