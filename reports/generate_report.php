<?php
include("../includes/auth.php");

if (isset($_GET['year'])) {
    $year = $_GET['year'];
} else {
    $latest_year_query = "SELECT MAX(YEAR(Date)) as latest_year FROM kpi_data";
    $latest_year_result = mysqli_query($conn, $latest_year_query);
    $latest_year_row = mysqli_fetch_assoc($latest_year_result);
    $year = $latest_year_row['latest_year'] ?? date('Y');
}
$department = isset($_GET['department']) ? $_GET['department'] : '';
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'overall';

// Helper function to classify performance
function classifyPerformance($percentage) {
    if ($percentage >= 85) return 'top';
    if ($percentage >= 70) return 'good';
    if ($percentage >= 50) return 'average';
    if ($percentage >= 40) return 'critical';
    return 'at-risk';
}

function getRatingLabel($classification) {
    $labels = [
        'top' => ['label' => 'Top Performer', 'class' => 'rating-top', 'icon' => '🏆'],
        'good' => ['label' => 'Good', 'class' => 'rating-good', 'icon' => '👍'],
        'average' => ['label' => 'Average', 'class' => 'rating-average', 'icon' => '👌'],
        'critical' => ['label' => 'Critical', 'class' => 'rating-critical', 'icon' => '⚠️'],
        'at-risk' => ['label' => 'At Risk', 'class' => 'rating-risk', 'icon' => '🔴']
    ];
    return $labels[$classification] ?? ['label' => 'Unknown', 'class' => '', 'icon' => '❓'];
}

// Calculate employee KPI score
function calculateEmployeeScore($conn, $employee_name, $year, $template_id = 3) {
    $query = "SELECT kd.KPI_Code, kd.Score, kti.weight 
              FROM kpi_data kd
              JOIN kpi_template_items kti ON kd.KPI_Code = kti.kpi_code 
                  AND kd.template_id = kti.template_id
              WHERE kd.Name = ? AND YEAR(kd.Date) = ? AND kd.template_id = ?
              AND kti.is_active = 1";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "sii", $employee_name, $year, $template_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $total_weighted_score = 0;
    $total_weight = 0;
    
    while ($row = mysqli_fetch_assoc($result)) {
        $score = $row['Score'];
        $weight = $row['weight'];
        $total_weighted_score += ($score / 5) * $weight;
        $total_weight += $weight;
    }
    
    if ($total_weight > 0) {
        $percentage = ($total_weighted_score / $total_weight) * 100;
        return round($percentage, 2);
    }
    return 0;
}

// Get employee details with score
function getEmployeeScores($conn, $year, $department = '') {
    $sql = "SELECT s.id, s.full_name, s.department, s.position, s.staff_code
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
        $classification = classifyPerformance($score);
        $employees[] = [
            'id' => $row['id'],
            'name' => $row['full_name'],
            'department' => $row['department'],
            'position' => $row['position'],
            'staff_code' => $row['staff_code'],
            'score' => $score,
            'classification' => $classification,
            'rating' => getRatingLabel($classification)
        ];
    }
    
    return $employees;
}

