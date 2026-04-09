<?php
session_start();
require_once __DIR__ . '/../config/db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

$staffId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($staffId <= 0) {
    die('Invalid staff ID.');
}

$success = '';
$error = '';
$selectedYear = trim((string)($_GET['year'] ?? ''));

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
$selectedYear = trim((string)($_GET['year'] ?? ''));

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
$allRecords = $records;

$availableYears = [];
foreach ($allRecords as $row) {
    $availableYears[(string)$row['year']] = true;
}
$availableYears = array_keys($availableYears);
rsort($availableYears);

if ($selectedYear !== '') {
    $records = array_values(array_filter($records, function ($row) use ($selectedYear) {
        return (string)$row['year'] === (string)$selectedYear;
    }));
}
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
        'items' => array_map(function ($item) {
            return [
                'code' => $item['code'],
                'description' => $item['description'],
                'score_5' => (float)$item['score'],
                'percentage' => round(((float)$item['score'] / 5) * 100, 2),
            ];
        }, $items),
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

$coreCompetencyLabels = [
    'S1.1' => 'Initiative',
    'S1.2' => 'Professional Conduct',
    'S1.3' => 'Reliability & Accountability'
];

$coreCompetencies = [];
$latestPeriodKey = $latest['period'] ?? '';

if ($latestPeriodKey !== '' && isset($groupedPeriods[$latestPeriodKey])) {
    $latestSection1Scores = $groupedPeriods[$latestPeriodKey]['section1_scores'] ?? [];

    foreach ($coreCompetencyLabels as $code => $label) {
        $score5 = isset($latestSection1Scores[$code]) ? (float)$latestSection1Scores[$code] : 0.0;
        $percentage = round(($score5 / 5) * 100, 2);

        $coreCompetencies[] = [
            'code' => $code,
            'label' => $label,
            'score_5' => $score5,
            'percentage' => $percentage,
        ];
    }
}

$coreCompetencyLabelsJs = array_map(fn($item) => $item['label'], $coreCompetencies);
$coreCompetencyValuesJs = array_map(fn($item) => $item['percentage'], $coreCompetencies);
$coreCompetencyColorsJs = array_map(function ($item) {
    $percent = (float)$item['percentage'];

    if ($percent >= 85) return '#16a34a';
    if ($percent >= 70) return '#3b82f6';
    if ($percent >= 50) return '#f59e0b';
    return '#ef4444';
}, $coreCompetencies);

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

$trendSeriesLabels = array_map(function ($row) {
    $raw = (string)$row['period'];

    if (preg_match('/^\d{4}-\d{2}$/', $raw)) {
        $date = DateTime::createFromFormat('Y-m', $raw);
        if ($date) {
            return $date->format('M Y');
        }
    }

    if (preg_match('/^\d{4}$/', $raw)) {
        return $raw;
    }

    return $raw;
}, $periodSummaries);

$trendSeriesValues = array_map(fn($row) => $row['percentage'], $periodSummaries);
$categoryLabels = array_map(fn($row) => $row['category'], $latest['category_scores']);
$categoryWrappedLabels = array_map(function ($label) {
    return match ($label) {
        'Customer Service Quality' => 'Customer<br>Service<br>Quality',
        'Daily Sales Operations' => 'Daily<br>Sales<br>Operations',
        'Inventory & Cost Control' => 'Inventory &<br>Cost Control',
        'Training, Learning & Team Contribution' => 'Training,<br>Learning & Team<br>Contribution',
        'Sales Target Contribution' => 'Sales Target<br>Contribution',
        'Store Operations Support' => 'Store Operations<br>Support',
        default => str_replace(' ', '<br>', $label),
    };
}, $categoryLabels);
$radarLabels = array_map(function ($label) {
    return match ($label) {
        'Training, Learning & Team Contribution' => 'Training & Team',
        'Inventory & Cost Control' => 'Inventory & Cost',
        'Daily Sales Operations' => 'Daily Sales',
        'Customer Service Quality' => 'Customer Service',
        'Sales Target Contribution' => 'Sales Target',
        'Store Operations Support' => 'Store Support',
        default => $label,
    };
}, $categoryLabels);

$categoryValues = array_map(fn($row) => $row['percentage'], $latest['category_scores']);
$hasTrendData = !empty($trendSeriesLabels) && !empty($trendSeriesValues);
$hasCategoryData = !empty($categoryLabels) && !empty($categoryValues);

$recordCount = count($periodSummaries);
$hasPreviousComparison = $recordCount >= 2;

$currentPerformanceBadgeClass =
    $performanceLevel === 'Top' ? 'pill-top' :
    ($performanceLevel === 'Good' ? 'pill-good' :
    ($performanceLevel === 'Average' ? 'pill-average' : 'pill-risk'));

