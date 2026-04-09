<?php
/* ── data.php ── */

// ══════════════════════════════════════════
// 1. FILTER VARIABLES
// ══════════════════════════════════════════

// $selectedYear is set in overview.php before this file is included.
// Default to 2025 if not set so nothing prints null.
if (!isset($selectedYear) || !$selectedYear) {
    $selectedYear = 2025;
}

$podiumYear         = $selectedYear;
$statYear           = 2025;   // Stat cards are always fixed to 2025
$prevYear           = $podiumYear - 1;
$currentYearFilter  = "= $podiumYear";

// ══════════════════════════════════════════
// 2. MAIN STAFF + KPI QUERY
// Fetches stat score (fixed 2025), podium score (filtered year),
// and previous year score for trend/drop analysis.
// Joins on full_name to match how kpi_data stores names.
// ══════════════════════════════════════════

$sql = "
    SELECT
        s.id,
        s.full_name       AS name,
        s.staff_code      AS staffId,
        s.department      AS dept,
        s.profile_photo   AS avatar,

        -- Fixed 2025 score used for stat cards and level badges
        ROUND(COALESCE(
            (AVG(CASE WHEN YEAR(k.Date) = $statYear THEN k.Score END) / 5) * 100
        , 0), 1) AS stat_score,

        -- Dynamic filtered year score used for podium and attention section
        ROUND(COALESCE(
            (AVG(CASE WHEN YEAR(k.Date) $currentYearFilter THEN k.Score END) / 5) * 100
        , 0), 1) AS curr_score,

        -- Previous year score used for trend comparison
        ROUND(COALESCE(
            (AVG(CASE WHEN YEAR(k.Date) = $prevYear THEN k.Score END) / 5) * 100
        , 0), 1) AS last_year_score

    FROM staff s
    LEFT JOIN kpi_data k ON k.Name = s.full_name
    GROUP BY s.id, s.full_name, s.staff_code, s.department, s.profile_photo
    ORDER BY s.full_name ASC
";

$result    = $conn->query($sql);
$staffData = [];
$atRisk    = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $sScore = (float) $row['stat_score'];
        $pScore = (float) $row['curr_score'];
        $prev   = (float) $row['last_year_score'];

        // ── Level badge based on fixed 2025 score ──
        if      ($sScore >= 85) { $level = 'top';      }
        elseif  ($sScore >= 70) { $level = 'good';     }
        elseif  ($sScore >= 50) { $level = 'average';  }
        elseif  ($sScore >  0)  { $level = 'at-risk';  }
        else                    { $level = 'critical'; }

        // ── Build staff record ──
        $item = [
            'id'           => (int) $row['id'],
            'name'         => $row['name'],
            'staffId'      => $row['staffId'],
            'dept'         => $row['dept'],
            'avatar'       => $row['avatar'],
            'score'        => $sScore,   // used by stat cards and sorting
            'podium_score' => $pScore,   // used by podium display
            'prev_score'   => $prev,     // used by attention section header row
            'score2024'    => $prev,     // kept for any template references
            'level'        => $level,
            'trend' => ($pScore > 0 && $prev == 0) ? 'up' 
                    : ($pScore >= $prev ? 'up' : 'down'),
            'diff'  => ($prev == 0 && $pScore > 0) 
                    ? round($pScore, 1)     
                    : round($pScore - $prev, 1),
            'group_details' => []       
        ];

        // ── Attention Required: flag critical (no data) and at-risk (< 50%) ──
        if ($pScore == 0 || $pScore < 50) {
            $item['level'] = ($pScore == 0) ? 'critical' : 'at-risk';

            // For critical staff (no data this year) analyse the previous year instead
            $yearToAnalyze = $podiumYear;
            if ($pScore == 0) {
                $checkSql = "SELECT YEAR(Date) as yr FROM kpi_data 
                            WHERE Name = ? AND Score > 0 
                            ORDER BY Date DESC LIMIT 1";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("s", $row['name']);
                $checkStmt->execute();
                $checkRes = $checkStmt->get_result()->fetch_assoc();
                $yearToAnalyze = $checkRes ? (int)$checkRes['yr'] : $prevYear;
                $checkStmt->close();
            }

            // Fetch weakest KPI groups — sorted ascending so worst appears first.
            // Joins kpi_data → kpi_master_list on KPI_Code = kpi_code.
            // kpi_master_list.kpi_group must not equal the header row value 'KPI_Group'.
            $gSql = "
                SELECT
                    m.kpi_group,
                    ROUND((AVG(k.Score) / 5) * 100, 1) AS avg_pct
                FROM kpi_data k
                INNER JOIN kpi_master_list m ON k.KPI_Code = m.kpi_code
                WHERE k.Name = ?
                  AND YEAR(k.Date) = ?
                  AND m.kpi_group != 'KPI_Group'
                GROUP BY m.kpi_group
                ORDER BY avg_pct ASC
                LIMIT 5
            ";

            $stmt = $conn->prepare($gSql);
            $stmt->bind_param("si", $row['name'], $yearToAnalyze);
            $stmt->execute();
            $gRes = $stmt->get_result();

            while ($g = $gRes->fetch_assoc()) {
                // Key name matches what overview.php JS expects:
                // staff.group_details.map(g => g.kpi_group)
                // staff.group_details.map(g => g.avg_pct)
                $item['group_details'][] = [
                    'kpi_group' => $g['kpi_group'],
                    'avg_pct'   => (float) $g['avg_pct']
                ];
            }

            $stmt->close();
            $atRisk[] = $item;
        }

        $staffData[] = $item;
    }
}