// 1. OVERALL PERFORMANCE REPORT
if ($report_type == 'overall') {
    $employees = getEmployeeScores($conn, $year, $department);
    $total_staff = count($employees);
    
    if ($total_staff == 0) {
        echo '<div class="alert alert-warning">No data found for the selected criteria.</div>';
        exit;
    }
    
    $avg_score = array_sum(array_column($employees, 'score')) / $total_staff;
    
    $distribution = ['top' => 0, 'good' => 0, 'average' => 0, 'critical' => 0, 'at-risk' => 0];
    foreach ($employees as $emp) {
        $distribution[$emp['classification']]++;
    }
    ?>
    
    <div class="report-card-wrapper" id="reportCard">
        <div class="card-header-custom">
            <h3><i class="fas fa-chart-bar me-2" style="color: var(--primary);"></i> Overall KPI Performance Report</h3>
            <div class="export-buttons no-print">
                <button class="btn-export-pdf" onclick="exportToPDF()"><i class="fas fa-file-pdf"></i> PDF</button>
                <button class="btn-export-excel" onclick="exportToExcel()"><i class="fas fa-file-excel"></i> Excel</button>
                <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
            </div>
        </div>
        <div class="card-body-custom">
            <div class="text-center mb-4">
                <h4><?php echo $department ? $department : 'All Departments'; ?></h4>
                <p class="reports-subtitle">Reporting Period: January - December <?php echo $year; ?></p>
            </div>
            
            <!-- Organization Details -->
            <div class="info-box mb-4">
                <h5><i class="fas fa-building me-2"></i> Organization Information</h5>
                <p class="mb-0"><strong>Organization:</strong> KPI System Sdn Bhd<br>
                <strong>Department:</strong> <?php echo $department ?: 'All Departments'; ?><br>
                <strong>Reporting Period:</strong> <?php echo $year; ?> Annual Performance Review<br>
                <strong>Report Generated:</strong> <?php echo date('F j, Y'); ?></p>
            </div>
            
            <!-- Summary Statistics -->
            <div class="row mb-4">
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $total_staff; ?></div>
                        <div>Total Staff</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo round($avg_score, 1); ?>%</div>
                        <div>Average KPI Score</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo round($avg_score, 1); ?>/100</div>
                        <div>Overall Rating</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $distribution['top']; ?></div>
                        <div>Top Performers 🏆</div>
                    </div>
                </div>
            </div>
            
            <!-- Performance Distribution Chart -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="chart-container">
                        <canvas id="distributionChart"></canvas>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="chart-container">
                        <canvas id="gaugeChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Performance Distribution Table -->
            <div class="mb-4">
                <h5>Performance Distribution</h5>
                <table class="performance-table">
                    <thead>
                        <tr><th>Category</th><th>Count</th><th>Percentage</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>Top Performers 🟢</td><td><?php echo $distribution['top']; ?></td><td><?php echo round(($distribution['top']/$total_staff)*100,1); ?>%</td><td><span class="rating-badge rating-top">Excellent</span></td></tr>
                        <tr><td>Good 👍</td><td><?php echo $distribution['good']; ?></td><td><?php echo round(($distribution['good']/$total_staff)*100,1); ?>%</td><td><span class="rating-badge rating-good">Good</span></td></tr>
                        <tr><td>Average 🟡</td><td><?php echo $distribution['average']; ?></td><td><?php echo round(($distribution['average']/$total_staff)*100,1); ?>%</td><td><span class="rating-badge rating-average">Average</span></td></tr>
                        <tr><td>Critical ⚠️</td><td><?php echo $distribution['critical']; ?></td><td><?php echo round(($distribution['critical']/$total_staff)*100,1); ?>%</td><td><span class="rating-badge rating-critical">Critical</span></td></tr>
                        <tr><td>At Risk 🔴</td><td><?php echo $distribution['at-risk']; ?></td><td><?php echo round(($distribution['at-risk']/$total_staff)*100,1); ?>%</td><td><span class="rating-badge rating-risk">At Risk</span></td></tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Detailed Staff Table -->
            <div>
                <h5>Staff Performance Details</h5>
                <div class="table-responsive">
                    <table class="performance-table">
                        <thead>
                            <tr><th>Staff Name</th><th>Department</th><th>Position</th><th>KPI Score (%)</th><th>Rating</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $emp): ?>
                            <tr>
                                <td><?php echo $emp['name']; ?></td>
                                <td><?php echo $emp['department']; ?></td>
                                <td><?php echo $emp['position']; ?></td>
                                <td><strong><?php echo $emp['score']; ?>%</strong></td>
                                <td><?php echo $emp['rating']['icon']; ?> <?php echo $emp['rating']['label']; ?></td>
                                <td><span class="rating-badge <?php echo $emp['rating']['class']; ?>"><?php echo $emp['rating']['label']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    new Chart(document.getElementById('distributionChart'), {
        type: 'doughnut',
        data: {
            labels: ['Top Performers', 'Good', 'Average', 'Critical', 'At Risk'],
            datasets: [{
                data: [<?php echo $distribution['top'] . ',' . $distribution['good'] . ',' . $distribution['average'] . ',' . $distribution['critical'] . ',' . $distribution['at-risk']; ?>],
                backgroundColor: ['#06d6a0', '#118ab2', '#ffd166', '#fb8500', '#ef476f']
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });
    
    new Chart(document.getElementById('gaugeChart'), {
        type: 'doughnut',
        data: {
            labels: ['Achieved', 'Remaining'],
            datasets: [{
                data: [<?php echo $avg_score; ?>, <?php echo 100 - $avg_score; ?>],
                backgroundColor: ['#4361ee', '#e9ecef']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                tooltip: { callbacks: { label: function(context) { return context.raw.toFixed(1) + '%'; } } },
                legend: { position: 'bottom' }
            }
        }
    });
    </script>

<?php
}
// 2. INDIVIDUAL EMPLOYEE REPORT
elseif ($report_type == 'individual') {
    $selected_employee = isset($_GET['employee']) ? $_GET['employee'] : '';
    $employees_list = getEmployeeScores($conn, $year, $department);
    
    if ($selected_employee == '' && !empty($employees_list)) {
        $selected_employee = $employees_list[0]['name'];
    }
    
    if ($selected_employee) {
        $query = "SELECT kd.KPI_Code, kd.Score, kti.weight, kti.kpi_group, kti.kpi_description, kti.section
                  FROM kpi_data kd
                  JOIN kpi_template_items kti ON kd.KPI_Code = kti.kpi_code AND kd.template_id = kti.template_id
                  WHERE kd.Name = ? AND YEAR(kd.Date) = ? AND kti.is_active = 1
                  ORDER BY kti.section, kti.display_order";
        
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "si", $selected_employee, $year);
        mysqli_stmt_execute($stmt);
        $kpi_result = mysqli_stmt_get_result($stmt);
        
        $total_weighted = 0;
        $total_weight = 0;
        $sections = [];
        
        while ($row = mysqli_fetch_assoc($kpi_result)) {
            $score = $row['Score'];
            $weight = $row['weight'];
            $weighted_score = ($score / 5) * $weight;
            $total_weighted += $weighted_score;
            $total_weight += $weight;
            
            $section = $row['section'];
            if (!isset($sections[$section])) {
                $sections[$section] = [];
            }
            $sections[$section][] = $row;
        }
        
        $final_percentage = $total_weight > 0 ? ($total_weighted / $total_weight) * 100 : 0;
        $classification = classifyPerformance($final_percentage);
        $rating = getRatingLabel($classification);
        
        $comment_query = "SELECT * FROM kpi_comment WHERE Name = ? AND Year = ?";
        $stmt = mysqli_prepare($conn, $comment_query);
        mysqli_stmt_bind_param($stmt, "si", $selected_employee, $year);
        mysqli_stmt_execute($stmt);
        $comment_result = mysqli_stmt_get_result($stmt);
        $comments = mysqli_fetch_assoc($comment_result);
        
        $emp_query = "SELECT * FROM staff WHERE full_name = ?";
        $stmt = mysqli_prepare($conn, $emp_query);
        mysqli_stmt_bind_param($stmt, "s", $selected_employee);
        mysqli_stmt_execute($stmt);
        $emp_result = mysqli_stmt_get_result($stmt);
        $employee = mysqli_fetch_assoc($emp_result);
        ?>
        
        <div class="report-card-wrapper" id="reportCard">
            <div class="card-header-custom">
                <h3><i class="fas fa-user-circle me-2" style="color: var(--primary);"></i> Individual Employee KPI Report</h3>
                <div class="export-buttons no-print">
                    <button class="btn-export-pdf" onclick="exportToPDF()"><i class="fas fa-file-pdf"></i> PDF</button>
                    <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
                </div>
            </div>
            <div class="card-body-custom">
                <!-- Employee Selection Dropdown -->
                <div class="row mb-4 no-print">
                    <div class="col-md-4">
                        <label class="form-label">Select Employee</label>
                        <select class="employee-select form-select" onchange="location.href='?report_type=individual&year=<?php echo $year; ?>&employee=' + this.value">
                            <?php foreach ($employees_list as $emp): ?>
                                <option value="<?php echo $emp['name']; ?>" <?php echo $selected_employee == $emp['name'] ? 'selected' : ''; ?>>
                                    <?php echo $emp['name']; ?> (<?php echo $emp['score']; ?>%)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="text-center mb-4">
                    <h4>Annual Performance Appraisal <?php echo $year; ?></h4>
                </div>
                
                <!-- Employee Details -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="info-box">
                            <h5><i class="fas fa-user me-2"></i> Employee Information</h5>
                            <table style="width: 100%;">
                                <tr><td style="padding: 4px 0; width: 40%;"><strong>Full Name:</strong></td><td><?php echo $employee['full_name'] ?? $selected_employee; ?></td></tr>
                                <tr><td style="padding: 4px 0;"><strong>Staff ID:</strong></td><td><?php echo $employee['staff_code'] ?? 'N/A'; ?></td></tr>
                                <tr><td style="padding: 4px 0;"><strong>Department:</strong></td><td><?php echo $employee['department'] ?? 'N/A'; ?></td></tr>
                                <tr><td style="padding: 4px 0;"><strong>Position:</strong></td><td><?php echo $employee['position'] ?? 'N/A'; ?></td></tr>
                                <tr><td style="padding: 4px 0;"><strong>Review Period:</strong></td><td>January - December <?php echo $year; ?></td></tr>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-box text-center">
                            <h5>Final Rating</h5>
                            <div style="font-size: 3rem;"><?php echo $rating['icon']; ?></div>
                            <div class="rating-badge <?php echo $rating['class']; ?>" style="font-size: 1.1rem; padding: 6px 16px; display: inline-block;">
                                <?php echo $rating['label']; ?>
                            </div>
                            <div class="mt-2">
                                <strong>Score: <?php echo round($final_percentage, 1); ?>%</strong>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- KPI Breakdown by Section -->
                <?php foreach ($sections as $section_name => $items): ?>
                <div class="mb-4">
                    <h5><?php echo $section_name; ?></h5>
                    <div class="table-responsive">
                        <table class="performance-table">
                            <thead>
                                <tr><th>KPI Code</th><th>KPI Group</th><th>Description</th><th>Score (1-5)</th><th>Weight (%)</th><th>Weighted Score</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): 
                                    $weighted = ($item['Score'] / 5) * $item['weight'];
                                ?>
                                <tr>
                                    <td><?php echo $item['KPI_Code']; ?></td>
                                    <td><?php echo $item['kpi_group']; ?></td>
                                    <td><?php echo $item['kpi_description']; ?></td>
                                    <td><?php echo $item['Score']; ?> / 5</td>
                                    <td><?php echo $item['weight']; ?>%</td>
                                    <td><?php echo round($weighted, 1); ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <!-- Summary -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="stat-card" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark));">
                            <h5>Final Score Calculation</h5>
                            <p class="mb-1"><strong>Total Weighted Score:</strong> <?php echo round($total_weighted, 1); ?>% out of <?php echo $total_weight; ?>% total weight</p>
                            <p class="mb-1"><strong>Final Percentage:</strong> <?php echo round($final_percentage, 1); ?>%</p>
                            <p class="mb-0"><strong>Final Rating:</strong> <?php echo $rating['label']; ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Supervisor Comments -->
                <?php if ($comments): ?>
                <div class="mb-4">
                    <h5><i class="fas fa-comment me-2"></i> Supervisor Comments</h5>
                    <div class="info-box">
                        <p><strong>Supervisor Comments:</strong><br><?php echo nl2br(htmlspecialchars($comments['Supervisor Comments'])); ?></p>
                        <p class="mb-0"><strong>Training/Development Recommendations:</strong><br><?php echo nl2br(htmlspecialchars($comments['Training/Development Recommendations'])); ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Signature Section -->
                <div class="row mt-5">
                    <div class="col-md-6">
                        <hr>
                        <p><strong>Employee Signature:</strong> ___________________</p>
                        <p>Date: ___________</p>
                    </div>
                    <div class="col-md-6">
                        <hr>
                        <p><strong>Supervisor Signature:</strong> ___________________</p>
                        <p>Date: ___________</p>
                    </div>
                </div>
            </div>
        </div>
    <?php } else { ?>
        <div class="alert alert-warning">No employee data found for <?php echo $year; ?></div>
    <?php }
}

