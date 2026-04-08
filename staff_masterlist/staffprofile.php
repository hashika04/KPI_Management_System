<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

$staffId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($staffId <= 0) {
    die('Invalid staff ID.');
}

$success = '';
$error = '';

define('KPI_TARGET_PERCENT', 80.0);
define('TREND_STABLE_DELTA', 0.2);

$SECTION1_COMPETENCY_WEIGHTS = [
    'S1.1' => 0.05,
    'S1.2' => 0.10,
    'S1.3' => 0.10
];

$SECTION2_GROUP_WEIGHTS = [
    'Daily Sales Operations' => 0.15,
    'Customer Service Quality' => 0.15,
    'Sales Target Contribution' => 0.15,
    'Training, Learning & Team Contribution' => 0.10,
    'Inventory & Cost Control' => 0.05,
    'Store Operations Support' => 0.15
];

function normalizeAvatarPath(string $path): string
{
    return trim($path) !== '' ? trim($path) : '../asset/images/staff/default-profile.jpg';
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
$staffResult = $stmt->get_result();
$staff = $staffResult->fetch_assoc();
$stmt->close();

if (!$staff) {
    die('Staff not found.');
}

/*
|--------------------------------------------------------------------------
| LOAD KPI RECORDS FOR THIS STAFF
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT
        kd.`Date` AS evaluation_date,
        kd.`KPI_Code` AS kpi_code,
        kd.`Score` AS score,
        km.`section`,
        km.`kpi_group`,
        km.`kpi_description`,
        kc.`Supervisor Comments` AS supervisor_comments,
        kc.`Training/Development Recommendations` AS training_recommendations
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
$kpiResult = $stmt->get_result();

$records = [];
while ($row = $kpiResult->fetch_assoc()) {
    $date = DateTime::createFromFormat('Y-m-d', trim((string)$row['evaluation_date']));
    if (!$date) {
        continue;
    }

    $records[] = [
        'period' => $date->format('Y-m'),
        'year' => (int)$date->format('Y'),
        'score' => (int)$row['score'],
        'kpi_code' => trim((string)$row['kpi_code']),
        'section' => trim((string)($row['section'] ?? '')),
        'kpi_group' => trim((string)($row['kpi_group'] ?? '')),
        'kpi_description' => trim((string)($row['kpi_description'] ?? '')),
        'supervisor_comments' => trim((string)($row['supervisor_comments'] ?? '')),
        'training_recommendations' => trim((string)($row['training_recommendations'] ?? '')),
    ];
}
$stmt->close();

/*
|--------------------------------------------------------------------------
| BUILD PERIOD SCORES
|--------------------------------------------------------------------------
*/
$groupedPeriods = [];

foreach ($records as $row) {
    $period = $row['period'];

    if (!isset($groupedPeriods[$period])) {
        $groupedPeriods[$period] = [
            'section1_scores' => [],
            'section2_groups' => [],
            'all_scores' => [],
            'comments' => $row['supervisor_comments'],
            'training' => $row['training_recommendations'],
        ];
    }

    $score = (int)$row['score'];
    $code = $row['kpi_code'];
    $groupName = $row['kpi_group'] !== '' ? $row['kpi_group'] : 'Other KPI';

    $groupedPeriods[$period]['all_scores'][] = $score;

    if ($row['section'] === 'Section 1' || str_starts_with($code, 'S1.')) {
        $groupedPeriods[$period]['section1_scores'][$code] = $score;
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
    $riskInsight = 'Risk is high because KPI performance is below target and requires immediate supervisory attention.';
}

$supervisorAction = '';
if ($riskLevel === 'Low') {
    $supervisorAction = 'Maintain performance momentum, recognise strong contribution, and consider advanced development opportunities.';
} elseif ($riskLevel === 'Moderate') {
    $supervisorAction = 'Provide focused coaching on weaker KPI categories and monitor the next evaluation periods closely.';
} else {
    $supervisorAction = 'Start an immediate coaching plan, review weak KPI areas, and assign a structured improvement target.';
}

$profilePhoto = htmlspecialchars(normalizeAvatarPath((string)($staff['profile_photo'] ?? '')));
$trendSeriesLabels = array_map(fn($row) => $row['period'], $periodSummaries);
$trendSeriesValues = array_map(fn($row) => $row['percentage'], $periodSummaries);
$categoryLabels = array_map(fn($row) => $row['category'], $latest['category_scores']);
$categoryValues = array_map(fn($row) => $row['percentage'], $latest['category_scores']);
$hasTrendData = !empty($trendSeriesLabels) && !empty($trendSeriesValues);
$hasCategoryData = !empty($categoryLabels) && !empty($categoryValues);
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
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main class="staff-profile-page">
    <section class="staff-profile-header">
        <a href="./stafflist.php" class="back-link">← Back to Staff List</a>
        <h1>Staff Performance Profile</h1>
        <p>View profile information, review KPI performance, and manage supervisor-facing staff details.</p>
    </section>

    <section class="profile-shell">
        <section class="profile-top-banner profile-card">
            <div class="profile-top-left">
                <img src="<?= $profilePhoto ?>" alt="<?= htmlspecialchars($staff['full_name']) ?>">
                <div class="profile-identity">
                    <h2><?= htmlspecialchars($staff['full_name']) ?></h2>
                    <p class="identity-line"><?= htmlspecialchars($staff['position']) ?> • <?= htmlspecialchars($staff['staff_code']) ?></p>
                    <p class="identity-line"><?= htmlspecialchars($staff['department']) ?></p>
                </div>
            </div>

            <div class="profile-meta-grid">
                <div class="profile-meta-item">
                    <i class="ph ph-envelope-simple"></i>
                    <div class="profile-meta-text">
                        <span>Email</span>
                        <strong><?= htmlspecialchars($staff['email'] ?? '-') ?></strong>
                    </div>
                </div>

                <div class="profile-meta-item">
                    <i class="ph ph-phone"></i>
                    <div class="profile-meta-text">
                        <span>Phone</span>
                        <strong><?= htmlspecialchars($staff['phone_number'] ?? '-') ?></strong>
                    </div>
                </div>

                <div class="profile-meta-item">
                    <i class="ph ph-calendar-blank"></i>
                    <div class="profile-meta-text">
                        <span>Join Date</span>
                        <strong><?= !empty($staff['join_date']) ? htmlspecialchars($staff['join_date']) : '-' ?></strong>
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

            <div class="profile-banner-actions">
                <button type="button" class="profile-edit-btn" id="editProfileBtn">Edit Profile</button>
            </div>
        </section>

        <section class="profile-main-card">
            <h2>Edit Staff Information</h2>

            <?php if ($success): ?>
                <div class="profile-message message-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="profile-message message-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" id="staffProfileForm">
                <input type="hidden" name="update_profile" value="1">
                <input type="hidden" name="staff_id" value="<?= (int)$staff['id'] ?>">

                <div class="profile-form-grid" id="profileFormGrid">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($staff['email'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone_number" value="<?= htmlspecialchars($staff['phone_number'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" name="department" value="<?= htmlspecialchars($staff['department'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Position</label>
                        <input type="text" name="position" value="<?= htmlspecialchars($staff['position'] ?? '') ?>">
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
            </section>
        <?php endif; ?>

        <section class="profile-summary-row">
            <article class="summary-box profile-card summary-score-card">
                <h3>Current KPI Score</h3>
                <div class="kpi-big-score"><?= number_format((float)$latest['percentage'], 2) ?>%</div>
                <div class="summary-subnote">
                    Previous 5-scale score: <?= number_format((float)$latest['score_5'], 2) ?> / 5
                </div>
                <div class="badge-row">
                    <span class="profile-pill <?= $performanceLevel === 'Top' ? 'pill-top' : ($performanceLevel === 'Good' ? 'pill-good' : ($performanceLevel === 'Average' ? 'pill-average' : 'pill-risk')) ?>">
                        <?= htmlspecialchars($performanceLevel) ?>
                    </span>
                    <span class="profile-pill <?= $riskLevel === 'Low' ? 'pill-low' : ($riskLevel === 'Moderate' ? 'pill-moderate' : 'pill-high') ?>">
                        <?= htmlspecialchars($riskLevel) ?> Risk
                    </span>
                    <span class="profile-pill <?= $trendLabel === 'Improving' ? 'pill-low' : ($trendLabel === 'Stable' ? 'pill-good' : 'pill-high') ?>">
                        <?= htmlspecialchars($trendLabel) ?>
                    </span>
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
                    <?php else: ?>
                        <li>No strength data available.</li>
                    <?php endif; ?>
                </ul>
            </article>

            <article class="summary-box profile-card summary-improve-card">
                <h3>Areas of Improvement</h3>
                <ul class="summary-list">
                    <?php if (!empty($improvements)): ?>
                        <?php foreach ($improvements as $item): ?>
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
            </article>

            <article class="profile-panel">
                <h2>Detailed KPI Breakdown</h2>
                <div class="detail-breakdown-grid">
                    <div class="detail-box">
                        <h3>Latest Summary</h3>
                        <ul class="detail-list">
                            <li><strong>Latest Period:</strong> <?= htmlspecialchars($latest['period'] ?: '-') ?></li>
                            <li><strong>Trend Delta:</strong> <?= number_format((float)$trendDelta, 2) ?></li>
                            <li><strong>Stability Score:</strong> <?= (int)$stabilityScore ?>/100</li>
                            <li><strong>Current 5-Scale Score:</strong> <?= number_format((float)$latest['score_5'], 2) ?> / 5</li>
                        </ul>
                    </div>

                    <div class="detail-box">
                        <h3>KPI Categories</h3>
                        <ul class="detail-list">
                            <?php if (!empty($latest['category_scores'])): ?>
                                <?php foreach ($latest['category_scores'] as $item): ?>
                                    <li>
                                        <strong><?= htmlspecialchars($item['category']) ?></strong>
                                        — <?= number_format((float)$item['percentage'], 2) ?>%
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li>No KPI category data available.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </article>
        </section>

        <section class="profile-wide-panel">
            <h2>Supervisor Comment</h2>
            <div class="text-panel">
                <p><?= htmlspecialchars($latestComment) ?></p>
            </div>
        </section>

        <section class="profile-wide-panel">
            <h2>Training & Development Recommendation</h2>
            <div class="text-panel">
                <p><?= htmlspecialchars($latestTraining) ?></p>
            </div>
        </section>

        <section class="profile-wide-panel">
            <h2>Supervisor Insight</h2>

            <div class="text-panel" style="margin-bottom:12px;">
                <h3>Performance Insight</h3>
                <p><?= htmlspecialchars($performanceInsight) ?></p>
            </div>

            <div class="text-panel" style="margin-bottom:12px;">
                <h3>Risk Interpretation</h3>
                <p><?= htmlspecialchars($riskInsight) ?></p>
            </div>

            <div class="text-panel">
                <h3>Recommended Supervisor Action</h3>
                <p><?= htmlspecialchars($supervisorAction) ?></p>
            </div>
        </section>

        <section class="profile-wide-panel">
            <h2>Quick Actions</h2>
            <div class="quick-actions-row">
                <a href="../Dashboard/staff_comparison_patched.php?staff1=<?= (int)$staff['id'] ?>&staff2=1" class="profile-action-btn" style="text-decoration:none;">
                    Compare Staff
                </a>
                <button type="button" class="profile-action-btn" onclick="alert('You can link this button to your Add KPI modal or Add KPI page.')">
                    Add KPI Record
                </button>
            </div>
        </section>
    </section>
</main>

<script>
const editBtn = document.getElementById('editProfileBtn');
const cancelBtn = document.getElementById('cancelEditBtn');
const formGrid = document.getElementById('profileFormGrid');
const editActions = document.getElementById('profileEditActions');

editBtn.addEventListener('click', () => {
    formGrid.style.display = 'grid';
    editActions.style.display = 'flex';
    editBtn.style.display = 'none';
});

cancelBtn.addEventListener('click', () => {
    formGrid.style.display = 'none';
    editActions.style.display = 'none';
    editBtn.style.display = 'inline-block';
});

const trendLabels = <?= json_encode($trendSeriesLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const trendValues = <?= json_encode($trendSeriesValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const categoryLabels = <?= json_encode($categoryLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const categoryValues = <?= json_encode($categoryValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

if (trendLabels.length > 0 && trendValues.length > 0) {
    Plotly.react('staffTrendChart', [
        {
            x: trendLabels,
            y: trendValues,
            type: 'scatter',
            mode: 'lines+markers',
            name: 'KPI %',
            line: { color: '#e8308c', width: 3, shape: 'spline' },
            marker: { size: 8, color: '#e8308c' },
            hovertemplate: '%{x}<br>KPI: %{y:.2f}%<extra></extra>'
        },
        {
            x: trendLabels,
            y: trendLabels.map(() => 80),
            type: 'scatter',
            mode: 'lines',
            name: 'Target',
            line: { color: '#14b8a6', dash: 'dash', width: 2 },
            hovertemplate: '%{x}<br>Target: %{y:.2f}%<extra></extra>'
        }
    ], {
        margin: { t: 10, r: 10, b: 40, l: 50 },
        paper_bgcolor: 'transparent',
        plot_bgcolor: 'transparent',
        yaxis: { range: [0, 100], title: 'KPI %' },
        legend: { orientation: 'h', y: 1.12 }
    }, { responsive: true, displayModeBar: true });
}

if (categoryLabels.length > 0 && categoryValues.length > 0) {
    Plotly.react('staffRadarChart', [{
        type: 'scatterpolar',
        r: categoryValues,
        theta: categoryLabels,
        fill: 'toself',
        name: 'KPI Category Score',
        line: { color: '#e8308c', width: 3 },
        fillcolor: 'rgba(232,48,140,0.20)',
        hovertemplate: '%{theta}<br>Score: %{r:.2f}%<extra></extra>'
    }], {
        paper_bgcolor: 'transparent',
        margin: { t: 20, r: 20, b: 20, l: 20 },
        polar: {
            radialaxis: {
                visible: true,
                range: [0, 100]
            }
        },
        showlegend: false
    }, { responsive: true, displayModeBar: true });

    Plotly.react('staffCategoryChart', [{
        x: categoryLabels,
        y: categoryValues,
        type: 'bar',
        marker: {
            color: ['#ec4899', '#f97316', '#f59e0b', '#3b82f6', '#8b5cf6', '#10b981']
        },
        text: categoryValues.map(v => Number(v).toFixed(1) + '%'),
        textposition: 'outside',
        hovertemplate: '%{x}<br>Score: %{y:.2f}%<extra></extra>'
    }], {
        margin: { t: 10, r: 10, b: 90, l: 50 },
        paper_bgcolor: 'transparent',
        plot_bgcolor: 'transparent',
        yaxis: { range: [0, 100], title: 'Score %' },
        xaxis: { tickangle: -18 },
        showlegend: false
    }, { responsive: true, displayModeBar: true });
}
</script>
</body>
</html>