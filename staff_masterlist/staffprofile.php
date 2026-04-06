<?php
// staff_masterlist/staff_profile.php
session_start();
require_once __DIR__ . '/../config/db.php';
$activePage = 'staff';

$staffId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$staffId) {
    header("Location: stafflist.php");
    exit();
}

// Fetch staff basic info
$staffSql = "SELECT id, full_name, email, profile_photo FROM staff WHERE id = ?";
$stmt = $conn->prepare($staffSql);
$stmt->bind_param("i", $staffId);
$stmt->execute();
$staff = $stmt->get_result()->fetch_assoc();

if (!$staff) {
    header("Location: stafflist.php");
    exit();
}

// Fetch yearly KPI scores for trend
$yearlySql = "SELECT 
        YEAR(Date) as year,
        ROUND(AVG(Score),2) as avg_score,
        ROUND((AVG(Score)/5)*100,2) as percentage
    FROM kpi_data 
    WHERE Name = ? AND Date IS NOT NULL
    GROUP BY YEAR(Date)
    ORDER BY year DESC";
$stmt = $conn->prepare($yearlySql);
$stmt->bind_param("s", $staff['full_name']);
$stmt->execute();
$yearlyScores = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch category scores
$categorySql = "SELECT 
        km.kpi_group as category_name,
        ROUND(AVG(kd.Score),2) as score,
        ROUND((AVG(kd.Score)/5)*100,2) as percentage,
        5 as target
    FROM kpi_data kd
    JOIN kpi_master_list km ON kd.KPI_Code = km.kpi_code
    WHERE kd.Name = ?
    GROUP BY km.kpi_group
    ORDER BY score DESC";
$stmt = $conn->prepare($categorySql);
$stmt->bind_param("s", $staff['full_name']);
$stmt->execute();
$categoryScores = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Determine strengths and weaknesses
$strengths = [];
$weaknesses = [];
foreach ($categoryScores as $cat) {
    if ($cat['percentage'] >= 80) {
        $strengths[] = $cat['category_name'];
    } elseif ($cat['percentage'] < 60) {
        $weaknesses[] = $cat['category_name'];
    }
}

// Fetch recommendations
$recSql = "SELECT `Training/Development Recommendations` as recommendation 
           FROM kpi_comment 
           WHERE Name = ? 
           ORDER BY Year DESC LIMIT 1";
$stmt = $conn->prepare($recSql);
$stmt->bind_param("s", $staff['full_name']);
$stmt->execute();
$recommendation = $stmt->get_result()->fetch_assoc();

// Get current and previous year scores
$currentScore = $yearlyScores[0]['avg_score'] ?? 0;
$previousScore = $yearlyScores[1]['avg_score'] ?? null;

// Calculate trend
if ($previousScore) {
    $trendValue = $currentScore - $previousScore;
    if ($trendValue > 0.3) {
        $trend = 'up';
        $trendText = 'Improving';
    } elseif ($trendValue < -0.3) {
        $trend = 'down';
        $trendText = 'Declining';
    } else {
        $trend = 'stable';
        $trendText = 'Stable';
    }
} else {
    $trend = 'stable';
    $trendText = 'Stable';
}