// 3. DEPARTMENT PERFORMANCE REPORT
elseif ($report_type == 'department') {
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
                'employee_count' => count($employees),
                'employees' => $employees
            ];
        }
    }
    
    usort($dept_scores, function($a, $b) {
        return $b['avg_score'] <=> $a['avg_score'];
    });
    
    $best_dept = !empty($dept_scores) ? $dept_scores[0] : null;
    $worst_dept = !empty($dept_scores) ? end($dept_scores) : null;
    ?>
    
    <div class="report-card-wrapper" id="reportCard">
        <div class="card-header-custom">
            <h3><i class="fas fa-building me-2" style="color: var(--primary);"></i> Department Performance Report</h3>
            <div class="export-buttons no-print">
                <button class="btn-export-pdf" onclick="exportToPDF()"><i class="fas fa-file-pdf"></i> PDF</button>
                <button class="btn-export-excel" onclick="exportToExcel()"><i class="fas fa-file-excel"></i> Excel</button>
                <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
            </div>
        </div>
        <div class="card-body-custom">
            <div class="text-center mb-4">
                <h4><?php echo $year; ?> Annual Review</h4>
            </div>
            
            <div class="mb-4">
                <div class="chart-container" style="height: 400px;">
                    <canvas id="deptChart"></canvas>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <div class="stat-card" style="background: linear-gradient(135deg, #06d6a0, #059669);">
                        <h5>🏆 Best Performing Department</h5>
                        <h3><?php echo $best_dept ? $best_dept['name'] : 'N/A'; ?></h3>
                        <p>Average Score: <?php echo $best_dept ? round($best_dept['avg_score'], 1) : '0'; ?>%</p>
                        <p class="mb-0">Staff Count: <?php echo $best_dept ? $best_dept['employee_count'] : '0'; ?></p>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="stat-card" style="background: linear-gradient(135deg, #ef476f, #c1121f);">
                        <h5>⚠️ Needs Improvement</h5>
                        <h3><?php echo $worst_dept ? $worst_dept['name'] : 'N/A'; ?></h3>
                        <p>Average Score: <?php echo $worst_dept ? round($worst_dept['avg_score'], 1) : '0'; ?>%</p>
                        <p class="mb-0">Staff Count: <?php echo $worst_dept ? $worst_dept['employee_count'] : '0'; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="mb-4">
                <h5>Department Ranking</h5>
                <div class="table-responsive">
                    <table class="performance-table">
                        <thead>
                            <tr><th>Rank</th><th>Department</th><th>Staff Count</th><th>Average Score</th><th>Rating</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dept_scores as $index => $dept): 
                                $classification = classifyPerformance($dept['avg_score']);
                                $rating = getRatingLabel($classification);
                            ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo $dept['name']; ?></td>
                                <td><?php echo $dept['employee_count']; ?></td>
                                <td><strong><?php echo round($dept['avg_score'], 1); ?>%</strong></td>
                                <td><span class="rating-badge <?php echo $rating['class']; ?>"><?php echo $rating['label']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php foreach ($dept_scores as $dept): ?>
            <div class="mb-4">
                <h5><?php echo $dept['name']; ?> - Staff Details</h5>
                <div class="table-responsive">
                    <table class="performance-table">
                        <thead><tr><th>Staff Name</th><th>Position</th><th>KPI Score</th><th>Rating</th></tr></thead>
                        <tbody>
                            <?php foreach ($dept['employees'] as $emp): ?>
                            <tr>
                                <td><?php echo $emp['name']; ?></td>
                                <td><?php echo $emp['position']; ?></td>
                                <td><?php echo $emp['score']; ?>%</strong></td>
                                <td><span class="rating-badge <?php echo $emp['rating']['class']; ?>"><?php echo $emp['rating']['label']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script>
    new Chart(document.getElementById('deptChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($dept_scores, 'name')); ?>,
            datasets: [{
                label: 'Average KPI Score (%)',
                data: <?php echo json_encode(array_map(function($d) { return round($d['avg_score'], 1); }, $dept_scores)); ?>,
                backgroundColor: '#4361ee',
                borderRadius: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, max: 100, title: { display: true, text: 'Score (%)' } } },
            plugins: { tooltip: { callbacks: { label: function(context) { return context.raw + '%'; } } } }
        }
    });
    </script>

