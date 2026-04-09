<?php
/* ── data.php ──*/
$sql = "
    SELECT
        s.id,
        s.full_name   AS name,
        s.staff_code  AS staffId,
        s.department  AS dept,
        s.profile_photo AS avatar,
        ROUND(COALESCE((AVG(k.Score) / 5) * 100, 0), 1) AS score
    FROM staff s
    LEFT JOIN kpi_data k ON k.Name = s.full_name
    GROUP BY s.id, s.full_name, s.staff_code, s.department, s.profile_photo
    ORDER BY score DESC
";
 
$result    = $conn->query($sql);
$staffData = [];
 
while ($row = $result->fetch_assoc()) {
    $score = (float)$row['score'];
 
    /* Classify level based on score */
    if ($score >= 85) {
        $level = 'top';
    } elseif ($score >= 70) {
        $level = 'good';
    } elseif ($score >= 50) {
        $level = 'average';
    } elseif ($score > 0) {
        $level = 'at-risk';
    } else {
        $level = 'critical';   /* score = 0 means no KPI data at all */
    }
 
    /* Simple trend: compare last month's avg vs overall avg */
    $sqlTrend = "
        SELECT ROUND(COALESCE((AVG(Score)/5)*100, 0), 1) AS recent_score
        FROM kpi_data
        WHERE Name = ?
          AND STR_TO_DATE(Date,'%m/%d/%Y') >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ";
    $stmt = $conn->prepare($sqlTrend);
    $stmt->bind_param('s', $row['name']);
    $stmt->execute();
    $trendRow    = $stmt->get_result()->fetch_assoc();
    $recentScore = (float)($trendRow['recent_score'] ?? 0);
    $trend       = ($recentScore >= $score) ? 'up' : 'down';
    $stmt->close();
 
    $staffData[] = [
        'id'      => (int)$row['id'],
        'name'    => $row['name'],
        'staffId' => $row['staffId'],
        'dept'    => $row['dept'],
        'avatar'  => $row['avatar'] ?? '',
        'score'   => $score,
        'level'   => $level,
        'trend'   => $trend,
    ];
}
 
/* ── Computed summary stats ── */
$totalStaff  = count($staffData);
$topCount    = count(array_filter($staffData, fn($s) => $s['level'] === 'top'));
$atRiskCount = count(array_filter($staffData, fn($s) => in_array($s['level'], ['at-risk', 'critical'])));
 
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
$critResult  = $conn->query($sqlCrit);
$critRow     = $critResult->fetch_assoc();
$criticalCat = $critRow ? $critRow['department'] : '—';
 
/* ── Top 3 performers (podium) ── */
$tops = array_slice($staffData, 0, 3);

$podium = [
    'bronze' => $tops[2] ?? null,
    'gold'   => $tops[0] ?? null,
    'silver' => $tops[1] ?? null,
];
 
/* ── At-risk staff (lowest scores first) ── */
$atRisk = array_values(
    array_filter($staffData, fn($s) => in_array($s['level'], ['at-risk', 'critical']))
);
usort($atRisk, fn($a, $b) => $a['score'] <=> $b['score']);
 
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
    WHERE m.kpi_group != 'KPI_Group'            -- exclude header row
      AND m.kpi_group IS NOT NULL
    GROUP BY m.kpi_group
    ORDER BY avg_pct ASC                         -- lowest first → critical at top
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
?>