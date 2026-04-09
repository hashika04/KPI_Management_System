<?php
/* ── data.php ──*/

// 1. Setup Filter Variables
// $selectedYear comes from overview.php (GET param)
$podiumYear = $selectedYear; 
$statYear = 2025; // Hardcoded fixed year for Stat Cards

// 2. Define Podium Score Logic
// If no year selected, average everything from 2022 onwards.
$podiumScoreSQL = $podiumYear 
    ? "AVG(CASE WHEN YEAR(k.Date) = $podiumYear THEN k.Score END)" 
    : "AVG(CASE WHEN YEAR(k.Date) >= 2022 THEN k.Score END)";

// 3. Updated SQL Query
$sql = "
    SELECT
        s.id,
        s.full_name   AS name,
        s.staff_code  AS staffId,
        s.department  AS dept,
        s.profile_photo AS avatar,
        -- Score for Stat Cards (Always 2025)
        ROUND(COALESCE((AVG(CASE WHEN YEAR(k.Date) = $statYear THEN k.Score END) / 5) * 100, 0), 1) AS stat_score,
        -- Score for Podium (Filtered by User)
        ROUND(COALESCE(($podiumScoreSQL / 5) * 100, 0), 1) AS podium_score
    FROM staff s
    LEFT JOIN kpi_data k ON k.Name = s.full_name
    GROUP BY s.id, s.full_name, s.staff_code, s.department, s.profile_photo
";

$result = $conn->query($sql);
$staffData = [];

while ($row = $result->fetch_assoc()) {
    $sScore = (float)$row['stat_score'];
    $pScore = (float)$row['podium_score'];

    // Classification (Always based on Stat Year 2025 for the Stat Cards)
    if ($sScore >= 85)      { $level = 'top'; } 
    elseif ($sScore >= 70)  { $level = 'good'; } 
    elseif ($sScore >= 50)  { $level = 'average'; } 
    elseif ($sScore > 0)    { $level = 'at-risk'; } 
    else                    { $level = 'critical'; }

    $staffData[] = [
        'id'           => (int)$row['id'],
        'name'         => $row['name'],
        'staffId'      => $row['staffId'],
        'dept'         => $row['dept'],
        'avatar'       => $row['avatar'] ?? '',
        'score'        => $sScore, // Used for Stat Cards / Level
        'podium_score' => $pScore, // Used for Podium sorting
        'level'        => $level,
    ];
}

/* ── Summary Stats (Fixed to 2025) ── */
// These variables feed your Stat Cards directly
$totalStaff  = count($staffData);
$topCount    = count(array_filter($staffData, fn($s) => $s['level'] === 'top'));
$atRiskCount = count(array_filter($staffData, fn($s) => in_array($s['level'], ['at-risk', 'critical'])));

/* ── Podium Logic (Dynamic Filter) ── */
// 1. Get staff who have a score in the selected period (Year or Overall)
$podiumList = array_values(array_filter($staffData, fn($s) => $s['podium_score'] > 0));

// 2. Sort them by the filtered podium score
usort($podiumList, fn($a, $b) => $b['podium_score'] <=> $a['podium_score']);

// 3. Slice the top 3
$tops = array_slice($podiumList, 0, 3);
$podium = [
    'gold'   => $tops[0] ?? null,
    'silver' => $tops[1] ?? null,
    'bronze' => $tops[2] ?? null,
];

/* ── Critical KPI Group Chart (Fixed to 2025) ── */
$groupSql = "
    SELECT
        m.kpi_group,
        ROUND((AVG(k.Score) / 5) * 100, 1) AS avg_pct
    FROM kpi_data k
    INNER JOIN kpi_master_list m ON k.KPI_Code = m.kpi_code
    WHERE YEAR(k.Date) = 2025
    GROUP BY m.kpi_group
    ORDER BY avg_pct ASC
";
 
/* ── Departments for filter dropdown ── */
$departments = array_unique(array_column($staffData, 'dept'));
sort($departments);

$deptSql = "SELECT department, COUNT(*) as count FROM staff GROUP BY department ORDER BY count DESC";
$deptResult = $conn->query($deptSql);
$deptLabels = [];
$deptCounts = [];

while ($row = $deptResult->fetch_assoc()) {
    $deptLabels[] = $row['department']; // Just the normal name
    $deptCounts[] = (int)$row['count']; 
}

$groupSql = "
    SELECT
        m.kpi_group                              AS grp,
        ROUND((AVG(k.Score) / 5) * 100, 1)      AS avg_pct
    FROM kpi_data k
    INNER JOIN kpi_master_list m ON k.KPI_Code = m.kpi_code
    WHERE m.kpi_group != 'KPI_Group'
      AND m.kpi_group IS NOT NULL
      AND YEAR(k.Date) = 2025
    GROUP BY m.kpi_group
    ORDER BY avg_pct ASC
";
 
$groupResult = $conn->query($groupSql);
$groupLabels = [];
$groupScores = [];
 
if ($groupResult) {
    while ($row = $groupResult->fetch_assoc()) {
        $groupLabels[] = $row['grp'];
        $groupScores[] = (float)$row['avg_pct'];
    }
}

$topPerformers = array_filter($staffData, fn($s) => $s['score'] >= 85);
$topCount = count($topPerformers);
$displayTops = array_slice($topPerformers, 0, 3);

/* ── Critical Category: department with lowest avg KPI ── */
$sqlCrit = "
    SELECT
        s.department,
        ROUND((AVG(k.Score)/5)*100, 1) AS dept_avg
    FROM staff s
    LEFT JOIN kpi_data k ON k.Name = s.full_name
    GROUP BY s.department
    ORDER BY dept_avg ASC
    LIMIT 1
";

/* ── At-risk staff (lowest scores first) ── */
$atRisk = array_values(
    array_filter($staffData, fn($s) => in_array($s['level'], ['at-risk', 'critical']))
);
usort($atRisk, fn($a, $b) => $a['score'] <=> $b['score']);

$critResult  = $conn->query($sqlCrit);
$critRow     = $critResult->fetch_assoc();
$criticalCat = $critRow ? $critRow['department'] : '—';
?>