// ══════════════════════════════════════════
// 3. STAT CARD SUMMARIES  (fixed to 2025)
// ══════════════════════════════════════════

$totalStaff    = count($staffData);
$topPerformers = array_filter($staffData, fn($s) => $s['score'] >= 85);
$topCount      = count($topPerformers);

// Sort top performers descending for the bar display in the stat card
usort($topPerformers, fn($a, $b) => $b['score'] <=> $a['score']);
$topPerformers = array_values($topPerformers);

// ══════════════════════════════════════════
// 4. PODIUM  (uses dynamic filtered year)
// ══════════════════════════════════════════

$podiumList = array_values(array_filter($staffData, fn($s) => $s['podium_score'] > 0));
usort($podiumList, fn($a, $b) => $b['podium_score'] <=> $a['podium_score']);
$tops = array_slice($podiumList, 0, 3);

$podium = [
    'gold'   => $tops[0] ?? null,
    'silver' => $tops[1] ?? null,
    'bronze' => $tops[2] ?? null,
];

// ══════════════════════════════════════════
// 5. CRITICAL KPI GROUP CHART  (fixed 2025)
// Used by the "Critical KPI Group" stat card and bar chart.
// ══════════════════════════════════════════

$groupSql = "
    SELECT
        m.kpi_group  AS grp,
        ROUND((AVG(k.Score) / 5) * 100, 1) AS avg_pct
    FROM kpi_data k
    INNER JOIN kpi_master_list m ON k.KPI_Code = m.kpi_code
    WHERE m.kpi_group != 'KPI_Group'
      AND YEAR(k.Date) = 2025
    GROUP BY m.kpi_group
    ORDER BY avg_pct ASC
";

$groupResult = $conn->query($groupSql);
$groupLabels = [];
$groupScores = [];

while ($row = $groupResult->fetch_assoc()) {
    $groupLabels[] = $row['grp'];
    $groupScores[] = (float) $row['avg_pct'];
}

// ══════════════════════════════════════════
// 6. DEPARTMENT LIST  (for filters)
// ══════════════════════════════════════════

$departments = array_unique(array_column($staffData, 'dept'));
sort($departments);

// ══════════════════════════════════════════
// 7. DEPT DONUT CHART DATA
// Used by the "Total Staff" stat card donut.
// ══════════════════════════════════════════

$deptCounts = [];
$deptLabels = [];

foreach ($departments as $dept) {
    $count = count(array_filter($staffData, fn($s) => $s['dept'] === $dept));
    if ($count > 0) {
        $deptLabels[] = $dept;
        $deptCounts[] = $count;
    }
}

// ══════════════════════════════════════════
// 8. HEATMAP  (Department × KPI Group, fixed 2025)
// ══════════════════════════════════════════

$heatmapSql = "
    SELECT
        s.department,
        m.kpi_group,
        ROUND((AVG(k.Score) / 5) * 100, 1) AS avg_pct
    FROM kpi_data k
    INNER JOIN staff s          ON k.Name     = s.full_name
    INNER JOIN kpi_master_list m ON k.KPI_Code = m.kpi_code
    WHERE m.kpi_group != 'KPI_Group'
      AND YEAR(k.Date) = 2025
    GROUP BY s.department, m.kpi_group
";

$heatmapResult = $conn->query($heatmapSql);
$heatmapRaw    = [];
$hDepts        = [];
$hGroups       = [];

