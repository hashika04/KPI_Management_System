<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

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
    'People, Training, Learning & Team Contribution' => 0.10,
    'Inventory & Cost Control' => 0.05,
    'Store Operations Support' => 0.15
];

try {
    $action = $_GET['action'] ?? 'dashboard';

    switch ($action) {
        case 'dashboard':
            respond(getDashboardPayload($conn, [
                'year' => trim((string)($_GET['year'] ?? '')),
                'department' => trim((string)($_GET['department'] ?? 'All Departments')),
                'kpi_category' => trim((string)($_GET['kpi_category'] ?? 'All Categories')),
                'period' => trim((string)($_GET['period'] ?? 'Monthly')),
            ]));
            break;

        case 'search_staff':
            respond(searchStaff($conn, [
                'query' => trim((string)($_GET['query'] ?? '')),
                'department' => trim((string)($_GET['department'] ?? 'All Departments')),
                'performance' => trim((string)($_GET['performance'] ?? 'All Performance')),
                'exclude_id' => trim((string)($_GET['exclude_id'] ?? '')),
            ]));
            break;

        case 'compare_staff':
            respond(getComparePayload($conn, [
                'staff1' => (int)($_GET['staff1'] ?? 0),
                'staff2' => (int)($_GET['staff2'] ?? 0),
                'period' => trim((string)($_GET['period'] ?? 'Yearly')),
                'year' => trim((string)($_GET['year'] ?? '')),
            ]));
            break;

        default:
            http_response_code(400);
            respond(['error' => 'Invalid action.']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    respond([
        'error' => 'Analytics data could not be loaded.',
        'message' => $e->getMessage(),
    ]);
}

function respond(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ------------------------------------------------------------
// FETCH RAW KPI RECORDS (FIXED DATE PARSING)
// ------------------------------------------------------------
function fetchKpiRecords(mysqli $conn): array
{
    $sql = "
        SELECT
            s.id AS staff_id,
            s.full_name,
            s.email,
            s.profile_photo,
            s.staff_code,
            s.department,
            s.position,
            kd.`Date` AS evaluation_date,
            kd.`Name` AS kpi_name,
            kd.`KPI_Code` AS kpi_code,
            kd.`Score` AS score,
            km.`section`,
            km.`kpi_group`,
            km.`kpi_description`,
            kc.`Supervisor Comments` AS supervisor_comments,
            kc.`Training/Development Recommendations` AS training_recommendations,
            kc.`Year` AS comment_year
        FROM `kpi_data` kd
        INNER JOIN `staff` s
            ON s.`full_name` = kd.`Name`
        LEFT JOIN `kpi_master_list` km
            ON km.`kpi_code` = kd.`KPI_Code`
        LEFT JOIN `kpi_comment` kc
            ON kc.`Name` = kd.`Name`
            AND kc.`Year` = YEAR(STR_TO_DATE(kd.`Date`, '%Y-%m-%d'))
            AND kc.`Year` > 0
            AND kc.`Name` <> 'Name'
        WHERE kd.`Date` IS NOT NULL
          AND kd.`Name` IS NOT NULL
          AND kd.`KPI_Code` IS NOT NULL
        ORDER BY STR_TO_DATE(kd.`Date`, '%Y-%m-%d') ASC,
                 s.`full_name` ASC,
                 kd.`KPI_Code` ASC
    ";

    $result = $conn->query($sql);
    $rows = [];

    while ($row = $result->fetch_assoc()) {
        $date = DateTime::createFromFormat('Y-m-d', trim((string)$row['evaluation_date']));
        if (!$date) {
            $date = DateTime::createFromFormat('m/d/Y', trim((string)$row['evaluation_date']));
            if (!$date) continue;
        }

        $row['score'] = (int)$row['score'];
        $row['year'] = (int)$date->format('Y');
        $row['month'] = $date->format('Y-m');
        $row['period_date'] = $date->format('Y-m-d');
        $row['department'] = trim((string)($row['department'] ?? '')) ?: 'Unassigned';
        $row['position'] = trim((string)($row['position'] ?? '')) ?: 'Staff';
        $row['staff_code'] = trim((string)($row['staff_code'] ?? ''));
        $row['profile_photo'] = normalizeAvatarPath((string)($row['profile_photo'] ?? ''));
        $row['section'] = trim((string)($row['section'] ?? ''));
        $row['kpi_group'] = trim((string)($row['kpi_group'] ?? ''));
        $rows[] = $row;
    }

    return $rows;
}

// ------------------------------------------------------------
// BUILD PERIOD-STAFF SCORES
// ------------------------------------------------------------
function buildPeriodStaffScores(array $records, string $periodType): array
{
    global $SECTION1_COMPETENCY_WEIGHTS, $SECTION2_GROUP_WEIGHTS;

    $groups = [];

    foreach ($records as $row) {
        $periodKey = $periodType === 'Monthly' ? $row['month'] : (string)$row['year'];
        $staffId = (int)$row['staff_id'];
        $groupKey = $periodKey . '|' . $staffId;

        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                'period' => $periodKey,
                'period_date' => $row['period_date'],
                'staff' => [
                    'id' => $staffId,
                    'name' => $row['full_name'],
                    'email' => $row['email'] ?? '',
                    'profile_photo' => $row['profile_photo'],
                    'staff_code' => $row['staff_code'],
                    'department' => $row['department'],
                    'position' => $row['position'],
                ],
                'section1_scores' => [],
                'section2_groups' => [],
                'all_scores' => [],
                'comments' => $row['supervisor_comments'] ?? '',
                'training' => $row['training_recommendations'] ?? '',
                'year' => $row['year'],
            ];
        }

        $code = trim((string)$row['kpi_code']);
        $groupName = trim((string)$row['kpi_group']);
        $description = trim((string)($row['kpi_description'] ?? $code));
        $score = (int)$row['score'];
        $groups[$groupKey]['all_scores'][] = $score;

        if (($row['section'] ?? '') === 'Section 1' || str_starts_with($code, 'S1.')) {
            $groups[$groupKey]['section1_scores'][$code] = $score;
            continue;
        }

        $groupName = $groupName !== '' ? $groupName : 'Other KPI';
        if (!isset($groups[$groupKey]['section2_groups'][$groupName])) {
            $groups[$groupKey]['section2_groups'][$groupName] = [];
        }
        $groups[$groupKey]['section2_groups'][$groupName][] = [
            'code' => $code,
            'description' => $description,
            'score' => $score,
        ];
    }

    $results = [];

    foreach ($groups as $entry) {
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
                'score' => round(($avg / 5) * 100, 2),
                'avg_5_scale' => round($avg, 4),
                'weight' => $weight,
                'weighted_score_5' => round($weighted, 4),
                'target' => KPI_TARGET_PERCENT,
                'items' => $items,
            ];
        }

        usort($categoryScores, fn($a, $b) => strcmp($a['category'], $b['category']));

        $finalScore5 = round($section1Weighted + $section2Weighted, 4);
        $finalPercent = round(($finalScore5 / 5) * 100, 2);

        $overallAverage5 = count($entry['all_scores']) > 0
            ? round(array_sum($entry['all_scores']) / count($entry['all_scores']), 4)
            : 0.0;

        $overallPercentage = round(($overallAverage5 / 5) * 100, 2);

        $results[] = [
            'period' => $entry['period'],
            'period_date' => $entry['period_date'],
            'year' => $entry['year'],
            'staff' => $entry['staff'],
            'section1_average_5' => round($section1Average, 4),
            'section1_score_5' => round($section1Weighted, 4),
            'section2_score_5' => round($section2Weighted, 4),
            'final_score_5' => $finalScore5,
            'final_percentage' => $finalPercent,
            'overall_average_5' => $overallAverage5,
            'overall_percentage' => $overallPercentage,
            'performance_level' => classifyPerformance($overallPercentage),
            'category_scores' => $categoryScores,
            'comments' => $entry['comments'],
            'training' => $entry['training'],
        ];
    }

    usort($results, function ($a, $b) {
        if ($a['period'] === $b['period']) {
            return strcmp($a['staff']['name'], $b['staff']['name']);
        }
        return strcmp($a['period'], $b['period']);
    });

    return $results;
}

