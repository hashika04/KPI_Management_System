<?php
// reports.php
include("../includes/auth.php");
$activePage = 'reports'; // For sidebar highlighting

// Fetch all staff with their latest KPI data and comments
$sql = "SELECT 
        s.id,
        s.full_name,
        s.email,
        s.profile_photo,
        kd.Date,
        AVG(kd.Score) as avg_score,
        (AVG(kd.Score)/5)*100 as percentage
    FROM staff s
    LEFT JOIN kpi_data kd ON s.full_name = kd.Name
    WHERE kd.Date IS NOT NULL
    GROUP BY s.id, s.full_name, s.email, s.profile_photo, kd.Date
    ORDER BY s.full_name, kd.Date DESC";

$result = $conn->query($sql);

// Organize data by staff member with all their yearly scores
$staffData = [];
while ($row = $result->fetch_assoc()) {
    $staffId = $row['id'];
    if (!isset($staffData[$staffId])) {
        $staffData[$staffId] = [
            'id' => $row['id'],
            'name' => $row['full_name'],
            'email' => $row['email'],
            'avatar' => $row['profile_photo'] ?: '../asset/images/staff/default-profile.jpg',
            'scores' => []
        ];
    }
    if ($row['Date'] && $row['avg_score'] !== null) {
        $year = date('Y', strtotime($row['Date']));
        $staffData[$staffId]['scores'][$year] = [
            'score' => round($row['avg_score'], 1),
            'percentage' => round($row['percentage'])
        ];
    }
}

// Calculate current performance (using most recent year's data)
foreach ($staffData as $id => &$staff) {
    if (!empty($staff['scores'])) {
        $latestYear = max(array_keys($staff['scores']));
        $staff['currentScore'] = $staff['scores'][$latestYear]['score'];
        $staff['currentPercentage'] = $staff['scores'][$latestYear]['percentage'];
        $staff['latestYear'] = $latestYear;
        
        // Determine performance level
        if ($staff['currentScore'] >= 4.5) {
            $staff['performanceLevel'] = 'excellence';
            $staff['levelText'] = 'Excellence';
            $staff['levelColor'] = 'green';
        } elseif ($staff['currentScore'] >= 3.5) {
            $staff['performanceLevel'] = 'good';
            $staff['levelText'] = 'Good';
            $staff['levelColor'] = 'yellow';
        } elseif ($staff['currentScore'] >= 2.5) {
            $staff['performanceLevel'] = 'moderate';
            $staff['levelText'] = 'Moderate';
            $staff['levelColor'] = 'orange';
        } else {
            $staff['performanceLevel'] = 'at-risk';
            $staff['levelText'] = 'At Risk';
            $staff['levelColor'] = 'red';
        }
        
        // Calculate trend (compare with previous year)
        $years = array_keys($staff['scores']);
        rsort($years);
        if (count($years) >= 2) {
            $currentYear = $years[0];
            $previousYear = $years[1];
            $staff['trend'] = $staff['scores'][$currentYear]['score'] - $staff['scores'][$previousYear]['score'];
            $staff['previousScore'] = $staff['scores'][$previousYear]['score'];
            $staff['trendDirection'] = $staff['trend'] >= 0 ? 'up' : 'down';
        } else {
            $staff['trend'] = 0;
            $staff['previousScore'] = null;
            $staff['trendDirection'] = 'stable';
        }
    } else {
        $staff['currentScore'] = 0;
        $staff['currentPercentage'] = 0;
        $staff['performanceLevel'] = 'at-risk';
        $staff['levelText'] = 'No Data';
        $staff['levelColor'] = 'gray';
        $staff['trend'] = 0;
        $staff['trendDirection'] = 'stable';
    }
}

// Fetch KPI category scores for weak areas analysis
$categorySql = "SELECT 
        kd.Name,
        km.kpi_group,
        AVG(kd.Score) as avg_score,
        AVG(kd.Score)/5*100 as percentage
    FROM kpi_data kd
    JOIN kpi_master_list km ON kd.KPI_Code = km.kpi_code
    WHERE kd.Date IS NOT NULL
    GROUP BY kd.Name, km.kpi_group";