while ($r = $heatmapResult->fetch_assoc()) {
    if (!in_array($r['department'], $hDepts)) $hDepts[] = $r['department'];
    if (!in_array($r['kpi_group'],  $hGroups)) $hGroups[] = $r['kpi_group'];
    $heatmapRaw[$r['department']][$r['kpi_group']] = (float) $r['avg_pct'];
}

$heatmapSeries = [];

foreach ($hDepts as $d) {
    $pts = [];
    foreach ($hGroups as $g) {
        $pts[] = [
            'x' => $g,
            'y' => $heatmapRaw[$d][$g] ?? 0
        ];
    }
    $heatmapSeries[] = ['name' => $d, 'data' => $pts];
}

// Find best and worst department-group combinations
$bestDeptGroup = ['dept' => '', 'group' => '', 'score' => 0];
$worstDeptGroup = ['dept' => '', 'group' => '', 'score' => 100];
$deptAvgScores = [];
$groupAvgScores = [];

foreach ($heatmapRaw as $dept => $groups) {
    $deptSum = 0;
    $deptCount = 0;
    foreach ($groups as $group => $score) {
        if ($score > $bestDeptGroup['score']) {
            $bestDeptGroup = ['dept' => $dept, 'group' => $group, 'score' => $score];
        }
        if ($score < $worstDeptGroup['score'] && $score > 0) {
            $worstDeptGroup = ['dept' => $dept, 'group' => $group, 'score' => $score];
        }
        $deptSum += $score;
        $deptCount++;
        
        // For group average across departments
        $groupAvgScores[$group][] = $score;
    }
    $deptAvgScores[$dept] = $deptCount > 0 ? round($deptSum / $deptCount, 1) : 0;
}


// Compute average per KPI group across all departments
$groupInsights = [];
foreach ($groupAvgScores as $group => $scores) {
    $groupInsights[$group] = round(array_sum($scores) / count($scores), 1);
}
arsort($groupInsights); // highest first


if (empty($groupInsights)) {
    $heatmapInsight = "Insufficient KPI data for 2025 to generate insights.";
} else {
    $heatmapInsight = "Best: <strong>{$bestDeptGroup['dept']}</strong> → {$bestDeptGroup['group']} ({$bestDeptGroup['score']}%)<br>";
    $heatmapInsight .= "Worst: <strong>{$worstDeptGroup['dept']}</strong> → {$worstDeptGroup['group']} ({$worstDeptGroup['score']}%)<br>";
    $heatmapInsight .= "Top group: <strong>" . array_key_first($groupInsights) . "</strong> (" . reset($groupInsights) . "%)<br>";
    $heatmapInsight .= "Bottom group: <strong>" . array_key_last($groupInsights) . "</strong> (" . end($groupInsights) . "%)";

}

// ========== TARGET ACHIEVEMENT INSIGHT ==========
$speedoYear = isset($_GET['speedo_year']) ? (int)$_GET['speedo_year'] : 2025;
$speedoTarget = isset($_GET['speedo_target']) ? (int)$_GET['speedo_target'] : 80;

// $yearlyData is already defined in overview.php before include, but to be safe:
if (!isset($yearlyData)) {
    // fallback – compute yearlyData again or set default
    $yearlyData = ['2022'=>0, '2023'=>0, '2024'=>0, '2025'=>0];
}
$actualForYear = $yearlyData[$speedoYear] ?? 0;
$percentageOfTarget = ($speedoTarget > 0) ? round(($actualForYear / $speedoTarget) * 100) : 0;
$percentageOfTarget = min($percentageOfTarget, 100);

if ($percentageOfTarget >= 100) {
    $targetInsight = "Excellent! The actual average KPI for $speedoYear ($actualForYear%) meets or exceeds the target of $speedoTarget%. Keep up the great work.";
} elseif ($percentageOfTarget >= 80) {  
    $targetInsight = "Good progress. The actual average ($actualForYear%) is $percentageOfTarget% of the target ($speedoTarget%). A small push can help close the gap.";
} elseif ($percentageOfTarget >= 60) {
    $targetInsight = "Moderate performance. The team is at $percentageOfTarget% of the target. Focus on the weakest KPI groups shown in the heatmap above.";
} else {
    $targetInsight = "Needs attention. The actual average ($actualForYear%) is only $percentageOfTarget% of the target ($speedoTarget%). Consider reviewing department‑level breakdowns and providing additional training.";
}