// ------------------------------------------------------------
// LATEST SNAPSHOTS & METRICS
// ------------------------------------------------------------
function getLatestStaffSnapshots(array $periodStaff, string $yearFilter, string $departmentFilter, string $categoryFilter): array
{
    $grouped = [];

    foreach ($periodStaff as $row) {
        if ($yearFilter !== '' && (string)$row['year'] !== (string)$yearFilter) continue;
        if ($departmentFilter !== 'All Departments' && $row['staff']['department'] !== $departmentFilter) continue;
        if ($categoryFilter !== 'All Categories' && !categoryExists($row, $categoryFilter)) continue;

        $staffId = (int)$row['staff']['id'];
        $grouped[$staffId][] = $row;
    }

    $snapshots = [];
    foreach ($grouped as $rows) {
        usort($rows, fn($a, $b) => strcmp($a['period'], $b['period']));
        $latest = end($rows);

        $history = [];
        foreach ($rows as $item) {
            $history[] = metricHistoryEntry($item, $categoryFilter);
        }

        $recentScores = array_map(fn($item) => $item['score_5'], array_slice($history, -3));
        $trendDelta = calculateTrendDelta($recentScores);
        $trend = classifyTrend($trendDelta);

        $previousHistoryEntry = count($history) >= 2 ? $history[count($history) - 2] : null;
        $latestMetric = metricForRow($latest, $categoryFilter);

        $filteredCategories = $categoryFilter === 'All Categories'
            ? $latest['category_scores']
            : array_values(array_filter($latest['category_scores'], fn($cat) => $cat['category'] === $categoryFilter));

        $snapshots[] = [
            'staff' => $latest['staff'],
            'period' => $latest['period'],
            'year' => $latest['year'],
            'current_score_5' => round($latestMetric['score_5'], 2),
            'current_percentage' => round($latestMetric['percentage'], 2),
            'previous_score_5' => $previousHistoryEntry ? round($previousHistoryEntry['score_5'], 2) : null,
            'previous_percentage' => $previousHistoryEntry ? round($previousHistoryEntry['percentage'], 2) : null,
            'trend_delta' => round($trendDelta, 2),
            'trend' => $trend,
            'performance_level' => classifyPerformance($latestMetric['percentage']),
            'category_scores' => $filteredCategories,
            'comments' => $latest['comments'],
            'training' => $latest['training'],
            'history' => $history,
        ];
    }

    usort($snapshots, fn($a, $b) => [$b['current_percentage'], $a['staff']['name']] <=> [$a['current_percentage'], $b['staff']['name']]);
    return $snapshots;
}

