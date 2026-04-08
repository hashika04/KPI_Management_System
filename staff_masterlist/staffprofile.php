<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
$activePage = 'staff';

$staffId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$staffId) {
    header("Location: stafflist.php");
    exit();
}


function classifyPerformanceLevel(float $percentage): string
{
    if ($percentage >= 85) return 'Top';
    if ($percentage >= 70) return 'Good';
    if ($percentage >= 50) return 'Average';
    return 'At Risk';
}

function classifyRiskLevel(float $percentage): string
{
    if ($percentage >= 85) return 'Low';
    if ($percentage >= 70) return 'Moderate';
    return 'High';
}

function classifyTrendLabel(float $delta): string
{
    if ($delta > TREND_STABLE_DELTA) return 'Improving';
    if ($delta < -TREND_STABLE_DELTA) return 'Declining';
    return 'Stable';
}

/*
|--------------------------------------------------------------------------
| SAVE EDITED PROFILE
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $postStaffId = (int)($_POST['staff_id'] ?? 0);
    $email = trim((string)($_POST['email'] ?? ''));
    $phoneNumber = trim((string)($_POST['phone_number'] ?? ''));
    $department = trim((string)($_POST['department'] ?? ''));
    $position = trim((string)($_POST['position'] ?? ''));
    $joinDate = trim((string)($_POST['join_date'] ?? ''));

    if ($postStaffId <= 0) {
        $error = 'Invalid staff record.';
    } else {
        try {
            $stmt = $conn->prepare("
                UPDATE staff
                SET email = ?, phone_number = ?, department = ?, position = ?, join_date = ?
                WHERE id = ?
            ");
            $stmt->bind_param('sssssi', $email, $phoneNumber, $department, $position, $joinDate, $postStaffId);
            $stmt->execute();
            $stmt->close();

            $success = 'Staff profile updated successfully.';
        } catch (Throwable $e) {
            $error = 'Unable to update staff profile. Please check the staff table structure.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| LOAD STAFF BASIC DETAILS
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT
        id,
        full_name,
        email,
        profile_photo,
        staff_code,
        department,
        position,
        phone_number,
        join_date
    FROM staff
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param('i', $staffId);
$stmt->execute();
$staff = $stmt->get_result()->fetch_assoc();

if (!$staff) {
    die('Staff not found.');
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
    LEFT JOIN kpi_master_list km
        ON TRIM(km.`kpi_code`) = TRIM(kd.`KPI_Code`)
        AND km.`kpi_code` <> 'KPI_Code'
    LEFT JOIN kpi_comment kc
        ON TRIM(LOWER(kc.`Name`)) = TRIM(LOWER(kd.`Name`))
        AND kc.`Year` = YEAR(STR_TO_DATE(kd.`Date`, '%Y-%m-%d'))
        AND kc.`Year` > 0
        AND kc.`Name` <> 'Name'
    WHERE TRIM(LOWER(kd.`Name`)) = TRIM(LOWER(?))
      AND kd.`Date` IS NOT NULL
      AND kd.`Date` <> ''
      AND kd.`KPI_Code` IS NOT NULL
      AND kd.`KPI_Code` <> ''
      AND kd.`Score` IS NOT NULL
    ORDER BY STR_TO_DATE(kd.`Date`, '%Y-%m-%d') ASC, kd.`KPI_Code` ASC
");
$staffName = trim((string)$staff['full_name']);
$stmt->bind_param('s', $staffName);
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
        if (!isset($groupedPeriods[$period]['section2_groups'][$groupName])) {
            $groupedPeriods[$period]['section2_groups'][$groupName] = [];
        }
        $groupedPeriods[$period]['section2_groups'][$groupName][] = [
            'code' => $code,
            'description' => $row['kpi_description'],
            'score' => $score,
        ];
    }
}

$periodSummaries = [];

foreach ($groupedPeriods as $period => $entry) {
    $section1Weighted = 0.0;
    $section1Average = 0.0;
    $section1Count = 0;

    foreach ($SECTION1_COMPETENCY_WEIGHTS as $code => $weight) {
        $raw = (float)($entry['section1_scores'][$code] ?? 0);
        $section1Weighted += $raw * $weight;
        if (isset($entry['section1_scores'][$code])) {
            $section1Average += $raw;
            $section1Count++;
        }
    }

    $section1Average = $section1Count > 0 ? $section1Average / $section1Count : 0.0;

    $section2Weighted = 0.0;
    $categoryScores = [];

    foreach ($entry['section2_groups'] as $groupName => $items) {
        $avg = count($items) ? array_sum(array_column($items, 'score')) / count($items) : 0.0;
        $weight = (float)($SECTION2_GROUP_WEIGHTS[$groupName] ?? 0.0);
        $weighted = $avg * $weight;
        $section2Weighted += $weighted;

        $categoryScores[] = [
            'category' => $groupName,
            'percentage' => round(($avg / 5) * 100, 2),
            'avg_5_scale' => round($avg, 4),
            'weighted_score_5' => round($weighted, 4),
        ];
    }

    usort($categoryScores, fn($a, $b) => strcmp($a['category'], $b['category']));

    $overallAverage5 = count($entry['all_scores']) > 0
        ? round(array_sum($entry['all_scores']) / count($entry['all_scores']), 4)
        : 0.0;

    $overallPercentage = round(($overallAverage5 / 5) * 100, 2);

    $periodSummaries[] = [
        'period' => $period,
        'score_5' => $overallAverage5,
        'percentage' => $overallPercentage,
        'category_scores' => $categoryScores,
        'comments' => $entry['comments'],
        'training' => $entry['training'],
    ];
}

usort($periodSummaries, fn($a, $b) => strcmp($a['period'], $b['period']));

$latest = end($periodSummaries) ?: [
    'period' => '',
    'score_5' => 0,
    'percentage' => 0,
    'category_scores' => [],
    'comments' => '',
    'training' => '',
];

$latestComment = $latest['comments'] ?: 'No supervisor comment available.';
$latestTraining = $latest['training'] ?: 'No training recommendation available.';

$recentScores5 = array_column(array_slice($periodSummaries, -3), 'score_5');
$trendDelta = 0.0;
if (count($recentScores5) >= 2) {
    $trendDelta = round(end($recentScores5) - $recentScores5[0], 4);
}

$trendLabel = classifyTrendLabel($trendDelta);
$performanceLevel = classifyPerformanceLevel((float)$latest['percentage']);
$riskLevel = classifyRiskLevel((float)$latest['percentage']);

$stabilityScore = 100;
if (count($periodSummaries) >= 2) {
    $deltas = [];
    $scores = array_column($periodSummaries, 'score_5');
    for ($i = 1; $i < count($scores); $i++) {
        $deltas[] = abs($scores[$i] - $scores[$i - 1]);
    }
    $avgDelta = count($deltas) ? array_sum($deltas) / count($deltas) : 0.0;
    $stabilityScore = max(0, min(100, (int)round(100 - ($avgDelta * 100))));
}

$topStrengths = $latest['category_scores'];
usort($topStrengths, fn($a, $b) => $b['percentage'] <=> $a['percentage']);
$strengths = array_slice($topStrengths, 0, 2);

$weakAreas = $latest['category_scores'];
usort($weakAreas, fn($a, $b) => $a['percentage'] <=> $b['percentage']);
$improvements = array_slice($weakAreas, 0, 2);

$performanceInsight = '';
if ($latest['percentage'] >= 85 && $stabilityScore >= 80) {
    $performanceInsight = 'This staff member is currently performing strongly with consistent KPI delivery across recent periods.';
} elseif ($latest['percentage'] >= 70) {
    $performanceInsight = 'This staff member is performing at an acceptable level, but selected KPI areas still need improvement to achieve stronger overall results.';
} else {
    $performanceInsight = 'This staff member is below the desired KPI level and should be monitored more closely with targeted support.';
}

$riskInsight = '';
if ($riskLevel === 'Low') {
    $riskInsight = 'Risk is currently low because the latest KPI remains strong and no major decline is visible in recent performance movement.';
} elseif ($riskLevel === 'Moderate') {
    $riskInsight = 'Risk is moderate because KPI performance is still acceptable, but stronger consistency and improvement are needed.';
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Profile</title>
    <script src="https://cdn.plot.ly/plotly-2.35.2.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
    <link rel="stylesheet" href="../asset/universal.css?v=2">
    <link rel="stylesheet" href="../asset/analytics.css?v=20">
    <style>
        .staff-profile-page {
            margin-left: 220px;
            padding: 104px 28px 40px;
            min-height: 100vh;
            background: #fcf2fa;
        }
        .staff-profile-header {
            margin-bottom: 20px;
        }
        .staff-profile-header h1 {
            margin: 8px 0 0;
            font-size: 2.1rem;
            font-weight: 800;
            color: #1d1635;
        }
        .staff-profile-header p {
            margin: 8px 0 0;
            color: #8f6d83;
            font-size: 1rem;
            font-weight: 500;
        }
        .profile-shell {
            display: grid;
            gap: 18px;
        }
        .profile-card,
        .profile-panel,
        .profile-wide-panel,
        .profile-main-card {
            background: rgba(255,255,255,0.96);
            border: 1px solid #efd8e5;
            border-radius: 22px;
            box-shadow: 0 10px 28px rgba(200, 80, 140, 0.08);
        }
        .profile-top-banner {
            display: grid;
            grid-template-columns: 1.1fr 1.5fr auto;
            gap: 18px;
            align-items: center;
            padding: 20px 22px;
            background: linear-gradient(135deg, #ffffff 0%, #fff7fb 100%);
            border: 1px solid #edd7e5;
            border-radius: 24px;
            box-shadow: 0 10px 24px rgba(192, 112, 181, 0.08);
        }
        .profile-top-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .profile-top-left img {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #efd6e5;
            background: #fff;
            box-shadow: 0 6px 16px rgba(0,0,0,0.08);
        }
        .profile-identity h2 {
            margin: 0 0 6px;
            font-size: 1.5rem;
            font-weight: 800;
            color: #231942;
        }
        .profile-identity .identity-line {
            margin: 2px 0;
            color: #6f6376;
            font-size: 0.94rem;
            font-weight: 600;
        }
        .profile-meta-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0,1fr));
            gap: 12px 18px;
        }
        .profile-meta-item {
            background: #fff;
            border: 1px solid #f0dce7;
            border-radius: 14px;
            padding: 10px 12px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .profile-meta-item i {
            font-size: 1rem;
            color: #b35d99;
            margin-top: 2px;
        }
        .profile-meta-text span {
            display: block;
            font-size: 0.76rem;
            color: #9b8796;
            margin-bottom: 2px;
        }
        .profile-meta-text strong {
            display: block;
            font-size: 0.91rem;
            color: #2a2038;
            word-break: break-word;
        }
        .profile-banner-actions {
            display: flex;
            align-items: flex-start;
            justify-content: flex-end;
        }
        .profile-edit-btn,
        .profile-save-btn,
        .profile-cancel-btn,
        .profile-action-btn {
            border: none;
            border-radius: 14px;
            padding: 10px 14px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
        }
        .profile-edit-btn,
        .profile-action-btn {
            background: #fff;
            border: 1.5px solid #ead6e2;
            color: #9f5aa8;
        }
        .profile-save-btn {
            background: linear-gradient(135deg, #c070e0 0%, #e8308c 100%);
            color: #fff;
        }
        .profile-cancel-btn {
            background: #f9edf4;
            color: #9f5aa8;
            border: 1.5px solid #edd7e5;
        }
        .profile-main-card,
        .profile-panel,
        .profile-wide-panel {
            padding: 18px 20px;
        }
        .profile-main-card h2,
        .profile-panel h2,
        .profile-wide-panel h2 {
            margin: 0 0 14px;
            font-size: 1.1rem;
            font-weight: 800;
            color: #221834;
        }
        .profile-form-grid {
            display: none;
            grid-template-columns: repeat(2, minmax(0,1fr));
            gap: 14px 16px;
            margin-top: 16px;
        }
        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-size: 0.84rem;
            font-weight: 700;
            color: #6f6376;
        }
        .form-group input {
            width: 100%;
            border: 1.5px solid #ead6e2;
            border-radius: 14px;
            background: #fff;
            padding: 12px 14px;
            font-size: 0.94rem;
            color: #2f2138;
        }
        .profile-action-row {
            display: none;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 16px;
        }
        .profile-message {
            border-radius: 14px;
            padding: 12px 14px;
            margin-top: 12px;
            font-size: 0.92rem;
        }
        .message-success {
            background: #ecfdf5;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .message-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .profile-summary-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 18px;
        }
        .summary-box {
            padding: 18px 20px;
        }
        .summary-score-card {
            background: linear-gradient(135deg, #fff1f4 0%, #fff8fa 100%);
            border: 1px solid #f3c9d6;
        }
        .summary-strength-card {
            background: linear-gradient(135deg, #f2fff7 0%, #fbfffd 100%);
            border: 1px solid #cfead9;
        }
        .summary-improve-card {
            background: linear-gradient(135deg, #fff9ee 0%, #fffdf8 100%);
            border: 1px solid #f3dfb7;
        }
        .summary-box h3 {
            margin: 0 0 10px;
            font-size: 1rem;
            font-weight: 800;
            color: #221834;
        }
        .kpi-big-score {
            font-size: 2.2rem;
            font-weight: 800;
            color: #db2777;
            margin-bottom: 8px;
        }
        .summary-subnote {
            color: #7d6979;
            font-size: 0.88rem;
            line-height: 1.5;
        }
        .badge-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
        .profile-pill {
            padding: 6px 11px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
        }
        .pill-top { background: #dcfce7; color: #166534; }
        .pill-good { background: #dbeafe; color: #1d4ed8; }
        .pill-average { background: #fef3c7; color: #92400e; }
        .pill-risk { background: #fee2e2; color: #b91c1c; }
        .pill-low { background: #dcfce7; color: #166534; }
        .pill-moderate { background: #fef3c7; color: #92400e; }
        .pill-high { background: #fee2e2; color: #b91c1c; }
        .summary-list,
        .detail-list {
            margin: 0;
            padding-left: 18px;
            color: #4b3a4c;
            line-height: 1.65;
        }
        .summary-list li + li,
        .detail-list li + li {
            margin-top: 6px;
        }
        .profile-chart-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }
        .detail-breakdown-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .detail-box {
            border: 1px solid #efdeea;
            border-radius: 18px;
            background: #fffafd;
            padding: 14px 16px;
        }
        .detail-box h3 {
            margin: 0 0 10px;
            font-size: 0.98rem;
            font-weight: 800;
            color: #221834;
        }
        .text-panel {
            padding: 16px 18px;
            border: 1px solid #efdeea;
            border-radius: 18px;
            background: linear-gradient(180deg, #fdf8ff 0%, #ffffff 100%);
        }
        .text-panel h3 {
            margin: 0 0 8px;
            font-size: 1rem;
            font-weight: 800;
            color: #221834;
        }
        .text-panel p {
            margin: 0;
            color: #4b3a4c;
            line-height: 1.65;
        }
        .empty-chart-note {
            margin: 0;
            color: #8f7d8a;
            font-size: 0.9rem;
            padding: 8px 0 0;
        }
        .quick-actions-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        @media (max-width: 1200px) {
            .profile-top-banner,
            .profile-summary-row,
            .profile-chart-row,
            .detail-breakdown-grid,
            .profile-form-grid,
            .profile-meta-grid {
                grid-template-columns: 1fr;
            }
            .profile-banner-actions {
                justify-content: flex-start;
            }
        }
        @media (max-width: 1100px) {
            .staff-profile-page {
                margin-left: 0;
                padding: 110px 18px 28px;
            }
        }
    </style>
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
            </div>

            <div class="profile-meta-grid">
                <div class="profile-meta-item">
                    <i class="ph ph-envelope-simple"></i>
                    <div class="profile-meta-text">
                        <span>Email</span>
                        <strong><?= htmlspecialchars($staff['email'] ?? '-') ?></strong>
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

                <div class="profile-meta-item">
                    <i class="ph ph-buildings"></i>
                    <div class="profile-meta-text">
                        <span>Department</span>
                        <strong><?= htmlspecialchars($staff['department'] ?? '-') ?></strong>
                    </div>
                </div>
            </div>
        </div>

                    <div class="form-group">
                        <label>Join Date</label>
                        <input type="date" name="join_date" value="<?= htmlspecialchars($staff['join_date'] ?? '') ?>">
                    </div>
                </div>

                <div class="profile-action-row" id="profileEditActions">
                    <button type="button" class="profile-cancel-btn" id="cancelEditBtn">Cancel</button>
                    <button type="submit" class="profile-save-btn">Save Changes</button>
                </div>
            </form>
        </section>

        <?php if (empty($records)): ?>
            <section class="profile-wide-panel">
                <h2>Data Status</h2>
                <div class="text-panel">
                    <p>No KPI records were matched for this staff profile. Please check whether the staff name in the <strong>staff</strong> table matches the name used in <strong>kpi_data</strong>.</p>
                </div>
                <?php endif; ?>
            </div>

            <div class="overview-card">
                <div class="overview-header">
                    <span class="overview-label">Strengths</span>
                </div>
            </article>

            <article class="summary-box profile-card summary-strength-card">
                <h3>Strengths</h3>
                <ul class="summary-list">
                    <?php if (!empty($strengths)): ?>
                        <?php foreach ($strengths as $item): ?>
                            <li>
                                <strong><?= htmlspecialchars($item['category']) ?></strong>
                                — <?= number_format((float)$item['percentage'], 2) ?>%
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
                                <strong><?= htmlspecialchars($item['category']) ?></strong>
                                — <?= number_format((float)$item['percentage'], 2) ?>%
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>No improvement data available.</li>
                    <?php endif; ?>
                </ul>
            </article>
        </section>

        <section class="profile-chart-row">
            <article class="profile-panel">
                <h2>Performance Trend</h2>
                <div id="staffTrendChart" style="height:320px;"></div>
                <?php if (!$hasTrendData): ?>
                    <p class="empty-chart-note">No KPI trend data available for this staff.</p>
                <?php endif; ?>
            </article>

            <article class="profile-panel">
                <h2>Performance Radar</h2>
                <div id="staffRadarChart" style="height:320px;"></div>
                <?php if (!$hasCategoryData): ?>
                    <p class="empty-chart-note">No KPI category data available for this staff.</p>
                <?php endif; ?>
            </article>
        </section>

        <section class="profile-chart-row">
            <article class="profile-panel">
                <h2>Category Breakdown</h2>
                <div id="staffCategoryChart" style="height:320px;"></div>
                <?php if (!$hasCategoryData): ?>
                    <p class="empty-chart-note">No category breakdown available for this staff.</p>
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
                </div>
            </article>
        </section>

        <section class="profile-wide-panel">
            <h2>Supervisor Comment</h2>
            <div class="text-panel">
                <p><?= htmlspecialchars($latestComment) ?></p>
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