<?php
}
// 4. PERFORMANCE TREND REPORT
elseif ($report_type == 'trend') {
    $trend_query = "SELECT YEAR(Date) as year, AVG(kd.Score) as avg_raw_score
                    FROM kpi_data kd
                    JOIN kpi_template_items kti ON kd.KPI_Code = kti.kpi_code AND kd.template_id = kti.template_id
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
    
    <div class="report-card-wrapper" id="reportCard">
        <div class="card-header-custom">
            <h3><i class="fas fa-chart-line me-2" style="color: var(--primary);"></i> Performance Trend Report</h3>
            <div class="export-buttons no-print">
                <button class="btn-export-pdf" onclick="exportToPDF()"><i class="fas fa-file-pdf"></i> PDF</button>
                <button class="btn-export-excel" onclick="exportToExcel()"><i class="fas fa-file-excel"></i> Excel</button>
                <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
            </div>
        </div>
        <div class="card-body-custom">
            <div class="text-center mb-4">
                <h4>Year-over-Year Analysis</h4>
            </div>
            
            <div class="mb-4">
                <div class="chart-container" style="height: 400px;">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
            
            <div class="alert <?php echo $overall_trend >= 0 ? 'alert-custom-success' : 'alert-custom-warning'; ?> mb-4" style="border-radius: 12px;">
                <h5><i class="fas fa-chart-line me-2"></i> Trend Analysis</h5>
                <p>Overall performance has <strong><?php echo $trend_direction; ?></strong> by <strong><?php echo $trend_percentage; ?>%</strong> over the analyzed period.</p>
                <p class="mb-0"><?php echo $overall_trend >= 0 ? '📈 Positive trajectory indicating effective performance management initiatives.' : '📉 Declining trend suggests need for performance improvement interventions.'; ?></p>
            </div>
            
            <div class="mb-4">
                <h5>Year-over-Year Comparison</h5>
                <div class="table-responsive">
                    <table class="performance-table">
                        <thead><tr><th>Year</th><th>Average KPI Score</th><th>Change from Previous Year</th><th>% Change</th><th>Trend</th></tr></thead>
                        <tbody>
                            <?php foreach ($years_data as $index => $year): ?>
                            <tr>
                                <td><strong><?php echo $year; ?></strong></td>
                                <td><?php echo $scores_data[$index]; ?>%</td>
                                <td><?php if ($index > 0) { $change = $scores_data[$index] - $scores_data[$index-1]; echo ($change >= 0 ? '+' : '') . round($change, 1) . '%'; } else { echo '-'; } ?></td>
                                <td><?php if ($index > 0) { $pct_change = ($scores_data[$index] - $scores_data[$index-1]) / $scores_data[$index-1] * 100; echo '<span class="' . ($pct_change >= 0 ? 'text-success' : 'text-danger') . '">' . ($pct_change >= 0 ? '+' : '') . round($pct_change, 1) . '%</span>'; } else { echo '-'; } ?></td>
                                <td><?php if ($index > 0) { echo $scores_data[$index] > $scores_data[$index-1] ? '<span class="text-success">📈 Improving</span>' : ($scores_data[$index] < $scores_data[$index-1] ? '<span class="text-danger">📉 Declining</span>' : '<span class="text-secondary">➡️ Stable</span>'); } else { echo '<span class="text-secondary">Base Year</span>'; } ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="mb-4">
                <h5>Department Performance Trends</h5>

                <div class="row">
                    <?php
                    $depts = ['Electronics', 'Fashion', 'Home & Living', 'Sports', 'Beauty & Health'];

                    foreach ($depts as $dept):
                        $dept_trend = [];

                        foreach ($years_data as $year) {
                            $employees = getEmployeeScores($conn, $year, $dept);
                            $dept_trend[$year] = count($employees) > 0
                                ? array_sum(array_column($employees, 'score')) / count($employees)
                                : null;
                        }

                        if (array_filter($dept_trend)):
                            $canvas_id = 'trend_' . str_replace(' ', '_', $dept);
                    ?>
                    
                    <div class="col-md-4 mb-4">
                        <h6 class="text-center"><?php echo $dept; ?></h6>
                        <div class="chart-container" style="height: 200px;">
                            <canvas id="<?php echo $canvas_id; ?>"></canvas>
                        </div>
                    </div>

                    <script>
                    new Chart(document.getElementById('<?php echo $canvas_id; ?>'), {
                        type: 'line',
                        data: {
                            labels: <?php echo json_encode($years_data); ?>,
                            datasets: [{
                                data: <?php echo json_encode(array_values($dept_trend)); ?>,
                                borderColor: '#4361ee',
                                fill: false,
                                tension: 0.4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } }
                        }
                    });
                    </script>

                    <?php endif; endforeach; ?>
                </div>
        </div>
    </div>
    
    <script>
    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($years_data); ?>,
            datasets: [{ label: 'Overall KPI Performance (%)', data: <?php echo json_encode($scores_data); ?>, borderColor: '#4361ee', backgroundColor: 'rgba(67, 97, 238, 0.1)', fill: true, tension: 0.4, pointRadius: 6, pointBackgroundColor: '#4361ee' }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, max: 100, title: { display: true, text: 'Score (%)' } } },
            plugins: { tooltip: { callbacks: { label: function(context) { return context.raw + '%'; } } } }
        }
    });
    </script>