function metricForRow(array $row, string $categoryFilter): array
{
    if ($categoryFilter === 'All Categories') {
        return [
            'score_5' => (float)$row['overall_average_5'],
            'percentage' => (float)$row['overall_percentage'],
        ];
    }
    if ($categoryFilter === 'Competency') {
        $score5 = (float)$row['section1_average_5'];
        return [
            'score_5' => round($score5, 4),
            'percentage' => round(($score5 / 5) * 100, 2),
        ];
    }
    foreach ($row['category_scores'] as $category) {
        if ($category['category'] === $categoryFilter) {
            return [
                'score_5' => (float)$category['avg_5_scale'],
                'percentage' => (float)$category['score'],
            ];
        }
    }
    return ['score_5' => 0.0, 'percentage' => 0.0];
}

function metricHistoryEntry(array $row, string $categoryFilter): array
{
    $metric = metricForRow($row, $categoryFilter);
    return [
        'period' => $row['period'],
        'score_5' => round($metric['score_5'], 2),
        'percentage' => round($metric['percentage'], 2),
    ];
}

// ------------------------------------------------------------
// DASHBOARD & CHART DATA BUILDERS
// ------------------------------------------------------------
function getDashboardPayload(mysqli $conn, array $filters): array
{
    $records = fetchKpiRecords($conn);
    $periodType = normalizePeriod($filters['period'] ?? 'Monthly');
    $categoryFilter = $filters['kpi_category'] ?? 'All Categories';
    $yearFilter = $filters['year'] ?? '';
    $departmentFilter = $filters['department'] ?? 'All Departments';

    $periodStaff = buildPeriodStaffScores($records, $periodType);
    $timeline = buildTimelineRows($periodStaff, $yearFilter, $departmentFilter, $categoryFilter);

    if ($yearFilter !== '') {
    $periodStaff = array_filter($periodStaff, function ($row) use ($yearFilter) {
        return (string)($row['year'] ?? '') === (string)$yearFilter;
    });
}

    $staffLatest = getLatestStaffSnapshots($periodStaff, $yearFilter, $departmentFilter, $categoryFilter);

    $staffLatestForDropdown = array_values(array_filter($staffLatest, function ($row) use ($yearFilter) {
    if ($yearFilter === '') return true;
    return (string)($row['year'] ?? '') === (string)$yearFilter;
}));
    $summary = buildSummary($staffLatest);
    $deptStats = buildDepartmentStats($staffLatest);
    usort($deptStats, fn($a, $b) => $b['score'] <=> $a['score']);

    $highRiskDepartments = array_values(array_filter($deptStats, fn($row) => $row['risk_band'] === 'High'));
    $moderateRiskDepartments = array_values(array_filter($deptStats, fn($row) => $row['risk_band'] === 'Moderate'));

    usort($highRiskDepartments, function ($a, $b) {
        if ((int)$a['at_risk'] === (int)$b['at_risk']) {
            return (float)$a['score'] <=> (float)$b['score'];
        }
        return (int)$b['at_risk'] <=> (int)$a['at_risk'];
    });
    usort($moderateRiskDepartments, fn($a, $b) => (float)$a['score'] <=> (float)$b['score']);

    return [
        'filters' => [
            'year' => $yearFilter,
            'department' => $departmentFilter,
            'kpi_category' => $categoryFilter,
            'period' => $periodType,
            'available_departments' => getDepartmentOptions($records),
            'available_categories' => getCategoryOptions($records),
            'available_years' => getYearOptions($records),
        ],
        'summary' => $summary,
        'insight' => buildTrendNarrative($timeline, $periodType, $categoryFilter),
        'high_risk_departments' => array_values(array_slice($highRiskDepartments, 0, 3)),
        'moderate_risk_departments' => array_values(array_slice($moderateRiskDepartments, 0, 3)),
        'performance_trend' => buildForecastTimeline($timeline),
        'performance_distribution' => distributionFromStaff($staffLatest),
        'trend_distribution' => trendDistributionFromStaff($staffLatest),
        'department_comparison' => array_map(fn($row) => [
            'department' => $row['department'],
            'score' => $row['score'],
            'top_performers' => $row['top_performers'],
            'at_risk' => $row['at_risk'],
            'trend' => $row['trend'],
        ], $deptStats),
        'department_stats' => array_map(fn($row) => [
            'department' => $row['department'],
            'score' => $row['score'],
            'staff_count' => $row['staff_count'],
            'top_performers' => $row['top_performers'],
            'at_risk' => $row['at_risk'],
            'trend' => $row['trend'],
            'risk_band' => $row['risk_band'],
        ], $deptStats),
        'kpi_vs_target' => buildKpiVsTarget($staffLatest, $categoryFilter),
        'heatmap' => buildHeatmap($periodStaff, $yearFilter, $departmentFilter, $categoryFilter),
        'risk_histogram' => buildRiskHistogram($staffLatest),
        'at_risk_staff' => buildAtRiskStaff($staffLatest),
        'suggestions' => buildSuggestions($staffLatest),
        'staff_snapshot_list' => array_map(fn($row) => [
            'id' => $row['staff']['id'],
            'name' => $row['staff']['name'],
            'staff_code' => $row['staff']['staff_code'] ?? '',
            'department' => $row['staff']['department'],
            'position' => $row['staff']['position'] ?? '',
            'score' => $row['current_percentage'],
            'performance_level' => $row['performance_level'],
            'trend' => $row['trend'],
            'year' => $row['year'],
        ], $staffLatest),
    ];
}