$categoryResult = $conn->query($categorySql);

$categoryScores = [];
while ($row = $categoryResult->fetch_assoc()) {
    $categoryScores[$row['Name']][$row['kpi_group']] = [
        'score' => round($row['avg_score'], 1),
        'percentage' => round($row['percentage'])
    ];
}

// Fetch comments for recommendations
$commentSql = "SELECT Name, `Supervisor Comments`, `Training/Development Recommendations` FROM kpi_comment WHERE Year = (SELECT MAX(Year) FROM kpi_comment)";
$commentResult = $conn->query($commentSql);
$comments = [];
while ($row = $commentResult->fetch_assoc()) {
    $comments[$row['Name']] = $row;
}

// Generate insights and analysis
$insights = [];
$atRiskStaff = [];
$decliningStaff = [];
$trainingNeeds = [];
$anomalies = [];

foreach ($staffData as $staff) {
    // At-risk staff
    if ($staff['performanceLevel'] === 'at-risk') {
        $atRiskStaff[] = $staff;
    }
    
    // Declining performance (trend down by more than 0.5)
    if ($staff['trendDirection'] === 'down' && $staff['trend'] < -0.5) {
        $decliningStaff[] = $staff;
    }
    
    // Weak areas analysis
    $weaknesses = [];
    if (isset($categoryScores[$staff['name']])) {
        foreach ($categoryScores[$staff['name']] as $group => $scores) {
            if ($scores['percentage'] < 70) {
                $weaknesses[] = $group;
            }
        }
    }
    
    // Anomalies detection
    if ($staff['trendDirection'] === 'down' && $staff['trend'] < -1) {
        $anomalies[] = [
            'type' => 'Sudden Drop',
            'staff' => $staff['name'],
            'description' => "KPI dropped from {$staff['previousScore']} to {$staff['currentScore']} (" . abs($staff['trend']) . " point decline)",
            'severity' => 'high'
        ];
    }
    
    if (count($weaknesses) >= 3) {
        $anomalies[] = [
            'type' => 'Multiple Weak Areas',
            'staff' => $staff['name'],
            'description' => "Underperforming in " . count($weaknesses) . " categories: " . implode(', ', array_slice($weaknesses, 0, 3)),
            'severity' => 'medium'
        ];
    }
    
    // Training needs
    if (!empty($weaknesses)) {
        $trainingNeeds[] = [
            'name' => $staff['name'],
            'weaknesses' => $weaknesses,
            'recommendations' => isset($comments[$staff['name']]) ? $comments[$staff['name']]['Training/Development Recommendations'] : 'Performance coaching recommended'
        ];
    }
}

// Generate automated insights
if (count($atRiskStaff) > 0) {
    $insights[] = [
        'type' => 'critical',
        'title' => 'Staff Members at Risk',
        'description' => count($atRiskStaff) . ' staff members are currently performing below expectations and require immediate attention.',
        'priority' => 'high',
        'staff' => $atRiskStaff
    ];
}

if (count($decliningStaff) > 0) {
    $insights[] = [
        'type' => 'warning',
        'title' => 'Declining Performance Trend',
        'description' => count($decliningStaff) . ' staff members have shown a decline in performance compared to the previous year.',
        'priority' => 'medium',
        'staff' => $decliningStaff
    ];
}