<?php
}
// 5. LOW PERFORMANCE REPORT
elseif ($report_type == 'low') {
    $threshold = isset($_GET['threshold']) ? intval($_GET['threshold']) : 60;
    $employees = getEmployeeScores($conn, $year, $department);
    $low_performers = array_filter($employees, function($emp) use ($threshold) {
        return $emp['score'] < $threshold;
    });
    
    $low_performers_with_comments = [];
    foreach ($low_performers as $emp) {
        $comment_query = "SELECT * FROM kpi_comment WHERE Name = ? AND Year = ?";
        $stmt = mysqli_prepare($conn, $comment_query);
        mysqli_stmt_bind_param($stmt, "si", $emp['name'], $year);
        mysqli_stmt_execute($stmt);
        $comment_result = mysqli_stmt_get_result($stmt);
        $comments = mysqli_fetch_assoc($comment_result);
        $emp['comments'] = $comments;
        $low_performers_with_comments[] = $emp;
    }
    ?>
    
    <div class="report-card-wrapper" id="reportCard">
        <div class="card-header-custom">
            <h3><i class="fas fa-exclamation-triangle me-2" style="color: var(--warning);"></i> At-Risk Staff Report</h3>
            <div class="export-buttons no-print">
                <button class="btn-export-pdf" onclick="exportToPDF()"><i class="fas fa-file-pdf"></i> PDF</button>
                <button class="btn-export-excel" onclick="exportToExcel()"><i class="fas fa-file-excel"></i> Excel</button>
            </div>
        </div>
        <div class="card-body-custom">
            <div class="text-center mb-4">
                <h4>Staff Below <?php echo $threshold; ?>% Threshold - <?php echo $year; ?></h4>
            </div>
            
            <div class="row mb-4 no-print">
                <div class="col-md-3">
                    <label class="form-label">Performance Threshold (%)</label>
                    <select class="threshold-select form-select" onchange="location.href='?report_type=low&year=<?php echo $year; ?>&threshold=' + this.value">
                        <option value="50" <?php echo $threshold == 50 ? 'selected' : ''; ?>>Below 50%</option>
                        <option value="60" <?php echo $threshold == 60 ? 'selected' : ''; ?>>Below 60%</option>
                        <option value="70" <?php echo $threshold == 70 ? 'selected' : ''; ?>>Below 70%</option>
                    </select>
                </div>
            </div>
            
            <?php if (count($low_performers_with_comments) > 0): ?>
                <div class="alert-custom-warning mb-4">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i> Summary</h5>
                    <p>Total staff below <?php echo $threshold; ?>%: <strong><?php echo count($low_performers_with_comments); ?></strong> out of <?php echo count($employees); ?> employees (<?php echo round((count($low_performers_with_comments)/count($employees))*100,1); ?>%)</p>
                    <p class="mb-0">Immediate action required for these employees to prevent further performance decline.</p>
                </div>
                
                <div class="mb-4">
                    <h5>Risk Level</h5>
                    <div class="table-responsive">
                        <table class="performance-table">
                            <thead><tr><th>Staff Name</th><th>Department</th><th>KPI Score</th><th>Rating</th><th>Risk Level</th><th>Supervisor Comments</th><th>Recommended Action</th></tr></thead>
                            <tbody>
                                <?php foreach ($low_performers_with_comments as $emp): ?>
                                <tr>
                                    <td><?php echo $emp['name']; ?></td>
                                    <td><?php echo $emp['department']; ?></td>
                                    <td><span class="text-danger fw-bold"><?php echo $emp['score']; ?>%</span></td>
                                    <td><span class="rating-badge <?php echo $emp['rating']['class']; ?>"><?php echo $emp['rating']['label']; ?></span></td>
                                    <td>
                                    <?php 
                                    if ($emp['score'] < 50) {
                                        echo '<span class="badge bg-danger">🔴 High Risk</span>';
                                    } else {
                                        echo '<span class="badge bg-warning text-dark">🟡 Medium Risk</span>';
                                    }
                                    ?>
                                    </td>
                                    <td><?php echo isset($emp['comments']['Supervisor Comments']) ? htmlspecialchars(substr($emp['comments']['Supervisor Comments'], 0, 100)) : 'No comments recorded'; ?>...</td>
                                    <td><?php if ($emp['score'] < 40): ?><span class="badge bg-danger">⚠️ Performance Improvement Plan Required</span><?php elseif ($emp['score'] < 50): ?><span class="badge bg-warning text-dark">📋 Intensive Training Required</span><?php else: ?><span class="badge bg-info">🎯 Monitoring & Coaching Required</span><?php endif; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="stat-card" style="background: linear-gradient(135deg, #ef476f, #c1121f);">
                            <div class="stat-value"><?php echo count(array_filter($low_performers_with_comments, function($e) use ($threshold) { return $e['score'] < 40; })); ?></div>
                            <div>Need PIP (Score < 40%)</div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="stat-card" style="background: linear-gradient(135deg, #ffb703, #fb8500);">
                            <div class="stat-value"><?php echo count(array_filter($low_performers_with_comments, function($e) use ($threshold) { return $e['score'] >= 40 && $e['score'] < 50; })); ?></div>
                            <div>Need Intensive Training</div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="stat-card" style="background: linear-gradient(135deg, #118ab2, #0a5c7e);">
                            <div class="stat-value"><?php echo count(array_filter($low_performers_with_comments, function($e) use ($threshold) { return $e['score'] >= 50 && $e['score'] < $threshold; })); ?></div>
                            <div>Need Monitoring</div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert-custom-success">
                    <h5><i class="fas fa-check-circle me-2"></i> Excellent!</h5>
                    <p class="mb-0">No employees are performing below the <?php echo $threshold; ?>% threshold for <?php echo $year; ?>.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php
}
// 6. TOP PERFORMERS REPORT
elseif ($report_type == 'top') {
    $top_count = isset($_GET['top_count']) ? intval($_GET['top_count']) : 10;
    $employees = getEmployeeScores($conn, $year, $department);
    
    usort($employees, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });
    
    $top_performers = array_slice($employees, 0, $top_count);
    ?>
    
    <div class="report-card-wrapper" id="reportCard">
        <div class="card-header-custom">
            <h3><i class="fas fa-trophy me-2" style="color: var(--primary);"></i> Top Performers Recognition Report</h3>
            <div class="export-buttons no-print">
                <button class="btn-export-pdf" onclick="exportToPDF()"><i class="fas fa-file-pdf"></i> PDF</button>
                <button class="btn-export-excel" onclick="exportToExcel()"><i class="fas fa-file-excel"></i> Excel</button>
            </div>
        </div>
        <div class="card-body-custom">
            <div class="text-center mb-4">
                <h4><?php echo $year; ?> - Top <?php echo $top_count; ?> Employees</h4>
            </div>
            
            <div class="row mb-4 no-print">
                <div class="col-md-3">
                    <label class="form-label">Number of Top Performers</label>
                    <select class="threshold-select form-select" onchange="location.href='?report_type=top&year=<?php echo $year; ?>&top_count=' + this.value">
                        <option value="5" <?php echo $top_count == 5 ? 'selected' : ''; ?>>Top 5</option>
                        <option value="10" <?php echo $top_count == 10 ? 'selected' : ''; ?>>Top 10</option>
                        <option value="15" <?php echo $top_count == 15 ? 'selected' : ''; ?>>Top 15</option>
                    </select>
                </div>
            </div>
            
            <div class="row mb-4 text-center">
                <?php if (isset($top_performers[0])): ?>
                <div class="col-md-4 offset-md-4">
                    <div class="podium-gold">
                        <div style="font-size: 3rem;">🥇</div>
                        <h3><?php echo $top_performers[0]['name']; ?></h3>
                        <p><?php echo $top_performers[0]['department']; ?></p>
                        <div class="display-4 fw-bold"><?php echo $top_performers[0]['score']; ?>%</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="row mb-4">
                <?php if (isset($top_performers[1])): ?>
                <div class="col-md-6 mb-3">
                    <div class="podium-silver text-center">
                        <div style="font-size: 2rem;">🥈</div>
                        <h4><?php echo $top_performers[1]['name']; ?></h4>
                        <p><?php echo $top_performers[1]['department']; ?></p>
                        <div class="h2"><?php echo $top_performers[1]['score']; ?>%</div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (isset($top_performers[2])): ?>
                <div class="col-md-6 mb-3">
                    <div class="podium-bronze text-center">
                        <div style="font-size: 2rem;">🥉</div>
                        <h4><?php echo $top_performers[2]['name']; ?></h4>
                        <p><?php echo $top_performers[2]['department']; ?></p>
                        <div class="h2"><?php echo $top_performers[2]['score']; ?>%</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="mb-4">
                <h5>Top <?php echo $top_count; ?> Performers List</h5>
                <div class="table-responsive">
                    <table class="performance-table">
                        <thead><tr><th>Rank</th><th>Staff Name</th><th>Department</th><th>Position</th><th>KPI Score</th><th>Award Type</th></tr></thead>
                        <tbody>
                            <?php foreach ($top_performers as $index => $emp): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><strong><?php echo $emp['name']; ?></strong></td>
                                <td><?php echo $emp['department']; ?></td>
                                <td><?php echo $emp['position']; ?></td>
                                <td><strong><?php echo $emp['score']; ?>%</strong></td>
                                <td><?php if ($index == 0): ?><span class="badge bg-warning text-dark">🏆 Excellence Award</span><?php elseif ($index == 1): ?><span class="badge bg-secondary">⭐ Silver Award</span><?php elseif ($index == 2): ?><span class="badge" style="background: #cd7f32;">🌟 Bronze Award</span><?php else: ?><span class="badge bg-info">📝 Recognition Letter</span><?php endif; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="mb-4">
                <h5>Top Performers by Department</h5>
                <?php
                $dept_dist = [];
                foreach ($top_performers as $emp) {
                    $dept = $emp['department'];
                    if (!isset($dept_dist[$dept])) $dept_dist[$dept] = 0;
                    $dept_dist[$dept]++;
                }
                ?>
                <div class="chart-container" style="height: 300px;">
                    <canvas id="deptDistribution"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    new Chart(document.getElementById('deptDistribution'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_keys($dept_dist)); ?>,
            datasets: [{ label: 'Number of Top Performers', data: <?php echo json_encode(array_values($dept_dist)); ?>, backgroundColor: '#06d6a0', borderRadius: 10 }]
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, stepSize: 1, title: { display: true, text: 'Count' } } } }
    });
    </script>