function buildTimelineRows(array $periodStaff, string $yearFilter, string $departmentFilter, string $categoryFilter): array
{
    $grouped = [];
    foreach ($periodStaff as $row) {
        if ($yearFilter !== '' && (string)$row['year'] !== (string)$yearFilter) continue;
        if ($departmentFilter !== 'All Departments' && $row['staff']['department'] !== $departmentFilter) continue;
        if ($categoryFilter !== 'All Categories' && !categoryExists($row, $categoryFilter)) continue;

        $period = $row['period'];
        if (!isset($grouped[$period])) $grouped[$period] = [];
        $grouped[$period][] = metricForRow($row, $categoryFilter)['percentage'];
    }
    ksort($grouped);
    $rows = [];
    foreach ($grouped as $period => $scores) {
        $avg = count($scores) ? array_sum($scores) / count($scores) : 0.0;
        $rows[] = ['period' => $period, 'score' => round($avg, 2), 'target' => KPI_TARGET_PERCENT, 'atRisk' => $avg < 60];
    }
    return $rows;
}

function getYearOptions(array $records): array
{
    $years = [];
    foreach ($records as $row) {
        if (!empty($row['year'])) {
            $years[(string)$row['year']] = true;
        }
    }
    $list = array_keys($years);
    rsort($list);
    return $list;
}

function buildForecastTimeline(array $timeline): array
{
    if (count($timeline) === 0) return [];
    $result = [];
    $scores5 = [];
    foreach ($timeline as $row) {
        $score5 = round(((float)$row['score'] / 100) * 5, 4);
        $scores5[] = $score5;
        $result[] = [
            'period' => $row['period'],
            'actual' => round((float)$row['score'], 2),
            'target' => round((float)$row['target'], 2),
            'forecast' => null,
            'is_forecast' => false,
            'risk' => ((float)$row['score'] < KPI_TARGET_PERCENT),
        ];
    }
    $recent5 = array_slice($scores5, -3);
    $delta5 = calculateTrendDelta($recent5);
    $lastRow = end($timeline);
    $lastScore5 = round(((float)$lastRow['score'] / 100) * 5, 4);
    $adjustedDelta5 = max(-0.25, min(0.25, $delta5));
    $forecast1_5 = max(0, min(5, round($lastScore5 + ($adjustedDelta5 * 0.6), 4)));
    $forecast2_5 = max(0, min(5, round($forecast1_5 + ($adjustedDelta5 * 0.4), 4)));
    $forecast1 = round(($forecast1_5 / 5) * 100, 2);
    $forecast2 = round(($forecast2_5 / 5) * 100, 2);
    $lastPeriod = (string)$lastRow['period'];
    $futurePeriods = buildFuturePeriods($lastPeriod, 2);
    $result[] = ['period' => $futurePeriods[0], 'actual' => null, 'target' => KPI_TARGET_PERCENT, 'forecast' => $forecast1, 'is_forecast' => true, 'risk' => ($forecast1 < KPI_TARGET_PERCENT)];
    $result[] = ['period' => $futurePeriods[1], 'actual' => null, 'target' => KPI_TARGET_PERCENT, 'forecast' => $forecast2, 'is_forecast' => true, 'risk' => ($forecast2 < KPI_TARGET_PERCENT)];
    return $result;
}