if (count($trainingNeeds) > 0) {
    $insights[] = [
        'type' => 'info',
        'title' => 'Training Needs Identified',
        'description' => count($trainingNeeds) . ' staff members require training interventions to improve performance.',
        'priority' => 'medium',
        'staff' => $trainingNeeds
    ];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reports & Insights - KPI Dashboard</title>
    <link rel="stylesheet" href="../asset/universal.css">
    <link rel="stylesheet" href="../asset/dashboard.css">
    <link rel="stylesheet" href="../asset/reports.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

<div class="dashboard">
    <?php include("../includes/sidebar.php"); ?>

    <div class="reports-content">
        <!-- Header -->
        <div class="reports-header">
            <div>
                <h1>Reports & Insights</h1>
                <p class="reports-subtitle">Automated analysis and recommendations based on KPI data</p>
            </div>
            <button class="btn-export" onclick="window.print()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>
                </svg>
                Export Report
            </button>
        </div>

        <!-- Automated Insights Section -->
        <div class="insights-section">
            <div class="section-header">
                <div class="section-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                    </svg>
                </div>
                <h2>Automated Insights</h2>
            </div>
            
            <div class="insights-grid">
                <?php foreach ($insights as $insight): ?>
                    <div class="insight-card insight-<?php echo $insight['type']; ?>">
                        <div class="insight-header">
                            <span class="insight-priority priority-<?php echo $insight['priority']; ?>">
                                <?php echo ucfirst($insight['priority']); ?> Priority
                            </span>
                            <span class="insight-badge insight-<?php echo $insight['type']; ?>-badge">
                                <?php echo $insight['type'] === 'critical' ? '⚠️ Critical' : ($insight['type'] === 'warning' ? '📉 Warning' : 'ℹ️ Info'); ?>
                            </span>
                        </div>
                        <h3><?php echo htmlspecialchars($insight['title']); ?></h3>
                        <p><?php echo htmlspecialchars($insight['description']); ?></p>
                        <?php if (!empty($insight['staff'])): ?>
                            <div class="insight-staff-list">
                                <?php 
                                $displayCount = 0;
                                foreach ($insight['staff'] as $staffMember): 
                                    if ($displayCount >= 5) break;
                                    
                                    // Handle different staff data structures
                                    if (is_array($staffMember)) {
                                        // Check if we have staff ID
                                        if (isset($staffMember['id']) && isset($staffMember['name'])) {
                                            $staffId = $staffMember['id'];
                                            $staffName = $staffMember['name'];
                                            $staffAvatar = isset($staffMember['avatar']) ? $staffMember['avatar'] : '../asset/images/staff/default-profile.jpg';
                                            $displayCount++;
                                            ?>
                                            <a href="staff_profile.php?id=<?php echo $staffId; ?>" class="insight-staff-tag">
                                                <img src="<?php echo htmlspecialchars($staffAvatar); ?>" alt="">
                                                <span><?php echo htmlspecialchars($staffName); ?></span>
                                            </a>
                                        <?php 
                                        }
                                        // For training needs - find staff ID from staffData by name
                                        elseif (isset($staffMember['name']) && !isset($staffMember['id'])) {
                                            $staffName = $staffMember['name'];
                                            $staffId = null;
                                            $staffAvatar = '../asset/images/staff/default-profile.jpg';
                                            
                                            // Find staff ID from the staffData array
                                            foreach ($staffData as $staff) {
                                                if ($staff['name'] === $staffName) {
                                                    $staffId = $staff['id'];
                                                    $staffAvatar = $staff['avatar'];
                                                    break;
                                                }
                                            }
                                            
                                            $displayCount++;
                                            if ($staffId) {
                                                ?>
                                                <a href="staff_profile.php?id=<?php echo $staffId; ?>" class="insight-staff-tag">
                                                    <img src="<?php echo htmlspecialchars($staffAvatar); ?>" alt="">
                                                    <span><?php echo htmlspecialchars($staffName); ?></span>
                                                </a>
                                            <?php 
                                            } else {
                                                ?>
                                                <span class="insight-staff-tag">
                                                    <img src="<?php echo htmlspecialchars($staffAvatar); ?>" alt="">
                                                    <span><?php echo htmlspecialchars($staffName); ?></span>
                                                </span>
                                            <?php 
                                            }
                                        }
                                    } 
                                    // If it's a string (just name)
                                    elseif (is_string($staffMember)) {
                                        $staffName = $staffMember;
                                        $staffId = null;
                                        $staffAvatar = '../asset/images/staff/default-profile.jpg';
                                        
                                        // Find staff ID from the staffData array
                                        foreach ($staffData as $staff) {
                                            if ($staff['name'] === $staffName) {
                                                $staffId = $staff['id'];
                                                $staffAvatar = $staff['avatar'];
                                                break;
                                            }
                                        }
                                        
                                        $displayCount++;
                                        if ($staffId) {
                                            ?>
                                            <a href="staff_profile.php?id=<?php echo $staffId; ?>" class="insight-staff-tag">
                                                <img src="<?php echo htmlspecialchars($staffAvatar); ?>" alt="">
                                                <span><?php echo htmlspecialchars($staffName); ?></span>
                                            </a>
                                        <?php 
                                        } else {
                                            ?>
                                            <span class="insight-staff-tag">
                                                <img src="<?php echo htmlspecialchars($staffAvatar); ?>" alt="">
                                                <span><?php echo htmlspecialchars($staffName); ?></span>
                                            </span>
                                        <?php 
                                        }
                                    }
                                    endforeach; 
                                ?>
                                <?php if (count($insight['staff']) > 5): ?>
                                    <span class="insight-more">+<?php echo count($insight['staff']) - 5; ?> more</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <!-- At-Risk Staff Report -->
        <?php if (!empty($atRiskStaff)): ?>
        <div class="report-card">
            <div class="report-card-header">
                <div class="report-icon warning-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <h2>At-Risk Staff Report</h2>
            </div>
            
            <div class="staff-risk-list">
                <?php foreach ($atRiskStaff as $staff): ?>
                    <div class="risk-card">
                        <div class="risk-card-header">
                            <div class="risk-avatar">
                                <img src="<?php echo htmlspecialchars($staff['avatar']); ?>" alt="">
                            </div>
                            <div class="risk-info">
                                <a href="staff_profile.php?id=<?php echo $staff['id']; ?>" class="risk-name">
                                    <?php echo htmlspecialchars($staff['name']); ?>
                                </a>
                                <p class="risk-email"><?php echo htmlspecialchars($staff['email']); ?></p>
                            </div>
                            <div class="risk-score">
                                <span class="risk-score-value"><?php echo $staff['currentScore']; ?></span>
                                <span class="risk-score-label">/5</span>
                                <span class="risk-badge risk-<?php echo $staff['levelColor']; ?>">
                                    <?php echo $staff['levelText']; ?>
                                </span>
                            </div>
                        </div>
                        
                        <?php
                        // Get weak areas for this staff
                        $staffWeaknesses = [];
                        if (isset($categoryScores[$staff['name']])) {
                            foreach ($categoryScores[$staff['name']] as $group => $scores) {
                                if ($scores['percentage'] < 70) {
                                    $staffWeaknesses[] = $group;
                                }
                            }
                        }
                        ?>
                        <?php if (!empty($staffWeaknesses)): ?>
                            <div class="risk-weaknesses">
                                <p><strong>Weak Areas:</strong></p>
                                <div class="weakness-tags">
                                    <?php foreach ($staffWeaknesses as $weakness): ?>
                                        <span class="weakness-tag"><?php echo htmlspecialchars($weakness); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="risk-recommendations">
                            <p><strong>Recommended Actions:</strong></p>
                            <ul>
                                <?php if (isset($comments[$staff['name']]['Training/Development Recommendations']) && !empty($comments[$staff['name']]['Training/Development Recommendations'])): ?>
                                    <li><?php echo htmlspecialchars($comments[$staff['name']]['Training/Development Recommendations']); ?></li>
                                <?php else: ?>
                                    <li>Performance improvement plan review</li>
                                    <li>Weekly coaching sessions</li>
                                    <li>Skill development workshop</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Declining Performance Report -->
        <?php if (!empty($decliningStaff)): ?>
        <div class="report-card">
            <div class="report-card-header">
                <div class="report-icon decline-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/>
                    </svg>
                </div>
                <h2>Declining Performance Report</h2>
            </div>
            
            <div class="table-responsive">
                <table class="decline-table">
                    <thead>
                        <tr>
                            <th>Staff Member</th>
                            <th>Previous Score</th>
                            <th>Current Score</th>
                            <th>Change</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($decliningStaff as $staff): ?>
                            <tr>
                                <td>
                                    <div class="staff-cell">
                                        <img src="<?php echo htmlspecialchars($staff['avatar']); ?>" alt="" class="staff-avatar-sm">
                                        <div>
                                            <div class="staff-name"><?php echo htmlspecialchars($staff['name']); ?></div>
                                            <div class="staff-dept">Sales Associate</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="score-cell"><?php echo $staff['previousScore'] ? number_format($staff['previousScore'], 1) : '-'; ?></td>
                                <td class="score-cell decline-value"><?php echo number_format($staff['currentScore'], 1); ?></td>
                                <td class="change-cell decline-change">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/>
                                    </svg>
                                    <?php echo number_format(abs($staff['trend']), 1); ?>
                                </td>
                                <td>
                                    <a href="staff_profile.php?id=<?php echo $staff['id']; ?>" class="btn-outline-sm">Review Profile</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Training Needs Analysis -->
        <?php if (!empty($trainingNeeds)): ?>
        <div class="report-card">
            <div class="report-card-header">
                <div class="report-icon training-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                </div>
                <h2>Training Needs Analysis</h2>
            </div>
            
            <?php
            // Group by weakness category
            $groupedNeeds = [];
            foreach ($trainingNeeds as $need) {
                foreach ($need['weaknesses'] as $weakness) {
                    if (!isset($groupedNeeds[$weakness])) {
                        $groupedNeeds[$weakness] = [];
                    }
                    $groupedNeeds[$weakness][] = $need['name'];
                }
            }
            ?>
            
            <div class="training-needs-grid">
                <?php foreach ($groupedNeeds as $category => $staffNames): ?>
                    <div class="training-card">
                        <div class="training-card-header">
                            <h3><?php echo htmlspecialchars($category); ?></h3>
                            <span class="training-count"><?php echo count($staffNames); ?> staff members</span>
                        </div>
                        <p class="training-desc">Staff requiring training in this area:</p>
                        <div class="training-staff-list">
                            <?php foreach (array_slice($staffNames, 0, 5) as $name): ?>
                                <span class="training-staff-name"><?php echo htmlspecialchars($name); ?></span>
                            <?php endforeach; ?>
                            <?php if (count($staffNames) > 5): ?>
                                <span class="training-more">+<?php echo count($staffNames) - 5; ?> more</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Anomalies Detection -->
        <?php if (!empty($anomalies)): ?>
        <div class="report-card">
            <div class="report-card-header">
                <div class="report-icon anomaly-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <h2>Anomalies & Alerts</h2>
            </div>
            
            <div class="anomalies-list">
                <?php foreach ($anomalies as $anomaly): ?>
                    <div class="anomaly-item anomaly-<?php echo $anomaly['severity']; ?>">
                        <div class="anomaly-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                        <div class="anomaly-content">
                            <div class="anomaly-header">
                                <span class="anomaly-badge"><?php echo htmlspecialchars($anomaly['type']); ?></span>
                                <span class="anomaly-staff"><?php echo htmlspecialchars($anomaly['staff']); ?></span>
                            </div>
                            <p class="anomaly-description"><?php echo htmlspecialchars($anomaly['description']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Summary Stats -->
        <div class="summary-stats">
            <div class="summary-card">
                <div class="summary-value"><?php echo count($atRiskStaff); ?></div>
                <div class="summary-label">Staff members at risk</div>
            </div>
            <div class="summary-card">
                <div class="summary-value"><?php echo count($trainingNeeds); ?></div>
                <div class="summary-label">Training interventions needed</div>
            </div>
            <div class="summary-card">
                <div class="summary-value"><?php echo count($anomalies); ?></div>
                <div class="summary-label">Anomalies detected</div>
            </div>
            <div class="summary-card">
                <div class="summary-value"><?php echo count($staffData); ?></div>
                <div class="summary-label">Total staff evaluated</div>
            </div>
        </div>
    </div>
</div>

</body>
</html>