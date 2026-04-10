<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once __DIR__ . '/../config/db.php';

$year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$department = isset($_GET['department']) ? $_GET['department'] : '';
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'overall';
$selected_employee = isset($_GET['employee']) ? $_GET['employee'] : '';
$threshold = isset($_GET['threshold']) ? intval($_GET['threshold']) : 60;
$top_count = isset($_GET['top_count']) ? intval($_GET['top_count']) : 10;

// Debug: Log the received employee
error_log("export_excel_preview.php - Report type: $report_type, Employee: $selected_employee");

// Helper functions
function classifyPerformance($percentage) {
    if ($percentage >= 85) return ['label' => 'Top Performer', 'class' => 'status-excellent'];
    if ($percentage >= 70) return ['label' => 'Good', 'class' => 'status-good'];
    if ($percentage >= 50) return ['label' => 'Average', 'class' => 'status-average'];
    if ($percentage >= 40) return ['label' => 'Critical', 'class' => 'status-critical'];
    return ['label' => 'At Risk', 'class' => 'status-risk'];
}

function calculateEmployeeScore($conn, $employee_name, $year, $template_id = 3) {
    $query = "SELECT kd.Score, kti.weight 
              FROM kpi_data kd
              JOIN kpi_template_items kti ON kd.KPI_Code = kti.kpi_code AND kd.template_id = kti.template_id
              WHERE kd.Name = ? AND YEAR(kd.Date) = ? AND kd.template_id = ? AND kti.is_active = 1";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "sii", $employee_name, $year, $template_id);
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
    if (!empty($department)) {
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
        $rating = classifyPerformance($score);
        $employees[] = [
            'name' => $row['full_name'],
            'department' => $row['department'],
            'position' => $row['position'],
            'staff_code' => $row['staff_code'],
            'score' => $score,
            'rating_label' => $rating['label'],
            'rating_class' => $rating['class']
        ];
    }
    return $employees;
}