function buildFuturePeriods(string $lastPeriod, int $count = 2): array
{
    $periods = [];
    if (preg_match('/^\d{4}-\d{2}$/', $lastPeriod)) {
        $date = DateTime::createFromFormat('Y-m', $lastPeriod);
        if ($date) {
            for ($i = 1; $i <= $count; $i++) {
                $next = clone $date;
                $next->modify("+{$i} month");
                $periods[] = $next->format('Y-m');
            }
            return $periods;
        }
    }
    if (preg_match('/^\d{4}$/', $lastPeriod)) {
        $year = (int)$lastPeriod;
        for ($i = 1; $i <= $count; $i++) $periods[] = (string)($year + $i);
        return $periods;
    }
    for ($i = 1; $i <= $count; $i++) $periods[] = 'Forecast ' . $i;
    return $periods;
}

function buildSummary(array $staffLatest): array
{
    $total = count($staffLatest);
    $avg = $total ? array_sum(array_column($staffLatest, 'current_percentage')) / $total : 0.0;
    $departments = [];
    $top = 0;
    $atRisk = 0;
    $improving = 0;
    foreach ($staffLatest as $row) {
        $departments[$row['staff']['department']] = true;
        if ($row['performance_level'] === 'top') $top++;
        if (in_array($row['performance_level'], ['critical', 'at-risk'], true)) $atRisk++;
        if ($row['trend'] === 'up') $improving++;
    }
    return [
        'total_staff' => $total,
        'avg_kpi' => round($avg, 2),
        'improving' => $improving,
        'departments' => count($departments),
        'top_performers' => $top,
        'at_risk' => $atRisk,
    ];
}

function buildDepartmentStats(array $staffLatest): array
{
    $grouped = [];
    foreach ($staffLatest as $row) {
        $dept = $row['staff']['department'] ?: 'Unassigned';
        $grouped[$dept][] = $row;
    }
    $stats = [];
    foreach ($grouped as $dept => $rows) {
        $avg = array_sum(array_column($rows, 'current_percentage')) / max(count($rows), 1);
        $top = count(array_filter($rows, fn($r) => $r['performance_level'] === 'top'));
        $atRisk = count(array_filter($rows, fn($r) => in_array($r['performance_level'], ['critical', 'at-risk'], true)));
        $trendCounts = ['up' => 0, 'stable' => 0, 'down' => 0];
        foreach ($rows as $r) $trendCounts[$r['trend']]++;
        arsort($trendCounts);
        $dominantTrend = array_key_first($trendCounts);
        $riskBand = 'Low';
        if ($avg < 50) {
            $riskBand = 'High';
        } elseif ($avg < 75) {
            $riskBand = 'Moderate';
        }
        $stats[] = [
            'department' => $dept,
            'score' => round($avg, 2),
            'staff_count' => count($rows),
            'top_performers' => $top,
            'at_risk' => $atRisk,
            'trend' => trendLabel($dominantTrend),
            'risk_band' => $riskBand,
        ];
    }
    return $stats;
}

function buildKpiVsTarget(array $staffLatest, string $categoryFilter): array
{
    $grouped = [];
    foreach ($staffLatest as $row) {
        if ($categoryFilter === 'All Categories') {
            foreach ($row['category_scores'] as $cat) $grouped[$cat['category']][] = $cat['score'];
            continue;
        }
        if ($categoryFilter === 'Competency') {
            $grouped['Competency'][] = $row['current_percentage'];
            continue;
        }
        foreach ($row['category_scores'] as $cat) {
            if ($cat['category'] === $categoryFilter) $grouped[$cat['category']][] = $cat['score'];
        }
    }
    $result = [];
    foreach ($grouped as $cat => $scores) {
        if (count($scores) === 0) continue;
        $avg = array_sum($scores) / count($scores);
        $result[] = ['category' => $cat, 'actual' => round($avg, 2), 'target' => KPI_TARGET_PERCENT, 'gap' => round($avg - KPI_TARGET_PERCENT, 2)];
    }
    usort($result, fn($a, $b) => $a['actual'] <=> $b['actual']);
    return $result;
}

function buildHeatmap(array $periodStaff, string $yearFilter, string $departmentFilter, string $categoryFilter): array
{
    $matrix = [];
    foreach ($periodStaff as $row) {
        if ($yearFilter !== '' && (string)$row['year'] !== (string)$yearFilter) continue;
        if ($departmentFilter !== 'All Departments' && $row['staff']['department'] !== $departmentFilter) continue;
        if ($categoryFilter !== 'All Categories' && !categoryExists($row, $categoryFilter)) continue;
        $period = $row['period'];
        $dept = $row['staff']['department'] ?: 'Unassigned';
        $matrix[$period][$dept][] = metricForRow($row, $categoryFilter)['percentage'];
    }
    ksort($matrix);
    $rows = [];
    foreach ($matrix as $period => $departments) {
        $row = ['period' => $period];
        ksort($departments);
        foreach ($departments as $dept => $scores) {
            $row[$dept] = round(array_sum($scores) / max(count($scores), 1), 2);
        }
        $rows[] = $row;
    }
    return $rows;
}

