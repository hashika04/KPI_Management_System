<?php
include("../includes/auth.php");
$activePage = 'reports';

$year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$department = isset($_GET['department']) ? $_GET['department'] : '';
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'overall';

// Helper functions (same as generate_report.php)
function classifyPerformance($percentage) {
    if ($percentage >= 85) return 'Top Performer';
    if ($percentage >= 70) return 'Good';
    if ($percentage >= 50) return 'Average';
    if ($percentage >= 40) return 'Critical';
    return 'At Risk';
}

function calculateEmployeeScore($conn, $employee_name, $year, $template_id = 3) {
    $query = "SELECT kd.Score, kti.weight 
              FROM kpi_data kd
              JOIN kpi_template_items kti ON kd.KPI_Code = kti.kpi_code AND kd.template_id = kti.template_id
              WHERE kd.Name = ? AND YEAR(kd.Date) = ? AND kti.is_active = 1";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "si", $employee_name, $year);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $total_weighted = 0;
    $total_weight = 0;
    while ($row = mysqli_fetch_assoc($result)) {
        $total_weighted += ($row['Score'] / 5) * $row['weight'];
        $total_weight += $row['weight'];
    }
    return $total_weight > 0 ? round(($total_weighted / $total_weight) * 100, 2) : 0;
}

function getEmployeeScores($conn, $year, $department = '') {
    $sql = "SELECT s.full_name, s.department, s.position, s.staff_code
            FROM staff s
            WHERE s.full_name IN (SELECT DISTINCT Name FROM kpi_data WHERE YEAR(Date) = ?)";
    if ($department) {
        $sql .= " AND s.department = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "is", $year, $department);
    } else {
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $year);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $employees = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $score = calculateEmployeeScore($conn, $row['full_name'], $year);
        $employees[] = [
            'name' => $row['full_name'],
            'department' => $row['department'],
            'position' => $row['position'],
            'staff_code' => $row['staff_code'],
            'score' => $score,
            'rating' => classifyPerformance($score)
        ];
    }
    return $employees;
}

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="kpi_report_' . $year . '_' . date('Ymd') . '.xls"');

echo '<html>';
echo '<head><meta charset="UTF-8"><title>KPI Report</title></head>';
echo '<body>';

if ($report_type == 'overall') {
    $employees = getEmployeeScores($conn, $year, $department);
    
    echo '<h2>Overall KPI Performance Report</h2>';
    echo '<h3>' . ($department ? $department : 'All Departments') . ' - ' . $year . '</h3>';
    echo '<table border="1" cellpadding="5">';
    echo '<tr>';
    echo '<th>Staff Name</th><th>Staff Code</th><th>Department</th><th>Position</th><th>KPI Score (%)</th><th>Rating</th>';
    echo '</tr>';
    
    foreach ($employees as $emp) {
        echo '<tr>';
        echo '<td>' . $emp['name'] . '</td>';
        echo '<td>' . $emp['staff_code'] . '</td>';
        echo '<td>' . $emp['department'] . '</td>';
        echo '<td>' . $emp['position'] . '</td>';
        echo '<td>' . $emp['score'] . '%</td>';
        echo '<td>' . $emp['rating'] . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '<p>Report Generated: ' . date('Y-m-d H:i:s') . '</p>';
} 
elseif ($report_type == 'department') {
    $dept_query = "SELECT DISTINCT department FROM staff WHERE department IS NOT NULL";
    $dept_result = mysqli_query($conn, $dept_query);
    
    echo '<h2>Department Performance Report - ' . $year . '</h2>';
    echo '<table border="1" cellpadding="5">';
    echo '<tr><th>Rank</th><th>Department</th><th>Staff Count</th><th>Average Score (%)</th><th>Rating</th></tr>';
    
    $dept_scores = [];
    while ($dept = mysqli_fetch_assoc($dept_result)) {
        $employees = getEmployeeScores($conn, $year, $dept['department']);
        if (count($employees) > 0) {
            $avg_score = array_sum(array_column($employees, 'score')) / count($employees);
            $dept_scores[] = [
                'name' => $dept['department'],
                'avg_score' => $avg_score,
                'count' => count($employees)
            ];
        }
    }
    
    usort($dept_scores, function($a, $b) {
        return $b['avg_score'] <=> $a['avg_score'];
    });
    
    foreach ($dept_scores as $index => $dept) {
        echo '<tr>';
        echo '<td>' . ($index + 1) . '</td>';
        echo '<td>' . $dept['name'] . '</td>';
        echo '<td>' . $dept['count'] . '</td>';
        echo '<td>' . round($dept['avg_score'], 1) . '%</td>';
        echo '<td>' . classifyPerformance($dept['avg_score']) . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
}
elseif ($report_type == 'low') {
    $threshold = isset($_GET['threshold']) ? intval($_GET['threshold']) : 60;
    $employees = getEmployeeScores($conn, $year, $department);
    $low_performers = array_filter($employees, function($emp) use ($threshold) {
        return $emp['score'] < $threshold;
    });
    
    echo '<h2>Low Performance Report - Below ' . $threshold . '%</h2>';
    echo '<h3>' . $year . '</h3>';
    echo '<table border="1" cellpadding="5">';
    echo '<tr><th>Staff Name</th><th>Department</th><th>Position</th><th>KPI Score (%)</th><th>Rating</th><th>Required Action</th></tr>';
    
    foreach ($low_performers as $emp) {
        $action = $emp['score'] < 40 ? 'Performance Improvement Plan' : ($emp['score'] < 50 ? 'Intensive Training' : 'Monitoring & Coaching');
        echo '<tr>';
        echo '<td>' . $emp['name'] . '</td>';
        echo '<td>' . $emp['department'] . '</td>';
        echo '<td>' . $emp['position'] . '</td>';
        echo '<td>' . $emp['score'] . '%</td>';
        echo '<td>' . $emp['rating'] . '</td>';
        echo '<td>' . $action . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
}
elseif ($report_type == 'top') {
    $top_count = isset($_GET['top_count']) ? intval($_GET['top_count']) : 10;
    $employees = getEmployeeScores($conn, $year, $department);
    usort($employees, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });
    $top_performers = array_slice($employees, 0, $top_count);
    
    echo '<h2>Top ' . $top_count . ' Performers - ' . $year . '</h2>';
    echo '<table border="1" cellpadding="5">';
    echo '<tr><th>Rank</th><th>Staff Name</th><th>Department</th><th>Position</th><th>KPI Score (%)</th></tr>';
    
    foreach ($top_performers as $index => $emp) {
        echo '<tr>';
        echo '<td>' . ($index + 1) . '</td>';
        echo '<td>' . $emp['name'] . '</td>';
        echo '<td>' . $emp['department'] . '</td>';
        echo '<td>' . $emp['position'] . '</td>';
        echo '<td>' . $emp['score'] . '%</td>';
        echo '</tr>';
    }
    
    echo '</table>';
}

echo '</body></html>';
?>