$currentTrendBadgeClass =
    $trendLabel === 'Improving' ? 'pill-low' :
    ($trendLabel === 'Stable' ? 'pill-good' : 'pill-high');
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
        font-size: 2.8rem;
        font-weight: 900;
        color: #1a132f;
        letter-spacing: -0.5px;
        margin-bottom: 6px;
    }

    .staff-profile-header p {
        font-size: 1.05rem;
        color: #7c6f87;
        margin: 0;
    }

    .profile-shell {
        display: grid;
        gap: 24px;
    }

    .profile-card,
    .profile-panel,
    .profile-wide-panel,
    .profile-main-card {
        background: rgba(255, 255, 255, 0.96);
        border: 1px solid #efd8e5;
        border-radius: 22px;
        box-shadow: 0 10px 28px rgba(200, 80, 140, 0.08);
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
        grid-template-columns: repeat(2, minmax(0, 1fr));
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

    .profile-edit-btn,
    .profile-save-btn,
    .profile-cancel-btn {
        border: none;
        border-radius: 14px;
        padding: 10px 14px;
        font-size: 0.9rem;
        font-weight: 700;
        cursor: pointer;
    }

    .profile-edit-btn {
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

    .profile-top-filter-row {
        margin-top: 18px;
        display: flex;
        justify-content: flex-start;
    }

    .profile-top-filter-form {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .profile-top-filter-form select {
        min-width: 160px;
        height: 50px;
        border: 1px solid #ffc9ef;
        border-radius: 10px;
        background: #fff;
        color: #2f2138;
        font-size: 0.95rem;
        font-weight: 600;
        padding: 0 12px;
        outline: none;
        box-shadow: 0 4px 12px rgba(200, 80, 140, 0.05);
    }

    .profile-edit-drawer {
        border: 1px solid #edd9e7;
        background: #fff;
    }

    .profile-summary-row {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 18px;
        margin-top: 12px;
    }

    .profile-summary-row-refined {
        grid-template-columns: 1.1fr 1fr 1fr;
        align-items: stretch;
    }

    .summary-box {
        padding: 18px 20px;
    }

    .summary-score-card-main {
        box-shadow: 0 10px 24px rgba(219, 39, 119, 0.08);
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

    .summary-title-row {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
    }

    .summary-title-row h3 {
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 1.02rem;
        font-weight: 800;
        color: #221834;
    }

    .summary-title-row h3 i {
        font-size: 1rem;
        color: #b35d99;
    }

    .summary-title-green h3 {
        color: #166534;
    }

    .summary-title-green h3 i {
        color: #16a34a;
    }

    .summary-title-amber h3 {
        color: #a16207;
    }

    .summary-title-amber h3 i {
        color: #d97706;
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

    .summary-list-strong {
        list-style: none;
        padding-left: 0;
        margin: 0;
    }

    .summary-list-strong li {
        display: flex;
        flex-direction: column;
        gap: 2px;
        padding: 8px 0;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .summary-list-strong li:last-child {
        border-bottom: none;
    }

    .summary-item-label {
        font-size: 1rem;
        font-weight: 800;
        line-height: 1.4;
    }

    .summary-item-label-green {
        color: #15803d;
    }

    .summary-item-label-amber {
        color: #b45309;
    }

    .summary-item-value {
        font-size: 0.9rem;
        color: #6b5b67;
        font-weight: 600;
    }

    .profile-chart-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px;
        align-items: stretch;
        margin-top: 24px;
    }

    .profile-chart-row > .profile-panel,
    .profile-chart-row > .profile-wide-panel {
        height: 100%;
        min-height: 520px;
        display: flex;
        flex-direction: column;
    }

    .profile-panel {
        border: 1px solid #efd8e5;
        border-radius: 22px;
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 10px 28px rgba(200, 80, 140, 0.08);
    }

    .profile-panel h2 {
        margin-bottom: 18px;
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

    .chart-center-wrapper {
        display: flex;
        justify-content: center;
        align-items: center;
        width: 100%;
    }

    #staffCategoryChart {
        width: 95%;
        max-width: 900px;
    }

    #coreCompetencyChart {
        width: 100%;
        max-width: 100%;
    }

    .core-chart-shell {
        background: linear-gradient(180deg, #fffefe 0%, #fff7fb 100%);
        border: 1px solid #f0dce7;
        border-radius: 18px;
        padding: 14px 14px 8px;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8), 0 8px 18px rgba(200, 80, 140, 0.06);
    }

    .kpi-drilldown-list {
        display: grid;
        gap: 14px;
        flex: 1;
    }

    .kpi-drill-card {
        border: 1px solid #efdeea;
        border-radius: 18px;
        background: #fffafd;
        overflow: hidden;
    }

    .kpi-drill-header {
        width: 100%;
        border: none;
        background: transparent;
        padding: 16px 18px;
        cursor: pointer;
        text-align: left;
    }

    .kpi-drill-title-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 10px;
    }

    .kpi-drill-name {
        font-size: 1rem;
        font-weight: 800;
        color: #231942;
    }

    .kpi-drill-percent {
        font-size: 0.96rem;
        font-weight: 800;
        color: #7c3aed;
        white-space: nowrap;
    }

    .kpi-progress-wrap {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .kpi-progress-track {
        flex: 1;
        height: 16px;
        border-radius: 999px;
        background: #f3edf2;
        overflow: hidden;
        border: 1px solid #eadfe7;
    }

    .kpi-progress-fill {
        height: 100%;
        border-radius: 999px;
    }

    .kpi-progress-fill.bar-strong {
        background: linear-gradient(90deg, #86efac 0%, #22c55e 100%);
    }

    .kpi-progress-fill.bar-good {
        background: linear-gradient(90deg, #bfdbfe 0%, #3b82f6 100%);
    }

    .kpi-progress-fill.bar-mid {
        background: linear-gradient(90deg, #fde68a 0%, #f59e0b 100%);
    }

    .kpi-progress-fill.bar-risk {
        background: linear-gradient(90deg, #fecaca 0%, #ef4444 100%);
    }

    .kpi-drill-icon {
        font-size: 1.1rem;
        font-weight: 800;
        color: #8f6d83;
        transition: transform 0.2s ease;
    }

    .kpi-drill-card.is-open .kpi-drill-icon {
        transform: rotate(180deg);
    }

    .kpi-drill-body {
        padding: 0 18px 16px;
        border-top: 1px solid #f1e5ec;
        background: #fff;
    }

    .kpi-item-table {
        display: grid;
        gap: 12px;
        margin-top: 14px;
    }

    .kpi-item-row {
        display: flex;
        justify-content: space-between;
        gap: 16px;
        padding: 12px 14px;
        border: 1px solid #f0e3ea;
        border-radius: 14px;
        background: #fffafd;
    }

    .kpi-item-left strong {
        display: block;
        color: #231942;
        margin-bottom: 4px;
    }

    .kpi-item-left p {
        margin: 0;
        color: #6f6376;
        font-size: 0.9rem;
        line-height: 1.5;
    }

    .kpi-item-right {
        min-width: 88px;
        text-align: right;
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 4px;
    }

    .kpi-item-right span {
        color: #7d6979;
        font-size: 0.88rem;
        font-weight: 600;
    }

    .kpi-item-right strong {
        color: #231942;
        font-size: 0.95rem;
        font-weight: 800;
    }

    .profile-hero-card {
        background: #fff;
        border: 1px solid #efd8e5;
        border-radius: 22px;
        overflow: hidden;
        box-shadow: 0 10px 28px rgba(200, 80, 140, 0.08);
        min-height: auto;
        align-items: start;
    }

    .profile-hero-topband {
        height: 66px;
        background: linear-gradient(90deg, #8f285f 0%, #ff9de6 55%, #e1a1d5 100%);
    }

    .profile-hero-content {
        display: grid;
        grid-template-columns: 1fr 170px;
        grid-template-areas:
            "left actions"
            "details actions";
        gap: 10px 18px;
        padding: 10px 18px 8px;
        margin-top: -10px;
        align-items: start;
    }

    .profile-hero-left {
        grid-area: left;
        display: flex;
        align-items: center;
        gap: 16px;
        min-width: 0;
    }

    .profile-hero-left img {
        width: 82px;
        height: 82px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid #fff;
        background: #fff;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.10);
        flex-shrink: 0;
        margin-top:15px;
    }

    .profile-hero-identity {
        min-width: 0;
    }

    .profile-hero-title-row {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 0;
        padding-bottom: 0;
    }

    .profile-hero-identity h2 {
        margin: 0;
        font-size: 1.7rem;
        font-weight: 800;
        color: #231942;
        line-height: 1.2;
    }

    .hero-role-line {
        margin: 0;
        color: #675a70;
        font-size: 0.98rem;
        font-weight: 600;
        line-height: 1.4;
    }

    .profile-hero-details-row {
        grid-area: details;
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        align-items: start;
        padding-top: 8px;
        border-top: 1px solid #f1e4ec;
        margin-top: -8px;
    }

    .hero-detail-inline {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        min-width: 0;
    }

    .hero-detail-inline i {
        font-size: 1rem;
        color: #a78a99;
        flex-shrink: 0;
        margin-top: 2px;
    }

    .hero-detail-inline span {
        display: block;
        font-size: 0.74rem;
        color: #9b8796;
        margin-bottom: 2px;
    }

    .hero-detail-inline strong {
        display: block;
        font-size: 0.93rem;
        color: #2a2038;
        line-height: 1.3;
        word-break: break-word;
    }

.profile-hero-action {
    grid-area: actions;
    display: flex;
    flex-direction: column;
    gap: 10px;
    align-items: stretch;
    justify-content: flex-start;
    align-self: start;
    padding-top: 6px;
}

    .profile-action-btn {
        min-width: 120px;
        min-height: 40px;
        padding: 0 14px;
        border-radius: 12px;
        border: 1px solid #ead6e2;
        background: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        font-weight: 700;
        font-size: 0.88rem;
        cursor: pointer;
        white-space: nowrap;
        box-shadow: 0 4px 10px rgba(200, 80, 140, 0.05);
        transition: 0.2s ease;
    }

    .profile-action-btn:hover {
        transform: translateY(-1px);
    }

    .action-kpi {
        background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
        color: #b45309;
        border-color: #fed7aa;
    }

    .action-report {
        background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        color: #1d4ed8;
        border-color: #bfdbfe;
    }

    .action-download {
        background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
        color: #15803d;
        border-color: #bbf7d0;
    }

    .action-profile {
        background: linear-gradient(135deg, #c070e0 0%, #e8308c 100%);
        color: #fff;
        border: none;
    }

    @media (max-width: 1300px) {
        .profile-summary-row,
        .profile-chart-row,
        .detail-breakdown-grid,
        .profile-form-grid {
            grid-template-columns: 1fr;
        }

        .profile-hero-content {
            grid-template-columns: 1fr;
            grid-template-areas:
                "left"
                "details"
                "actions";
            margin-top: 0;
        }

        .profile-hero-topband {
            height: 60px;
        }

        .profile-hero-details-row {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .profile-hero-action {
            flex-direction: row;
            flex-wrap: wrap;
            justify-content: flex-start;
            margin-top: 0;
        }
    }

    @media (max-width: 1100px) {
        .staff-profile-page {
            margin-left: 0;
            padding: 110px 18px 28px;
        }
    }

    @media (max-width: 768px) {
        .profile-hero-details-row {
            grid-template-columns: 1fr;
        }
    }

    .profile-bottom-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
    margin-top: 20px;
}

.supervisor-comment-card {
    background: linear-gradient(135deg, #fff9e6, #fffdf5);
    border: 1px solid #f7e3a1;
    box-shadow: 0 6px 18px rgba(255, 193, 7, 0.15);
}

.training-recommendation-card {
    background: linear-gradient(135deg, #f0fdf4, #f8fffc);
    border: 1px solid #bbf7d0;
    box-shadow: 0 6px 18px rgba(34, 197, 94, 0.12);
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    justify-content: center;
    align-items: center;
}

.modal-content {
    background: white;
    border-radius: 20px;
    position: relative;
    overflow-y: auto;
}

.chart-interpretation {
    margin-top: 14px;
    background: linear-gradient(180deg, #f6f8ff 0%, #fdfcff 100%);
    border: 1px solid #dfe4ff;
    border-radius: 16px;
    padding: 14px 16px;
    color: #4b3a4c;
    line-height: 1.6;
    font-size: 0.92rem;
}

.chart-interpretation strong {
    color: #231942;
}

.chart-interpretation ul {
    margin: 8px 0 0;
    padding-left: 18px;
}

.chart-interpretation li + li {
    margin-top: 6px;
}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main class="staff-profile-page">
    <section class="staff-profile-header">
    <a href="./stafflist.php" class="back-link">← Back to Staff List</a>
    <h1>Sales Assistant Profile</h1>
    <p>View profile information, review KPI performance, and manage supervisor-facing staff details.</p>

    <div class="profile-top-filter-row">
        <form method="GET" class="profile-top-filter-form">
            <input type="hidden" name="id" value="<?= (int)$staff['id'] ?>">

            <select name="year" onchange="this.form.submit()">
                <option value=""> Years</option>
                <?php foreach ($availableYears as $year): ?>
                    <option value="<?= htmlspecialchars($year) ?>" <?= $selectedYear === (string)$year ? 'selected' : '' ?>>
                        <?= htmlspecialchars($year) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</section>

 <section class="profile-hero-card">

    <div class="profile-hero-topband"></div>

    <div class="profile-hero-content">

        <!-- ROW 1: avatar + identity -->
        <div class="profile-hero-left">
            <img src="<?= $profilePhoto ?>" alt="<?= htmlspecialchars($staff['full_name']) ?>">

            <div class="profile-hero-identity">
                <div class="profile-hero-title-row">
                    <h2><?= htmlspecialchars($staff['full_name']) ?></h2>

                    <span class="profile-pill <?= $currentPerformanceBadgeClass ?>">
                        <?= htmlspecialchars($performanceLevel) ?>
                    </span>
                </div>

                <p class="hero-role-line">
                    <?= htmlspecialchars($staff['position']) ?> • <?= htmlspecialchars($staff['staff_code']) ?>
                </p>
            </div>
        </div>

        <!-- ROW 2 LEFT: details -->
        <div class="profile-hero-details-row">
            <div class="hero-detail-inline">
                <i class="ph ph-envelope-simple"></i>
                <div>
                    <span>Email</span>
                    <strong><?= htmlspecialchars($staff['email'] ?? '-') ?></strong>
                </div>
            </div>

            <div class="hero-detail-inline">
                <i class="ph ph-phone"></i>
                <div>
                    <span>Phone</span>
                    <strong><?= htmlspecialchars($staff['phone_number'] ?? '-') ?></strong>
                </div>
            </div>

            <div class="hero-detail-inline">
                <i class="ph ph-calendar-blank"></i>
                <div>
                    <span>Join Date</span>
                    <strong><?= !empty($staff['join_date']) ? htmlspecialchars($staff['join_date']) : '-' ?></strong>
                </div>
            </div>

            <div class="hero-detail-inline">
                <i class="ph ph-buildings"></i>
                <div>
                    <span>Department</span>
                    <strong><?= htmlspecialchars($staff['department'] ?? '-') ?></strong>
                </div>
            </div>
        </div>

        <!-- ROW 2 RIGHT: buttons -->
        <div class="profile-hero-action">
            <button class="profile-action-btn action-kpi" onclick="openAddKPIModal(<?= $staff['id'] ?>, '<?= htmlspecialchars($staff['full_name'], ENT_QUOTES) ?>')">
                Edit KPI
            </button>

            <button class="profile-action-btn action-report">
                <i class="ph ph-eye"></i> View Report
            </button>

            <button class="profile-action-btn action-download">
                <i class="ph ph-download-simple"></i> Download
            </button>

            <button class="profile-action-btn action-profile" id="editProfileBtn">
                <i class="ph ph-pencil-simple"></i> Edit Profile
            </button>
        </div>

    </div>
</section>

</section>

    <section class="profile-edit-drawer profile-main-card" id="profileEditDrawer" style="display:none;">
        <h2>Edit Profile</h2>

        <?php if ($success): ?>
            <div class="profile-message message-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="profile-message message-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="staffProfileForm">
            <input type="hidden" name="update_profile" value="1">
            <input type="hidden" name="staff_id" value="<?= (int)$staff['id'] ?>">

            <div class="profile-form-grid" id="profileFormGrid" style="display:grid;">
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

            <div class="profile-action-row" id="profileEditActions" style="display:flex;">
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
        
        
    <section class="profile-summary-row profile-summary-row-refined">
        <article class="summary-box profile-card summary-score-card summary-score-card-main">
            <div class="summary-title-row">
                <h3><i class="ph ph-chart-line-up"></i> Current Performance Score</h3>
            </div>

            <div class="kpi-big-score"><?= number_format((float)$latest['percentage'], 2) ?>%</div>

            <?php if ($hasPreviousComparison): ?>
                <div class="summary-subnote">
                    Previous 5-scale score: <?= number_format((float)$latest['score_5'], 2) ?> / 5
                </div>
            <?php else: ?>
                <div class="summary-subnote">
                    Current 5-scale score: <?= number_format((float)$latest['score_5'], 2) ?> / 5
                </div>
            <?php endif; ?>

            <div class="badge-row">
                <span class="profile-pill <?= $currentPerformanceBadgeClass ?>">
                    <?= htmlspecialchars($performanceLevel) ?>
                </span>
            </div>
        </article>

        <article class="summary-box profile-card summary-strength-card">
            <div class="summary-title-row summary-title-green">
                <h3><i class="ph ph-medal"></i> Strengths</h3>
            </div>

            <ul class="summary-list summary-list-strong">
                <?php if (!empty($strengths)): ?>
                    <?php foreach ($strengths as $item): ?>
                        <li>
                            <span class="summary-item-label summary-item-label-green">
                                <?= htmlspecialchars($item['category']) ?>
                            </span>
                            <span class="summary-item-value">
                                <?= number_format((float)$item['percentage'], 2) ?>%
                            </span>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>No strength data available.</li>
                <?php endif; ?>
            </ul>
        </article>

        <article class="summary-box profile-card summary-improve-card">
            <div class="summary-title-row summary-title-amber">
                <h3><i class="ph ph-warning-circle"></i> Areas of Improvement</h3>
            </div>

            <ul class="summary-list summary-list-strong">
                <?php if (!empty($improvements)): ?>
                    <?php foreach ($improvements as $item): ?>
                        <li>
                            <span class="summary-item-label summary-item-label-amber">
                                <?= htmlspecialchars($item['category']) ?>
                            </span>
                            <span class="summary-item-value">
                                <?= number_format((float)$item['percentage'], 2) ?>%
                            </span>
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
                <?php else: ?>
                    <div class="chart-interpretation">
                        <strong>Interpretation:</strong>
                        The latest KPI performance is <strong><?= number_format((float)$latest['percentage'], 2) ?>%</strong>,
                        which is classified as <strong><?= htmlspecialchars($performanceLevel) ?></strong>.
                        The recent performance pattern is <strong><?= htmlspecialchars($trendLabel) ?></strong>
                        with a stability score of <strong><?= (int)$stabilityScore ?>%</strong>.
                        <?php if ($trendLabel === 'Improving'): ?>
                            This suggests the staff member has shown positive movement in recent evaluation periods.
                        <?php elseif ($trendLabel === 'Declining'): ?>
                            This suggests the staff member’s KPI performance has weakened in recent periods and should be monitored closely.
                        <?php else: ?>
                            This suggests the staff member’s KPI performance has remained relatively consistent across recent periods.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </article>

            <article class="profile-panel">
                <h2>Performance Radar</h2>
                <div id="staffRadarChart" style="height:320px;"></div>

                <?php if (!$hasCategoryData): ?>
                    <p class="empty-chart-note">No KPI category data available for this staff.</p>
                <?php else: ?>
                    <div class="chart-interpretation">
                        <strong>Interpretation:</strong>
                        The radar chart shows how the staff member performs across KPI categories in the latest evaluation period.
                        <?php if (!empty($strengths) && !empty($improvements)): ?>
                            The strongest area is <strong><?= htmlspecialchars($strengths[0]['category']) ?></strong>
                            at <strong><?= number_format((float)$strengths[0]['percentage'], 2) ?>%</strong>,
                            while the weakest area is <strong><?= htmlspecialchars($improvements[0]['category']) ?></strong>
                            at <strong><?= number_format((float)$improvements[0]['percentage'], 2) ?>%</strong>.
                            This indicates that performance is stronger in selected KPI categories but still uneven across the full KPI profile.
                        <?php else: ?>
                            This chart helps identify which KPI categories are relatively stronger and which require more improvement.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </article>
        </section>

        <section class="profile-chart-row">
            <article class="profile-panel">
            <h2>Core Competencies</h2>
            <div class="core-chart-shell">
                <div id="coreCompetencyChart" style="height:470px;"></div>
            </div>

            <?php if (empty($coreCompetencies)): ?>
                <p class="empty-chart-note">No core competency data available for this staff.</p>
            <?php else: ?>
                
            <?php
                $bestCores = [];
                $weakCores = [];

                if (!empty($coreCompetencies)) {
                    $percentages = array_column($coreCompetencies, 'percentage');
                    $maxCorePercent = max($percentages);
                    $minCorePercent = min($percentages);

                    $bestCores = array_values(array_filter($coreCompetencies, function ($item) use ($maxCorePercent) {
                        return (float)$item['percentage'] === (float)$maxCorePercent;
                    }));

                    $weakCores = array_values(array_filter($coreCompetencies, function ($item) use ($minCorePercent) {
                        return (float)$item['percentage'] === (float)$minCorePercent;
                    }));
                }

                $bestCoreNames = array_map(fn($item) => $item['label'], $bestCores);
                $weakCoreNames = array_map(fn($item) => $item['label'], $weakCores);

                $bestCoreText = implode(', ', $bestCoreNames);
                $weakCoreText = implode(', ', $weakCoreNames);
            ?>
                            
                <div class="chart-interpretation">
                    <strong>Interpretation:</strong>
                    This chart highlights the staff member’s latest core competency performance.
                    
                    <?php if (!empty($bestCores) && !empty($weakCores)): ?>
                        <?php if ((float)$maxCorePercent === (float)$minCorePercent): ?>
                            All core competencies are currently at the same level, with
                            <strong><?= htmlspecialchars($bestCoreText) ?></strong>
                            each scoring <strong><?= number_format((float)$maxCorePercent, 2) ?>%</strong>.
                            This suggests the staff member’s competency profile is balanced, with no single area standing out as significantly stronger or weaker.
                        <?php else: ?>
                            The strongest core competenc<?= count($bestCores) > 1 ? 'ies are' : 'y is' ?>
                            <strong><?= htmlspecialchars($bestCoreText) ?></strong>
                            at <strong><?= number_format((float)$maxCorePercent, 2) ?>%</strong>,
                            while the weakest core competenc<?= count($weakCores) > 1 ? 'ies are' : 'y is' ?>
                            <strong><?= htmlspecialchars($weakCoreText) ?></strong>
                            at <strong><?= number_format((float)$minCorePercent, 2) ?>%</strong>.
                            This suggests that supervisory support should focus more on the lowest-scoring competenc<?= count($weakCores) > 1 ? 'ies' : 'y' ?>
                            while maintaining the stronger area<?= count($bestCores) > 1 ? 's' : '' ?>.
                        <?php endif; ?>
                    <?php else: ?>
                        This chart helps compare the staff member’s capability across the core competency dimensions.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </article>

            <section class="profile-panel">
                <h2>Detailed KPI Breakdown</h2>

                <div class="kpi-drilldown-list">
                    <?php if (!empty($latest['category_scores'])): ?>
                        <?php foreach ($latest['category_scores'] as $index => $category): ?>
                            <?php
                                $percent = (float)$category['percentage'];
                                $barClass = 'bar-mid';
                                if ($percent >= 85) {
                                    $barClass = 'bar-strong';
                                } elseif ($percent >= 70) {
                                    $barClass = 'bar-good';
                                } elseif ($percent >= 50) {
                                    $barClass = 'bar-mid';
                                } else {
                                    $barClass = 'bar-risk';
                                }
                            ?>
                            <div class="kpi-drill-card">
                                <button
                                    type="button"
                                    class="kpi-drill-header"
                                    data-target="kpiDetail<?= $index ?>"
                                >
                                    <div class="kpi-drill-title-row">
                                        <span class="kpi-drill-name"><?= htmlspecialchars($category['category']) ?></span>
                                        <span class="kpi-drill-percent"><?= number_format($percent, 2) ?>%</span>
                                    </div>

                                    <div class="kpi-progress-wrap">
                                        <div class="kpi-progress-track">
                                            <div class="kpi-progress-fill <?= $barClass ?>" style="width: <?= min($percent, 100) ?>%;"></div>
                                        </div>
                                        <span class="kpi-drill-icon">▾</span>
                                    </div>
                                </button>

                                <div class="kpi-drill-body" id="kpiDetail<?= $index ?>" style="display:none;">
                                    <?php if (!empty($category['items'])): ?>
                                        <div class="kpi-item-table">
                                            <?php foreach ($category['items'] as $item): ?>
                                                <div class="kpi-item-row">
                                                    <div class="kpi-item-left">
                                                        <strong><?= htmlspecialchars($item['code']) ?></strong>
                                                        <p><?= htmlspecialchars($item['description'] ?: 'No description') ?></p>
                                                    </div>
                                                    <div class="kpi-item-right">
                                                        <span><?= number_format((float)$item['score_5'], 2) ?>/5</span>
                                                        <strong><?= number_format((float)$item['percentage'], 2) ?>%</strong>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="empty-chart-note">No KPI item details available for this category.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="empty-chart-note">No KPI category data available.</p>
                    <?php endif; ?>
                </div>
            </section>
        </section>

        <section class="profile-wide-panel">
        <div class="profile-panel supervisor-comment-card">
            <h2>Supervisor Comment</h2>
            <div class="text-panel">
                <p><?= htmlspecialchars($latestComment) ?></p>
            </div>
        </div>
        </section>

        <section class="profile-wide-panel">
        <div class="profile-panel training-recommendation-card">
            <h2>Training & Development Recommendation</h2>
            <div class="text-panel">
                <p><?= htmlspecialchars($latestTraining) ?></p>
            </div>
        </div>
        </section>

    </section>
</main>

<div id="addKPIModal" class="modal">
    <div class="modal-content"
         style="max-width:900px; width:95%; max-height:92vh; padding:0; border-radius:20px;">
        <div id="modalContentTarget">
            Loading...
        </div>
    </div>
</div>

<script>
const editBtn = document.getElementById('editProfileBtn');
const cancelBtn = document.getElementById('cancelEditBtn');
const editDrawer = document.getElementById('profileEditDrawer');

if (editBtn && cancelBtn && editDrawer) {
    editBtn.addEventListener('click', () => {
        editDrawer.style.display = 'block';
        editBtn.style.display = 'none';
        editDrawer.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    cancelBtn.addEventListener('click', () => {
        editDrawer.style.display = 'none';
        editBtn.style.display = 'inline-flex';
    });
}

const trendLabels = <?= json_encode($trendSeriesLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const trendValues = <?= json_encode($trendSeriesValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const categoryLabels = <?= json_encode($categoryLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const categoryValues = <?= json_encode($categoryValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

if (trendLabels.length > 0 && trendValues.length > 0) {
    const hasSingleTrendPoint = trendLabels.length === 1;

    const trendTraces = [
        {
            x: trendLabels,
            y: trendValues,
            type: 'scatter',
            mode: hasSingleTrendPoint ? 'markers+text' : 'lines+markers',
            name: 'KPI %',
            line: { color: '#e8308c', width: 3, shape: 'spline' },
            marker: {
                size: hasSingleTrendPoint ? 12 : 8,
                color: '#e8308c'
            },
            text: hasSingleTrendPoint ? [trendValues[0].toFixed(2) + '%'] : [],
            textposition: 'top center',
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
    ];

    Plotly.react('staffTrendChart', trendTraces, {
        margin: { t: 10, r: 20, b: 50, l: 50 },
        paper_bgcolor: 'transparent',
        plot_bgcolor: 'transparent',
        yaxis: {
            range: [0, 100],
            title: '<b>KPI %</b>',
            gridcolor: '#f1e4ec'
        },
        xaxis: {
            type: '<b>category</b>',
            tickangle: 0,
            automargin: true
        },
        legend: {
            orientation: 'h',
            y: 1.12
        }
    }, { responsive: true, displayModeBar: true });
}
const radarLabels = <?= json_encode($radarLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

if (categoryLabels.length > 0 && categoryValues.length > 0) {
        Plotly.react('staffRadarChart', [{
            type: 'scatterpolar',
            r: categoryValues,
            theta: radarLabels,
            fill: 'toself',
            name: '<b>KPI Category Score</b>',
            line: { color: '#e8308c', width: 3 },
            fillcolor: 'rgba(232,48,140,0.18)',
            hovertemplate: '%{theta}<br><b>Score: %{r:.2f}%</b><extra></extra>'
        }], {
            paper_bgcolor: 'transparent',
            margin: { t: 30, r: 40, b: 30, l: 40 },
            polar: {
                radialaxis: {
                    visible: true,
                    range: [0, 100],
                    tickfont: { size: 10 }
                },
                angularaxis: {
                    tickfont: { size: 11 }
                }
            },
            showlegend: false
        }, { responsive: true, displayModeBar: true });

    const coreCompetencyLabels = <?= json_encode($coreCompetencyLabelsJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const coreCompetencyValues = <?= json_encode($coreCompetencyValuesJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const coreCompetencyColors = <?= json_encode($coreCompetencyColorsJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    
 if (coreCompetencyLabels.length > 0 && coreCompetencyValues.length > 0) {
    Plotly.react('coreCompetencyChart', [{
        x: coreCompetencyLabels.map(label => label.replace(/ & /g, '<br>& ')),
        y: coreCompetencyValues,
        type: 'bar',
        marker: {
            color: coreCompetencyColors,
            line: {
                color: 'rgba(255,255,255,0.95)',
                width: 2
            }
        },
        text: coreCompetencyValues.map(v => Number(v).toFixed(1) + '%'),
        textposition: 'outside',
        textfont: {
            size: 15,
            color: '#4b3a4c'
        },
        hovertemplate:
            '<b>%{x}</b><br>' +
            'Score: %{y:.2f}%<extra></extra>'
    }], {
        margin: { t: 30, r: 35, b: 95, l: 70 },
        paper_bgcolor: 'transparent',
        plot_bgcolor: 'transparent',
        bargap: 0.32,
        xaxis: {
            title: {
                text: '<b>Core Competency</b>',
                font: { size: 16, color: '#35263f' }
            },
            tickangle: 0,
            automargin: true,
            tickfont: {
                size: 13,
                color: '#4b3a4c'
            }
        },
        yaxis: {
            title: {
                text: '<b>Score %</b>',
                font: { size: 16, color: '#35263f' }
            },
            range: [0, 120],
            gridcolor: '#f1e4ec',
            gridwidth: 1,
            zeroline: false,
            tickfont: {
                size: 12,
                color: '#6f6376'
            }
        },
        showlegend: false
    }, { responsive: true, displayModeBar: true });
}
}
    document.querySelectorAll('.kpi-drill-header').forEach(button => {
        button.addEventListener('click', () => {
            const targetId = button.getAttribute('data-target');
            const target = document.getElementById(targetId);
            const card = button.closest('.kpi-drill-card');

            if (!target || !card) return;

            const isOpen = target.style.display === 'block';

            target.style.display = isOpen ? 'none' : 'block';
            card.classList.toggle('is-open', !isOpen);
        });
    });
</script>

<script src="staff.js"></script>

</body>
</html>