function buildRiskHistogram(array $staffLatest): array
{
    $bins = ['0-39' => 0, '40-49' => 0, '50-69' => 0, '70-89' => 0, '90-100' => 0];
    foreach ($staffLatest as $row) {
        $score = $row['current_percentage'];
        if ($score < 40) $bins['0-39']++;
        elseif ($score < 50) $bins['40-49']++;
        elseif ($score < 70) $bins['50-69']++;
        elseif ($score < 90) $bins['70-89']++;
        else $bins['90-100']++;
    }
    $out = [];
    foreach ($bins as $range => $count) $out[] = ['range' => $range, 'count' => $count];
    return $out;
}

function buildAtRiskStaff(array $staffLatest): array
{
    $items = array_values(array_filter($staffLatest, fn($r) => in_array($r['performance_level'], ['critical', 'at-risk'], true)));
    usort($items, fn($a, $b) => $a['current_percentage'] <=> $b['current_percentage']);
    return array_map(fn($r) => [
        'name' => $r['staff']['name'],
        'department' => $r['staff']['department'],
        'score' => $r['current_percentage'],
        'trend' => trendLabel($r['trend']),
        'action' => recommendedAction($r['performance_level'], $r['trend']),
    ], array_slice($items, 0, 8));
}

function buildSuggestions(array $staffLatest): array
{
    $top = array_values(array_filter($staffLatest, fn($r) => $r['performance_level'] === 'top'));
    $avg = array_values(array_filter($staffLatest, fn($r) => $r['performance_level'] === 'average'));
    usort($top, fn($a, $b) => $b['current_percentage'] <=> $a['current_percentage']);
    usort($avg, fn($a, $b) => $b['current_percentage'] <=> $a['current_percentage']);
    if (count($top) < 2) $top = array_slice($staffLatest, 0, 2);
    return ['top_pair' => array_slice($top, 0, 2), 'average_pair' => array_slice($avg, 0, 2)];
}

function distributionFromStaff(array $staffLatest): array
{
    $dist = ['top' => 0, 'good' => 0, 'average' => 0, 'critical' => 0, 'at-risk' => 0];
    foreach ($staffLatest as $r) $dist[$r['performance_level']]++;
    return $dist;
}

function trendDistributionFromStaff(array $staffLatest): array
{
    $dist = ['up' => 0, 'stable' => 0, 'down' => 0];
    foreach ($staffLatest as $r) $dist[$r['trend']]++;
    return $dist;
}

function buildTrendNarrative(array $timeline, string $periodType, string $categoryFilter): string
{
    if (count($timeline) === 0) return 'No KPI records matched the current filters.';
    $last = end($timeline);
    $previous = count($timeline) >= 2 ? $timeline[count($timeline) - 2] : null;
    $direction = 'stable';
    if ($previous) {
        $delta5 = round(($last['score'] / 20) - ($previous['score'] / 20), 2);
        $direction = classifyTrend($delta5);
    }
    $scope = $categoryFilter === 'All Categories' ? 'overall KPI performance' : $categoryFilter . ' performance';
    $rangeLabel = $periodType === 'Monthly' ? 'month' : 'year';
    $riskText = $last['score'] < 60 ? 'The latest period is in the at-risk band.' : ($last['score'] < 80 ? 'The latest period is below the 80% target.' : 'The latest period is meeting the 80% target.');
    return sprintf('The latest %s for %s is %.2f%%. Trend across recent periods is %s using the ±0.2 threshold on the 1–5 scale. %s', $rangeLabel, $scope, $last['score'], trendLabel($direction), $riskText);
}

// ------------------------------------------------------------
// STAFF SEARCH & COMPARE
// ------------------------------------------------------------
function searchStaff(mysqli $conn, array $filters): array
{
    $records = fetchKpiRecords($conn);
    $periodStaff = buildPeriodStaffScores($records, 'Yearly');
    $staffLatest = getLatestStaffSnapshots($periodStaff, '', $filters['department'] ?? 'All Departments', 'All Categories');
    $query = mb_strtolower($filters['query'] ?? '');
    $performance = normalizePerformanceFilter($filters['performance'] ?? 'All Performance');
    $excludeId = (int)($filters['exclude_id'] ?? 0);
    $items = array_values(array_filter($staffLatest, function ($staff) use ($query, $performance, $excludeId) {
        if ($excludeId > 0 && (int)$staff['staff']['id'] === $excludeId) return false;
        if ($query !== '') {
            $haystack = mb_strtolower(($staff['staff']['name'] ?? '') . ' ' . ($staff['staff']['department'] ?? '') . ' ' . ($staff['staff']['staff_code'] ?? ''));
            if (mb_strpos($haystack, $query) === false) return false;
        }
        if ($performance !== 'all' && $staff['performance_level'] !== $performance) return false;
        return true;
    }));
    usort($items, fn($a, $b) => [$b['current_percentage'], $a['staff']['name']] <=> [$a['current_percentage'], $b['staff']['name']]);
    return [
        'items' => array_map(fn($staff) => [
            'id' => $staff['staff']['id'],
            'name' => $staff['staff']['name'],
            'staff_code' => $staff['staff']['staff_code'],
            'department' => $staff['staff']['department'],
            'position' => $staff['staff']['position'],
            'avatar' => $staff['staff']['profile_photo'],
            'score' => $staff['current_percentage'],
            'performance_level' => $staff['performance_level'],
            'trend' => $staff['trend'],
        ], array_slice($items, 0, 12)),
    ];
}