// ==================== INDIVIDUAL REPORT ====================
if ($report_type == 'individual') {
    // If no employee selected, show error message
    if (empty($selected_employee)) {
        echo '<div class="excel-header">';
        echo '<h2><i class="fas fa-user-circle me-2"></i>Individual Employee Report</h2>';
        echo '<div class="alert alert-warning">';
        echo '<strong>No employee selected.</strong><br><br>';
        echo 'Please follow these steps:<br>';
        echo '1. Go back to the Reports page<br>';
        echo '2. Select "Individual Employee Report" from the Report Type dropdown<br>';
        echo '3. Select an employee from the dropdown that appears<br>';
        echo '4. Click the Excel button again<br>';
        echo '</div></div>';
        exit;
    }
    
    // Get employee KPI details
    $query = "SELECT kti.kpi_group, kti.kpi_code, kti.kpi_description, kd.Score, kti.weight, kti.section
              FROM kpi_data kd
              JOIN kpi_template_items kti ON kd.KPI_Code = kti.kpi_code AND kd.template_id = kti.template_id
              WHERE kd.Name = ? AND YEAR(kd.Date) = ? AND kti.is_active = 1
              ORDER BY kti.section, kti.display_order";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "si", $selected_employee, $year);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) == 0) {
        echo '<div class="excel-header">';
        echo '<h2><i class="fas fa-user-circle me-2"></i>Individual Employee Report</h2>';
        echo '<div class="alert alert-warning">';
        echo '<strong>No KPI data found</strong> for employee: ' . htmlspecialchars($selected_employee) . ' for year ' . $year . '.';
        echo '</div></div>';
        exit;
    }
    
    $total_weighted = 0;
    $total_weight = 0;
    $sections = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $weighted = ($row['Score'] / 5) * $row['weight'];
        $total_weighted += $weighted;
        $total_weight += $row['weight'];
        
        $section = $row['section'];
        if (!isset($sections[$section])) {
            $sections[$section] = [];
        }
        $sections[$section][] = $row;
    }
    
    $final_percentage = $total_weight > 0 ? ($total_weighted / $total_weight) * 100 : 0;
    $rating = classifyPerformance($final_percentage);
    
    // Get employee info
    $emp_query = "SELECT * FROM staff WHERE full_name = ?";
    $stmt = mysqli_prepare($conn, $emp_query);
    mysqli_stmt_bind_param($stmt, "s", $selected_employee);
    mysqli_stmt_execute($stmt);
    $emp_result = mysqli_stmt_get_result($stmt);
    $employee = mysqli_fetch_assoc($emp_result);
    
    // Get supervisor comments
    $comment_query = "SELECT * FROM kpi_comment WHERE Name = ? AND Year = ?";
    $stmt = mysqli_prepare($conn, $comment_query);
    mysqli_stmt_bind_param($stmt, "si", $selected_employee, $year);
    mysqli_stmt_execute($stmt);
    $comment_result = mysqli_stmt_get_result($stmt);
    $comments = mysqli_fetch_assoc($comment_result);
    ?>
    
    <div class="excel-header">
        <h2><i class="fas fa-user-circle me-2"></i>Individual Employee KPI Report</h2>
        <p><strong>Employee:</strong> <?php echo htmlspecialchars($selected_employee); ?></p>
        <p><strong>Staff ID:</strong> <?php echo $employee['staff_code'] ?? 'N/A'; ?></p>
        <p><strong>Department:</strong> <?php echo $employee['department'] ?? 'N/A'; ?></p>
        <p><strong>Position:</strong> <?php echo $employee['position'] ?? 'N/A'; ?></p>
        <p><strong>Review Period:</strong> January - December <?php echo $year; ?></p>
        <p><strong>Report Generated:</strong> <?php echo date('F j, Y H:i:s'); ?></p>
    </div>
    
    <div class="summary-stats">
        <div class="stat-box"><div class="number"><?php echo round($final_percentage, 1); ?>%</div><div class="label">Final Score</div></div>
        <div class="stat-box"><div class="number"><?php echo $rating['label']; ?></div><div class="label">Rating</div></div>
    </div>
    
    <?php foreach ($sections as $section_name => $section_items): ?>
        <h5 class="mt-4"><?php echo htmlspecialchars($section_name); ?></h5>
        <div class="table-wrapper">
            <table class="excel-table">
                <thead>
                    <tr>
                        <th>KPI Code</th>
                        <th>KPI Group</th>
                        <th>Description</th>
                        <th>Score (1-5)</th>
                        <th>Weight (%)</th>
                        <th>Weighted Score</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($section_items as $item): 
                        $weight = isset($item['weight']) && $item['weight'] > 0 ? $item['weight'] : 0;
                        $weighted = ($item['Score'] / 5) * $weight;
                    ?>
                        <tr>
                            <td style="vertical-align: middle;"><?php echo htmlspecialchars($item['kpi_code']); ?></td>
                            <td style="vertical-align: middle;"><?php echo htmlspecialchars($item['kpi_group']); ?></td>
                            <td style="vertical-align: middle;"><?php echo htmlspecialchars($item['kpi_description']); ?></td>
                            <td style="text-align: center; vertical-align: middle;"><?php echo $item['Score']; ?> / 5</td>
                            <td style="text-align: center; vertical-align: middle;">
                                <strong><?php echo $weight > 0 ? $weight . '%' : '0%'; ?></strong>
                            </td>
                            <td style="text-align: center; vertical-align: middle;">
                                <?php echo $weight > 0 ? round($weighted, 1) . '%' : '0%'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php
                    // Calculate section total
                    $section_weighted = 0;
                    $section_weight = 0;
                    foreach ($section_items as $item) {
                        $section_weighted += ($item['Score'] / 5) * $item['weight'];
                        $section_weight += $item['weight'];
                    }
                    ?>
                    <tr style="background: #f0f0f0; font-weight: bold;">
                        <td colspan="5" style="text-align: right;">Section Total:</td>
                        <td style="text-align: center;">
                            <?php echo round($section_weighted, 1) . '% / ' . $section_weight . '%'; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
    
    <h5 class="mt-4">Final Score Summary</h5>
    <table class="excel-table">
        <tr style="background: #e8f5e9;">
            <td><strong>Total Weighted Score:</strong></td>
            <td><strong><?php echo round($total_weighted, 1); ?>%</strong></td>
            <td>out of <?php echo $total_weight; ?>% total weight</td>
        </tr>
        <tr style="background: #e8f5e9;">
            <td><strong>Final Percentage:</strong></td>
            <td><strong><?php echo round($final_percentage, 1); ?>%</strong></td>
            <td><span class="status-badge <?php echo $rating['class']; ?>"><?php echo $rating['label']; ?></span></td>
        </tr>
    </table>
    
    <?php if ($comments && !empty($comments['Supervisor Comments'])): ?>
    <h5 class="mt-4">Supervisor Comments</h5>
    <table class="excel-table">
        <tr>
            <td><strong>Supervisor Comments:</strong></td>
            <td><?php echo nl2br(htmlspecialchars($comments['Supervisor Comments'])); ?></td>
        </tr>
        <?php if (!empty($comments['Training/Development Recommendations'])): ?>
        <tr>
            <td><strong>Training Recommendations:</strong></td>
            <td><?php echo nl2br(htmlspecialchars($comments['Training/Development Recommendations'])); ?></td>
        </tr>
        <?php endif; ?>
    </table>
    <?php endif; ?>