<?php
}
//Training Needs Analysis Report
elseif ($report_type == 'training') {

    $employees = getEmployeeScores($conn, $year, $department);
                                    
    $training_needs = [];
    $staff_by_training = [];

    foreach ($employees as $emp) {

        // Get KPI details
        $query = "SELECT kti.kpi_group, kd.Score
                  FROM kpi_data kd
                  JOIN kpi_template_items kti 
                  ON kd.KPI_Code = kti.kpi_code AND kd.template_id = kti.template_id
                  WHERE kd.Name = ? AND YEAR(kd.Date) = ? AND kti.is_active = 1";

        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "si", $emp['name'], $year);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            if ($row['Score'] < 3) { // weak area

                $area = $row['kpi_group'];

                // count occurrences
                if (!isset($training_needs[$area])) {
                    $training_needs[$area] = 0;
                    $staff_by_training[$area] = [];
                }

                $training_needs[$area]++;
                $staff_by_training[$area][] = $emp['name'];
            }
        }
    }

    arsort($training_needs);
?>

<div class="report-card-wrapper" id="reportCard">
    <div class="card-header-custom">
        <h3>🧠 Training Needs Summary</h3>
    </div>

    <div class="card-body-custom">

        <!-- A. SUMMARY TABLE -->
        <h5>Most Needed Training Areas</h5>
        <table class="performance-table mb-4">
            <thead>
                <tr>
                    <th>Training Area</th>
                    <th>No. of Staff</th>
                    <th>Priority</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($training_needs as $area => $count): ?>
                <tr>
                    <td><?php echo $area; ?></td>
                    <td><?php echo $count; ?></td>
                    <td>
                        <?php
                        if ($count >= 8) echo '<span class="badge bg-danger">High</span>';
                        elseif ($count >= 4) echo '<span class="badge bg-warning text-dark">Medium</span>';
                        else echo '<span class="badge bg-info">Low</span>';
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- B. GROUPED STAFF -->
        <h5>Staff Grouped by Training Needs</h5>

        <div class="row">
            <?php foreach ($staff_by_training as $area => $staffs): ?>
            <div class="col-md-4 mb-4">
                <div class="info-box">
                    <h6><?php echo $area; ?></h6>
                    <ul class="mb-0">
                        <?php foreach ($staffs as $name): ?>
                            <li><?php echo $name; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- C. SMART SUGGESTIONS -->
        <h5>Suggested Training Programs</h5>
        <div class="info-box">
            <?php foreach ($training_needs as $area => $count): ?>
                <p>
                    <strong><?php echo $area; ?>:</strong>
                    Recommend workshop / coaching session for improvement.
                </p>
            <?php endforeach; ?>
        </div>

    </div>