function getComparePayload(mysqli $conn, array $params): array
{
    if (($params['staff1'] ?? 0) <= 0 || ($params['staff2'] ?? 0) <= 0) {
        return ['error' => 'Please select two staff members.'];
    }
    $records = fetchKpiRecords($conn);
    $yearlyPeriodStaff = buildPeriodStaffScores($records, 'Yearly');
    $monthlyPeriodStaff = buildPeriodStaffScores($records, 'Monthly');
    $staffLatest = getLatestStaffSnapshots($yearlyPeriodStaff, '', 'All Departments', 'All Categories');
    $staffIndex = [];
    foreach ($staffLatest as $row) $staffIndex[(int)$row['staff']['id']] = $row;
    if (!isset($staffIndex[$params['staff1']]) || !isset($staffIndex[$params['staff2']])) {
        return ['error' => 'Selected staff records were not found.'];
    }
    $staff1 = $staffIndex[$params['staff1']];
    $staff2 = $staffIndex[$params['staff2']];
    $focusPeriod = normalizePeriod($params['period'] ?? 'Yearly');
    $focusMap = $focusPeriod === 'Monthly' ? $monthlyPeriodStaff : $yearlyPeriodStaff;
    return [
        'staff1' => buildStaffComparisonCard($staff1, $yearlyPeriodStaff, $monthlyPeriodStaff),
        'staff2' => buildStaffComparisonCard($staff2, $yearlyPeriodStaff, $monthlyPeriodStaff),
        'radar_categories' => buildRadarCategories($staff1, $staff2),
        'trend_series' => buildComparisonTrendSeries($focusMap, (int)$staff1['staff']['id'], (int)$staff2['staff']['id'], $params['year'] ?? ''),
        'category_gap' => buildCategoryGapSeries($staff1, $staff2),
        'focus_period' => $focusPeriod,
        'insight' => buildComparisonNarrative($staff1, $staff2),
    ];
}

function buildStaffComparisonCard(array $staffLatest, array $yearlyPeriodStaff, array $monthlyPeriodStaff): array
{
    $staffId = (int)$staffLatest['staff']['id'];
    return [
        'staff' => $staffLatest['staff'],
        'current_percentage' => $staffLatest['current_percentage'],
        'current_score_5' => $staffLatest['current_score_5'],
        'previous_score_5' => $staffLatest['previous_score_5'],
        'trend_delta' => $staffLatest['trend_delta'],
        'trend' => $staffLatest['trend'],
        'performance_level' => $staffLatest['performance_level'],
        'risk_level' => comparisonRiskLabel($staffLatest['current_percentage']),
        'stability_score' => calculateStabilityScore($staffLatest['history']),
        'history_yearly' => extractHistoryForStaff($yearlyPeriodStaff, $staffId),
        'history_monthly' => extractHistoryForStaff($monthlyPeriodStaff, $staffId),
        'category_scores' => $staffLatest['category_scores'],
        'comments' => $staffLatest['comments'],
        'training' => $staffLatest['training'],
    ];
}

function buildRadarCategories(array $staff1, array $staff2): array
{
    $categories = [];
    foreach ($staff1['category_scores'] as $cat) $categories[$cat['category']]['staff1'] = $cat['score'];
    foreach ($staff2['category_scores'] as $cat) $categories[$cat['category']]['staff2'] = $cat['score'];
    $rows = [];
    foreach ($categories as $cat => $vals) {
        $rows[] = [
            'category' => $cat,
            'staff1' => round((float)($vals['staff1'] ?? 0), 2),
            'staff2' => round((float)($vals['staff2'] ?? 0), 2),
            'target' => KPI_TARGET_PERCENT,
        ];
    }
    usort($rows, fn($a, $b) => strcmp($a['category'], $b['category']));
    return $rows;
}

function buildComparisonTrendSeries(array $periodStaff, int $staff1Id, int $staff2Id, string $yearFilter): array
{
    $byPeriod = [];
    foreach ($periodStaff as $row) {
        if ($yearFilter !== '' && (string)$row['year'] !== (string)$yearFilter) continue;
        $id = (int)$row['staff']['id'];
        if ($id !== $staff1Id && $id !== $staff2Id) continue;
        $period = $row['period'];
        if (!isset($byPeriod[$period])) $byPeriod[$period] = ['period' => $period, 'staff1' => null, 'staff2' => null, 'target' => KPI_TARGET_PERCENT];
        if ($id === $staff1Id) $byPeriod[$period]['staff1'] = $row['overall_percentage'];
        if ($id === $staff2Id) $byPeriod[$period]['staff2'] = $row['overall_percentage'];
    }
    ksort($byPeriod);
    return array_values($byPeriod);
}