// Determine performance level
$scorePercentage = ($currentScore / 5) * 100;
if ($scorePercentage >= 90) {
    $performanceLevel = 'top';
    $levelText = 'Top Performer';
    $levelColor = 'green';
} elseif ($scorePercentage >= 75) {
    $performanceLevel = 'good';
    $levelText = 'Good';
    $levelColor = 'blue';
} elseif ($scorePercentage >= 60) {
    $performanceLevel = 'average';
    $levelText = 'Average';
    $levelColor = 'gray';
} else {
    $performanceLevel = 'at-risk';
    $levelText = 'At Risk';
    $levelColor = 'orange';
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($staff['full_name']); ?> - Staff Profile</title>
    <link rel="stylesheet" href="../asset/universal.css">
    <link rel="stylesheet" href="../asset/dashboard.css">
    <link rel="stylesheet" href="../asset/staffprofile.css">
</head>
<body>

<div class="dashboard">
        
    <?php include("../includes/sidebar.php"); ?>

    <div class="staff-profile-content">
        <!-- Back Button -->
        <div class="back-nav">
            <a href="stafflist.php" class="back-link">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Back to Staff List
            </a>
        </div>

        <!-- Profile Header Card -->
        <div class="profile-header-card">
            <div class="profile-cover"></div>
            <div class="profile-info-wrapper">
                <div class="profile-avatar-large">
                    <img src="<?php echo htmlspecialchars($staff['profile_photo'] ? '../' . $staff['profile_photo'] : '../asset/images/staff/default-profile.jpg'); ?>" 
                        alt="<?php echo htmlspecialchars($staff['full_name']); ?>"
                        onerror="this.src='../asset/images/staff/default-profile.jpg'">
                </div>
                <div class="profile-details">
                    <div class="profile-name-section">
                        <div>
                            <h1><?php echo htmlspecialchars($staff['full_name']); ?></h1>
                            <p class="profile-role">Staff • STF<?php echo str_pad($staffId, 3, '0', STR_PAD_LEFT); ?></p>
                        </div>
                        <button class="btn-add-kpi-large" onclick="openAddKPIModal(<?php echo $staffId; ?>, '<?php echo htmlspecialchars($staff['full_name']); ?>')">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 5v14M5 12h14"/>
                            </svg>
                            Update KPI Score
                        </button>
                    </div>

                    <div class="profile-contact-info">
                        <div class="contact-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <span><?php echo htmlspecialchars($staff['email']); ?></span>
                        </div>
                        <div class="contact-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.362 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0122 16.92z"/>
                            </svg>
                            <span>+60 12 345 6789</span>
                        </div>
                        <div class="contact-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span>Joined Jan 2024</span>
                        </div>
                        <div class="contact-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z"/>
                            </svg>
                            <span>Sales Department</span>
                        </div>
                    </div>

                    <div class="profile-badges">
                        <span class="badge performance-<?php echo $levelColor; ?>">
                            <?php echo $levelText; ?>
                        </span>
                        <span class="badge badge-active">Active</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI Overview Cards -->
        <div class="kpi-overview-grid">
            <div class="overview-card">
                <div class="overview-header">
                    <span class="overview-label">Current KPI Score</span>
                    <?php if ($trend == 'up'): ?>
                        <span class="trend-up-small">▲ Improving</span>
                    <?php elseif ($trend == 'down'): ?>
                        <span class="trend-down-small">▼ Declining</span>
                    <?php else: ?>
                        <span class="trend-stable-small">— Stable</span>
                    <?php endif; ?>
                </div>
                <div class="overview-value <?php echo $scorePercentage >= 80 ? 'value-high' : ($scorePercentage >= 60 ? 'value-medium' : 'value-low'); ?>">
                    <?php echo number_format($currentScore, 1); ?>
                </div>
                <?php if ($previousScore): ?>
                <div class="overview-sub">
                    Previous: <?php echo number_format($previousScore, 1); ?>
                    (<?php echo $trendValue > 0 ? '+' : ''; ?><?php echo number_format($trendValue, 1); ?>)
                </div>
                <?php endif; ?>
            </div>

            <div class="overview-card">
                <div class="overview-header">
                    <span class="overview-label">Strengths</span>
                </div>
                <?php if (!empty($strengths)): ?>
                    <ul class="strength-list">
                        <?php foreach (array_slice($strengths, 0, 3) as $strength): ?>
                            <li>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#22c55e">
                                    <path d="M20 6L9 17l-5-5"/>
                                </svg>
                                <?php echo htmlspecialchars($strength); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="no-data-text">No standout strengths yet</p>
                <?php endif; ?>
            </div>

            <div class="overview-card">
                <div class="overview-header">
                    <span class="overview-label">Areas for Improvement</span>
                </div>
                <?php if (!empty($weaknesses)): ?>
                    <ul class="weakness-list">
                        <?php foreach ($weaknesses as $weakness): ?>
                            <li>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f97316">
                                    <path d="M12 8v4m0 4h.01M12 2a10 10 0 110 20 10 10 0 010-20z"/>
                                </svg>
                                <?php echo htmlspecialchars($weakness); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="no-data-text">All areas meeting targets</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Performance Trend Chart -->
        <?php if (count($yearlyScores) > 1): ?>
        <div class="chart-card">
            <h2>Performance Trend</h2>
            <div class="trend-chart">
                <?php
                $years = array_reverse(array_column($yearlyScores, 'year'));
                $scores = array_reverse(array_column($yearlyScores, 'avg_score'));
                $maxScore = max($scores);
                ?>
                <div class="chart-bars">
                    <?php foreach ($yearlyScores as $index => $yearData): ?>
                    <div class="chart-bar-wrapper">
                        <div class="chart-bar-label"><?php echo $yearData['year']; ?></div>
                        <div class="chart-bar-container">
                            <div class="chart-bar" style="height: <?php echo ($yearData['avg_score'] / 5) * 100; ?>%"></div>
                        </div>
                        <div class="chart-bar-value"><?php echo number_format($yearData['avg_score'], 1); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Category Breakdown -->
        <div class="chart-card">
            <h2>KPI Category Breakdown</h2>
            <div class="category-list">
                <?php foreach ($categoryScores as $cat): ?>
                <div class="category-item">
                    <div class="category-header">
                        <span class="category-name"><?php echo htmlspecialchars($cat['category_name']); ?></span>
                        <span class="category-score <?php echo $cat['percentage'] >= 80 ? 'score-high-text' : ($cat['percentage'] >= 60 ? 'score-medium-text' : 'score-low-text'); ?>">
                            <?php echo number_format($cat['score'], 1); ?> / 5
                        </span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill <?php echo $cat['percentage'] >= 80 ? 'fill-high' : ($cat['percentage'] >= 60 ? 'fill-medium' : 'fill-low'); ?>" 
                             style="width: <?php echo $cat['percentage']; ?>%"></div>
                    </div>
                    <div class="category-percentage"><?php echo $cat['percentage']; ?>% of target</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Training Recommendations -->
        <?php if ($recommendation && !empty($recommendation['recommendation'])): ?>
        <div class="recommendations-card">
            <h2>Training Recommendations</h2>
            <div class="recommendation-content">
                <div class="recommendation-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                </div>
                <p><?php echo htmlspecialchars($recommendation['recommendation']); ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add KPI Modal (same as in stafflist.php) -->
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

<script src="staffprofile.js"></script>

</body>
</html>