</div>

<?php
}
// 7. CUSTOM REPORT BUILDER
elseif ($report_type == 'builder') {
    $custom_dept = isset($_GET['custom_dept']) ? $_GET['custom_dept'] : '';
    $custom_min_score = isset($_GET['custom_min_score']) ? intval($_GET['custom_min_score']) : 0;
    $custom_max_score = isset($_GET['custom_max_score']) ? intval($_GET['custom_max_score']) : 100;
    
    $employees = getEmployeeScores($conn, $year, $custom_dept);
    $filtered_employees = array_filter($employees, function($emp) use ($custom_min_score, $custom_max_score) {
        return $emp['score'] >= $custom_min_score && $emp['score'] <= $custom_max_score;
    });
    ?>
    
    <div class="report-card-wrapper" id="reportCard">
        <div class="card-header-custom">
            <h3><i class="fas fa-sliders-h me-2" style="color: var(--primary);"></i> Custom Report Builder</h3>
            <div class="export-buttons no-print">
                <button class="btn-export-pdf" onclick="exportToPDF()"><i class="fas fa-file-pdf"></i> PDF</button>
                <button class="btn-export-excel" onclick="exportToExcel()"><i class="fas fa-file-excel"></i> Excel</button>
            </div>
        </div>
        <div class="card-body-custom">
            <div class="text-center mb-4">
                <h4>Build Your Own Performance Report</h4>
            </div>
            
            <div class="info-box mb-4 no-print">
                <form method="GET" action="" class="row g-3 align-items-end">
                    <input type="hidden" name="report_type" value="builder">
                    <div class="col-md-3">
                        <label class="form-label">Year</label>
                        <select name="year" class="form-select">
                            <?php
                            $years_res = mysqli_query($conn, "SELECT DISTINCT YEAR(Date) as year FROM kpi_data ORDER BY year DESC");
                            while($yr = mysqli_fetch_assoc($years_res)):
                            ?>
                            <option value="<?php echo $yr['year']; ?>" <?php echo $year == $yr['year'] ? 'selected' : ''; ?>><?php echo $yr['year']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Department</label>
                        <select name="custom_dept" class="form-select">
                            <option value="">All Departments</option>
                            <?php
                            $depts_res = mysqli_query($conn, "SELECT DISTINCT department FROM staff WHERE department IS NOT NULL");
                            while($dept_row = mysqli_fetch_assoc($depts_res)):
                            ?>
                            <option value="<?php echo $dept_row['department']; ?>" <?php echo $custom_dept == $dept_row['department'] ? 'selected' : ''; ?>><?php echo $dept_row['department']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Min Score (%)</label>
                        <input type="number" name="custom_min_score" class="form-control" value="<?php echo $custom_min_score; ?>" min="0" max="100">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Max Score (%)</label>
                        <input type="number" name="custom_max_score" class="form-control" value="<?php echo $custom_max_score; ?>" min="0" max="100">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary-custom w-100">Apply Filters</button>
                    </div>
                </form>
            </div>
            
            <div class="info-box mb-4">
                <h5><i class="fas fa-filter me-2"></i> Current Filters</h5>
                <p class="mb-0"><strong>Year:</strong> <?php echo $year; ?> | <strong>Department:</strong> <?php echo $custom_dept ?: 'All'; ?> | <strong>Score Range:</strong> <?php echo $custom_min_score; ?>% - <?php echo $custom_max_score; ?>% | <strong>Results:</strong> <?php echo count($filtered_employees); ?> employees found</p>
            </div>
            
            <?php if (count($filtered_employees) > 0): ?>
            <div class="mb-4">
                <h5>Filtered Results</h5>
                <div class="table-responsive">
                    <table class="performance-table">
                        <thead><tr><th>Staff Name</th><th>Department</th><th>Position</th><th>KPI Score</th><th>Rating</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($filtered_employees as $emp): ?>
                            <tr>
                                <td><strong><?php echo $emp['name']; ?></strong></td>
                                <td><?php echo $emp['department']; ?></td>
                                <td><?php echo $emp['position']; ?></td>
                                <td><?php echo $emp['score']; ?>%</strong></td>
                                <td><span class="rating-badge <?php echo $emp['rating']['class']; ?>"><?php echo $emp['rating']['label']; ?></span></td>
                                <td><?php echo $emp['rating']['icon']; ?> <?php echo $emp['rating']['label']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <div class="alert alert-warning">No employees match the selected criteria.</div>
            <?php endif; ?>
        </div>
    </div>
<?php } ?>