function buildCategoryGapSeries(array $staff1, array $staff2): array
{
    $rows = buildRadarCategories($staff1, $staff2);
    foreach ($rows as &$row) $row['gap'] = round($row['staff1'] - $row['staff2'], 2);
    unset($row);
    return $rows;
}

function buildComparisonNarrative(array $staff1, array $staff2): string
{
    $primary = $staff1['stability_score'] >= $staff2['stability_score'] ? $staff1 : $staff2;
    $secondary = $primary['staff']['id'] === $staff1['staff']['id'] ? $staff2 : $staff1;
    return sprintf('%s currently records %.2f%% with %s movement across recent periods. %s records %.2f%%. Based on the latest three periods and the ±0.2 stability rule, %s appears more reliable for sustained delivery, while %s should be reviewed more closely in the weaker KPI categories.',
        $primary['staff']['name'], $primary['current_percentage'], strtolower(trendLabel($primary['trend'])),
        $secondary['staff']['name'], $secondary['current_percentage'],
        $primary['staff']['name'], $secondary['staff']['name']);
}

function extractHistoryForStaff(array $periodStaff, int $staffId): array
{
    $rows = [];
    foreach ($periodStaff as $row) {
        if ((int)$row['staff']['id'] !== $staffId) continue;
        $rows[] = ['period' => $row['period'], 'score_5' => round($row['overall_average_5'], 2), 'percentage' => $row['overall_percentage']];
    }
    usort($rows, fn($a, $b) => strcmp($a['period'], $b['period']));
    return $rows;
}

// ------------------------------------------------------------
// UTILITY FUNCTIONS
// ------------------------------------------------------------
function calculateTrendDelta(array $scores): float
{
    $recent = array_values(array_slice($scores, -3));
    if (count($recent) < 2) return 0.0;
    return round(end($recent) - $recent[0], 4);
}

function calculateStabilityScore(array $history): int
{
    if (count($history) < 2) return 100;
    $scores = array_column($history, 'score_5');
    $deltas = [];
    for ($i = 1; $i < count($scores); $i++) $deltas[] = abs($scores[$i] - $scores[$i-1]);
    $avgDelta = count($deltas) ? array_sum($deltas) / count($deltas) : 0.0;
    return max(0, min(100, (int)round(100 - ($avgDelta * 100))));
}

function normalizePeriod(string $period): string
{
    return strtolower(trim($period)) === 'yearly' ? 'Yearly' : 'Monthly';
}

function normalizePerformanceFilter(string $performance): string
{
    $performance = strtolower(trim($performance));
    return match ($performance) {
        'top', 'good', 'average', 'critical', 'at-risk' => $performance,
        default => 'all',
    };
}

function classifyPerformance(float $percentage): string
{
    if ($percentage >= 85) return 'top';
    if ($percentage >= 70) return 'good';
    if ($percentage >= 50) return 'average';
    if ($percentage >= 40) return 'critical';
    return 'at-risk';
}

function classifyTrend(float $delta): string
{
    if ($delta > TREND_STABLE_DELTA) return 'up';
    if ($delta < -TREND_STABLE_DELTA) return 'down';
    return 'stable';
}

function trendLabel(string $trend): string
{
    return match ($trend) {
        'up' => 'Improving',
        'down' => 'Declining',
        default => 'Stable',
    };
}

function comparisonRiskLabel(float $percentage): string
{
    return match (true) {
        $percentage >= 90 => 'Low',
        $percentage >= 70 => 'Moderate',
        $percentage >= 50 => 'Elevated',
        $percentage >= 40 => 'High',
        default => 'Very High',
    };
}

function categoryExists(array $row, string $categoryFilter): bool
{
    if ($categoryFilter === 'Competency') return true;
    foreach ($row['category_scores'] as $category) {
        if ($category['category'] === $categoryFilter) return true;
    }
    return false;
}

function recommendedAction(string $performanceLevel, string $trend): string
{
    if ($performanceLevel === 'at-risk') return 'Immediate coaching plan';
    if ($performanceLevel === 'critical' && $trend === 'down') return 'Targeted intervention';
    if ($performanceLevel === 'critical') return 'Close monthly review';
    return 'Monitor and support';
}

function normalizeAvatarPath(string $path): string
{
    return trim($path) !== '' ? trim($path) : './asset/images/staff/default-profile.jpg';
}

function getDepartmentOptions(array $records): array
{
    $departments = [];
    foreach ($records as $row) $departments[$row['department'] ?: 'Unassigned'] = true;
    $list = array_keys($departments);
    sort($list);
    return $list;
}

function getCategoryOptions(array $records): array
{
    $categories = ['Competency' => true];
    foreach ($records as $row) {
        if (!empty($row['kpi_group'])) $categories[$row['kpi_group']] = true;
    }
    unset($categories['KPI_Group']);
    $list = array_keys($categories);
    sort($list);
    return $list;
}
?>