<?php

// ==================== OVERALL REPORT ====================
} elseif ($report_type == 'overall') {
    $employees = getEmployeeScores($conn, $year, $department);
    $total_staff = count($employees);
    
    if ($total_staff == 0) {
        echo '<div class="alert alert-warning">No data found for the selected criteria.</div>';
        exit;
    }
    
    $avg_score = $total_staff > 0 ? array_sum(array_column($employees, 'score')) / $total_staff : 0;
    
    $distribution = ['Top Performer' => 0, 'Good' => 0, 'Average' => 0, 'Critical' => 0, 'At Risk' => 0];
    foreach ($employees as $emp) {
        $distribution[$emp['rating_label']]++;
    }
    ?>
    
    <div class="excel-header">
        <h2><i class="fas fa-chart-bar me-2"></i>Overall KPI Performance Report</h2>
        <p><strong>Organization:</strong> KPI System Sdn Bhd</p>
        <p><strong>Department:</strong> <?php echo $department ?: 'All Departments'; ?></p>
        <p><strong>Reporting Period:</strong> January - December <?php echo $year; ?></p>
        <p><strong>Report Generated:</strong> <?php echo date('F j, Y H:i:s'); ?></p>
    </div>
    
    <div class="summary-stats">
        <div class="stat-box"><div class="number"><?php echo $total_staff; ?></div><div class="label">Total Staff</div></div>
        <div class="stat-box"><div class="number"><?php echo round($avg_score, 1); ?>%</div><div class="label">Average Score</div></div>
        <div class="stat-box"><div class="number"><?php echo $distribution['Top Performer']; ?></div><div class="label">Top Performers</div></div>
        <div class="stat-box"><div class="number"><?php echo $distribution['At Risk']; ?></div><div class="label">At Risk</div></div>
    </div>
    
    <h5>Performance Distribution Summary</h5>
    <table class="excel-table">
        <thead><tr><th>Category</th><th>Count</th><th>Percentage</th></tr></thead>
        <tbody>
            <?php foreach ($distribution as $category => $count): ?>
            <tr><td><?php echo $category; ?></td><td><?php echo $count; ?></td><td><?php echo $total_staff > 0 ? round(($count/$total_staff)*100, 1) : 0; ?>%</td></tr>
            <?php endforeach; ?>
        </tbody>
     licensierad
    
    <h5 class="mt-4">Staff Performance Details</h5>
    <table class="excel-table">
        <thead><tr><th>#</th><th>Staff Code</th><th>Staff Name</th><th>Department</th><th>Position</th><th>KPI Score (%)</th><th>Rating</th></tr></thead>
        <tbody>
            <?php $counter = 1; foreach ($employees as $emp): ?>
            <tr><td><?php echo $counter++; ?></td><td><?php echo $emp['staff_code']; ?></td><td><strong><?php echo $emp['name']; ?></strong></td><td><?php echo $emp['department']; ?></td><td><?php echo $emp['position']; ?></td><td><strong><?php echo $emp['score']; ?>%</strong></td><td><span class="status-badge <?php echo $emp['rating_class']; ?>"><?php echo $emp['rating_label']; ?></span></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>

<?php
// ==================== DEPARTMENT REPORT ====================
} elseif ($report_type == 'department') {
    $dept_query = "SELECT DISTINCT department FROM staff WHERE department IS NOT NULL";
    $dept_result = mysqli_query($conn, $dept_query);
    
    $dept_scores = [];
    while ($dept = mysqli_fetch_assoc($dept_result)) {
        $employees = getEmployeeScores($conn, $year, $dept['department']);
        if (count($employees) > 0) {
            $avg_score = array_sum(array_column($employees, 'score')) / count($employees);
            $dept_scores[] = [
                'name' => $dept['department'],
                'avg_score' => $avg_score,
                'count' => count($employees),
                'employees' => $employees
            ];
        }
    }
    
    usort($dept_scores, function($a, $b) { return $b['avg_score'] <=> $a['avg_score']; });
    ?>
    
    <div class="excel-header">
        <h2><i class="fas fa-building me-2"></i>Department Performance Report</h2>
        <p><strong>Organization:</strong> KPI System Sdn Bhd</p>
        <p><strong>Reporting Period:</strong> January - December <?php echo $year; ?></p>
        <p><strong>Report Generated:</strong> <?php echo date('F j, Y H:i:s'); ?></p>
    </div>
    
    <h5>Department Ranking</h5>
    <table class="excel-table">
        <thead><tr><th>Rank</th><th>Department</th><th>Staff Count</th><th>Average Score (%)</th><th>Rating</th></tr></thead>
        <tbody>
            <?php foreach ($dept_scores as $index => $dept): 
                $rating = classifyPerformance($dept['avg_score']);
            ?>
            <tr><td><?php echo $index + 1; ?></td><td><strong><?php echo $dept['name']; ?></strong></td><td><?php echo $dept['count']; ?></td><td><strong><?php echo round($dept['avg_score'], 1); ?>%</strong></td><td><span class="status-badge <?php echo $rating['class']; ?>"><?php echo $rating['label']; ?></span></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <?php foreach ($dept_scores as $dept): ?>
        <h5 class="mt-4"><?php echo $dept['name']; ?> - Staff Details</h5>
        <table class="excel-table">
            <thead><tr><th>#</th><th>Staff Name</th><th>Position</th><th>KPI Score (%)</th><th>Rating</th></tr></thead>
            <tbody>
                <?php $counter = 1; foreach ($dept['employees'] as $emp): ?>
                <tr><td><?php echo $counter++; ?></td><td><?php echo $emp['name']; ?></td><td><?php echo $emp['position']; ?></td><td><strong><?php echo $emp['score']; ?>%</strong></td><td><span class="status-badge <?php echo $emp['rating_class']; ?>"><?php echo $emp['rating_label']; ?></span></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>

<?php
// ==================== LOW PERFORMANCE REPORT ====================
} elseif ($report_type == 'low') {
    $employees = getEmployeeScores($conn, $year, $department);
    $low_performers = array_filter($employees, function($emp) use ($threshold) {
        return $emp['score'] < $threshold;
    });
    ?>
    
    <div class="excel-header">
        <h2><i class="fas fa-exclamation-triangle me-2"></i>At-Risk Staff Report</h2>
        <p><strong>Threshold:</strong> Below <?php echo $threshold; ?>%</p>
        <p><strong>Reporting Period:</strong> January - December <?php echo $year; ?></p>
        <p><strong>Report Generated:</strong> <?php echo date('F j, Y H:i:s'); ?></p>
    </div>
    
    <?php if (count($low_performers) > 0): ?>
        <div class="summary-stats">
            <div class="stat-box"><div class="number"><?php echo count($low_performers); ?></div><div class="label">At-Risk Staff</div></div>
            <div class="stat-box"><div class="number"><?php echo count($employees); ?></div><div class="label">Total Staff</div></div>
            <div class="stat-box"><div class="number"><?php echo round((count($low_performers)/count($employees))*100, 1); ?>%</div><div class="label">Percentage</div></div>
        </div>
        
        <h5>At-Risk Staff Details</h5>
        <table class="excel-table">
            <thead><tr><th>#</th><th>Staff Name</th><th>Department</th><th>Position</th><th>KPI Score (%)</th><th>Risk Level</th><th>Recommended Action</th></tr></thead>
            <tbody>
                <?php $counter = 1; foreach ($low_performers as $emp): 
                    $action = $emp['score'] < 40 ? 'Performance Improvement Plan Required' : ($emp['score'] < 50 ? 'Intensive Training Required' : 'Monitoring & Coaching Required');
                    $risk_level = $emp['score'] < 50 ? 'High Risk' : 'Medium Risk';
                ?>
                <tr><td><?php echo $counter++; ?></td><td><?php echo $emp['name']; ?></td><td><?php echo $emp['department']; ?></td><td><?php echo $emp['position']; ?></td><td><strong class="text-danger"><?php echo $emp['score']; ?>%</strong></td><td><?php echo $risk_level; ?></td><td><?php echo $action; ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="alert alert-success">No employees found below the <?php echo $threshold; ?>% threshold.</div>
    <?php endif; ?>

<?php
// ==================== TOP PERFORMERS REPORT ====================
} elseif ($report_type == 'top') {
    $employees = getEmployeeScores($conn, $year, $department);
    usort($employees, function($a, $b) { return $b['score'] <=> $a['score']; });
    $top_performers = array_slice($employees, 0, $top_count);
    ?>
    
    <div class="excel-header">
        <h2><i class="fas fa-trophy me-2"></i>Top Performers Recognition Report</h2>
        <p><strong>Showing:</strong> Top <?php echo $top_count; ?> performers</p>
        <p><strong>Reporting Period:</strong> January - December <?php echo $year; ?></p>
        <p><strong>Report Generated:</strong> <?php echo date('F j, Y H:i:s'); ?></p>
    </div>
    
    <h5>Top <?php echo $top_count; ?> Performers List</h5>
    <table class="excel-table">
        <thead><tr><th>Rank</th><th>Staff Name</th><th>Department</th><th>Position</th><th>KPI Score (%)</th><th>Award</th></tr></thead>
        <tbody>
            <?php foreach ($top_performers as $index => $emp): 
                $award = $index == 0 ? '🏆 Excellence Award' : ($index == 1 ? '⭐ Silver Award' : ($index == 2 ? '🌟 Bronze Award' : '📝 Recognition Letter'));
            ?>
            <tr><td><strong><?php echo $index + 1; ?></strong></td><td><strong><?php echo $emp['name']; ?></strong></td><td><?php echo $emp['department']; ?></td><td><?php echo $emp['position']; ?></td><td><strong><?php echo $emp['score']; ?>%</strong></td><td><?php echo $award; ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>

<?php
// ==================== TREND REPORT ====================
} elseif ($report_type == 'trend') {
    $trend_query = "SELECT YEAR(Date) as year, AVG(kd.Score) as avg_raw_score
                    FROM kpi_data kd
                    JOIN kpi_template_items kti ON kd.KPI_Code = kti.kpi_code AND kd.template_id = kti.template_id
                    WHERE kti.is_active = 1
                    GROUP BY YEAR(Date)
                    ORDER BY year";
    $trend_result = mysqli_query($conn, $trend_query);
    
    $years_data = [];
    $scores_data = [];
    while ($row = mysqli_fetch_assoc($trend_result)) {
        $percentage = ($row['avg_raw_score'] / 5) * 100;
        $years_data[] = $row['year'];
        $scores_data[] = round($percentage, 1);
    }
    
    $overall_trend = end($scores_data) - reset($scores_data);
    $trend_direction = $overall_trend > 0 ? 'improved' : ($overall_trend < 0 ? 'declined' : 'remained stable');
    $trend_percentage = abs(round(($overall_trend / reset($scores_data)) * 100, 1));
    ?>
    
    <div class="excel-header">
        <h2><i class="fas fa-chart-line me-2"></i>Performance Trend Report</h2>
        <p><strong>Organization:</strong> KPI System Sdn Bhd</p>
        <p><strong>Report Generated:</strong> <?php echo date('F j, Y H:i:s'); ?></p>
    </div>
    
    <div class="summary-stats">
        <div class="stat-box"><div class="number"><?php echo ucfirst($trend_direction); ?></div><div class="label">Overall Trend</div></div>
        <div class="stat-box"><div class="number"><?php echo $trend_percentage; ?>%</div><div class="label">Change</div></div>
        <div class="stat-box"><div class="number"><?php echo min($scores_data); ?>%</div><div class="label">Lowest Score</div></div>
        <div class="stat-box"><div class="number"><?php echo max($scores_data); ?>%</div><div class="label">Highest Score</div></div>
    </div>
    
    <h5>Year-over-Year Comparison</h5>
    <table class="excel-table">
        <thead><tr><th>Year</th><th>Average KPI Score</th><th>Change from Previous Year</th><th>% Change</th><th>Trend</th></tr></thead>
        <tbody>
            <?php foreach ($years_data as $index => $year_val): ?>
            <tr>
                <td><strong><?php echo $year_val; ?></strong></td>
                <td><strong><?php echo $scores_data[$index]; ?>%</strong></td>
                <?php if ($index > 0): 
                    $change = $scores_data[$index] - $scores_data[$index-1];
                    $pct_change = ($scores_data[$index] - $scores_data[$index-1]) / $scores_data[$index-1] * 100;
                ?>
                    <td><?php echo ($change >= 0 ? '+' : '') . round($change, 1); ?>%</td>
                    <td><?php echo ($pct_change >= 0 ? '+' : '') . round($pct_change, 1); ?>%</td>
                    <td><?php echo $scores_data[$index] > $scores_data[$index-1] ? '📈 Improving' : ($scores_data[$index] < $scores_data[$index-1] ? '📉 Declining' : '➡️ Stable'); ?></td>
                <?php else: ?>
                    <td>-</td><td>-</td><td>Base Year</td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="alert alert-info mt-4">
        <strong>Trend Analysis:</strong> Overall performance has <strong><?php echo $trend_direction; ?></strong> by <strong><?php echo $trend_percentage; ?>%</strong> over the analyzed period.
    </div>

<?php
// ==================== TRAINING REPORT ====================
} elseif ($report_type == 'training') {
    // Get all employees for the year
    $staff_query = "SELECT DISTINCT Name FROM kpi_data WHERE YEAR(Date) = ?";
    $stmt = mysqli_prepare($conn, $staff_query);
    mysqli_stmt_bind_param($stmt, "i", $year);
    mysqli_stmt_execute($stmt);
    $staff_result = mysqli_stmt_get_result($stmt);
    
    $employees = [];
    while ($row = mysqli_fetch_assoc($staff_result)) {
        $employees[] = $row['Name'];
    }
    $total_staff = count($employees);
    
    // Training categories with their KPI groups
    $training_categories = [
        'Communication Skills' => ['Customer Service Quality', 'Daily Sales Operations'],
        'Technical Skills' => ['Inventory & Cost Control', 'Store Operations Support', 'AI Utilization & Digital Efficiency'],
        'Leadership & Management' => ['Competency', 'Sales Target Contribution'],
        'Time Management' => ['Training, Learning & Team Contribution', 'People, Training, Learning & Team Contribution'],
        'Professional Development' => ['Competency']
    ];
    
    // Calculate training needs
    $training_needs = [];
    $staff_by_training = [];
    
    foreach ($employees as $employee_name) {
        foreach ($training_categories as $category => $kpi_groups) {
            $group_placeholders = implode("','", $kpi_groups);
            $query = "SELECT AVG(kd.Score) as avg_score
                      FROM kpi_data kd
                      JOIN kpi_template_items kti ON kd.KPI_Code = kti.kpi_code AND kd.template_id = kti.template_id
                      WHERE kd.Name = ? AND YEAR(kd.Date) = ? AND kti.kpi_group IN ('$group_placeholders') AND kti.is_active = 1";
            
            $stmt2 = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt2, "si", $employee_name, $year);
            mysqli_stmt_execute($stmt2);
            $result2 = mysqli_stmt_get_result($stmt2);
            $row2 = mysqli_fetch_assoc($result2);
            
            if ($row2 && $row2['avg_score'] !== null) {
                $percentage = ($row2['avg_score'] / 5) * 100;
                if ($percentage < 60) {
                    if (!isset($training_needs[$category])) {
                        $training_needs[$category] = 0;
                        $staff_by_training[$category] = [];
                    }
                    if (!in_array($employee_name, $staff_by_training[$category])) {
                        $training_needs[$category]++;
                        $staff_by_training[$category][] = $employee_name;
                    }
                }
            }
        }
    }
    
    arsort($training_needs);
    ?>
    
    <div class="excel-header">
        <h2><i class="fas fa-brain me-2"></i>Training Needs Summary - <?php echo $year; ?></h2>
        <p><strong>Organization:</strong> KPI System Sdn Bhd</p>
        <p><strong>Total Staff Evaluated:</strong> <?php echo $total_staff; ?></p>
        <p><strong>Report Generated:</strong> <?php echo date('F j, Y H:i:s'); ?></p>
        <p><strong>Note:</strong> This report identifies training needs based on KPI performance below 60% threshold.</p>
    </div>
    
    <h5>Training Demand Overview</h5>
    <table class="excel-table">
        <thead><tr><th>Training Area</th><th>No. of Staff</th><th>% of Staff</th><th>Priority Level</th></tr></thead>
        <tbody>
            <?php if (empty($training_needs)): ?>
            <tr><td colspan="4" style="text-align: center;">No significant training needs detected for <?php echo $year; ?></td></tr>
            <?php else: ?>
                <?php foreach ($training_needs as $category => $count): 
                    $percentage = round(($count / $total_staff) * 100, 1);
                    $priority = $percentage >= 30 ? 'High' : ($percentage >= 15 ? 'Medium' : 'Low');
                    $priority_class = $priority == 'High' ? 'status-critical' : ($priority == 'Medium' ? 'status-warning' : 'status-info');
                ?>
                <tr>
                    <td><strong><?php echo $category; ?></strong></td>
                    <td><?php echo $count; ?> / <?php echo $total_staff; ?></td>
                    <td><?php echo $percentage; ?>%</td>
                    <td><span class="status-badge <?php echo $priority_class; ?>"><?php echo $priority; ?> Priority</span></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <?php foreach ($staff_by_training as $category => $staffs): ?>
        <h5 class="mt-4"><?php echo $category; ?> - Staff Requiring Training</h5>
        <table class="excel-table">
            <thead><tr><th>#</th><th>Staff Name</th></tr></thead>
            <tbody>
                <?php $counter = 1; foreach ($staffs as $name): ?>
                <tr><td><?php echo $counter++; ?></td><td><?php echo $name; ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>

<?php
} else {
    echo '<div class="alert alert-warning">No data found for the selected report type or criteria.</div>';
}
?>