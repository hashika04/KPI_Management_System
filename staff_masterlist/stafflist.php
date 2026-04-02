<?php
// staff_masterlist/staff_list.php
include("../includes/auth.php");
$activePage = 'staff';

// Fetch all staff with their basic info
$sql = "SELECT 
        s.id,
        s.full_name,
        s.email,
        s.profile_photo
    FROM staff s
    ORDER BY s.full_name";

$result = $conn->query($sql);

// Get the latest year from kpi_data
$yearSql = "SELECT DISTINCT YEAR(Date) as year FROM kpi_data WHERE Date IS NOT NULL ORDER BY year DESC";
$yearResult = $conn->query($yearSql);
$years = [];
while ($row = $yearResult->fetch_assoc()) {
    $years[] = $row['year'];
}

// Get current year (latest) and previous year
$currentYear = isset($years[0]) ? $years[0] : 2025;
$prevYear = isset($years[1]) ? $years[1] : 2024;

// Debug: Uncomment to see what years are being used
// echo "Current Year: $currentYear, Previous Year: $prevYear<br>";

// Fetch current year KPI scores
$currentSql = "SELECT 
        Name,
        ROUND(AVG(Score),2) AS avg_score,
        ROUND((AVG(Score)/5)*100,2) AS avg_percentage
    FROM kpi_data 
    WHERE YEAR(Date) = $currentYear
    GROUP BY Name";
$currentResult = $conn->query($currentSql);

$currentScores = [];
while ($row = $currentResult->fetch_assoc()) {
    $currentScores[$row['Name']] = [
        'score' => $row['avg_score'],
        'percentage' => $row['avg_percentage']
    ];
}

// Fetch previous year KPI scores for trend
$prevSql = "SELECT 
        Name,
        ROUND(AVG(Score),2) AS avg_score
    FROM kpi_data 
    WHERE YEAR(Date) = $prevYear
    GROUP BY Name";
$prevResult = $conn->query($prevSql);

$prevScores = [];
while ($row = $prevResult->fetch_assoc()) {
    $prevScores[$row['Name']] = $row['avg_score'];
}

// Build staff data array
$staffData = [];
while ($row = $result->fetch_assoc()) {
    $name = $row['full_name'];
    $currentScore = isset($currentScores[$name]) ? $currentScores[$name]['score'] : 0;
    $currentPercentage = isset($currentScores[$name]) ? $currentScores[$name]['percentage'] : 0;
    $previousScore = isset($prevScores[$name]) ? $prevScores[$name] : null;
    
    // Calculate trend
    if ($previousScore && $currentScore > 0) {
        $trendValue = $currentScore - $previousScore;
        if ($trendValue > 0.3) {
            $trend = 'up';
            $trendText = 'Improving';
        } elseif ($trendValue < -0.3) {
            $trend = 'down';
            $trendText = 'Declining';
        } else {
            $trend = 'stable';
            $trendText = 'Stable';
        }
    } else {
        $trend = 'stable';
        $trendText = 'Stable';
    }
    
    // Determine performance level
    if ($currentScore >= 4.5) {
        $levelText = 'Excellence';
        $levelColor = 'green';
    } elseif ($currentScore >= 3.5) {
        $levelText = 'Good';
        $levelColor = 'blue';
    } elseif ($currentScore >= 2.5) {
        $levelText = 'Moderate';
        $levelColor = 'orange';
    } elseif ($currentScore > 0) {
        $levelText = 'At Risk';
        $levelColor = 'red';
    } else {
        $levelText = 'No Data';
        $levelColor = 'gray';
    }
    
    // Fix avatar path
    $avatarPath = '../asset/images/staff/default-profile.jpg';
    if (!empty($row['profile_photo'])) {
        // Check if the path already has the correct format
        if (strpos($row['profile_photo'], '../') === 0) {
            $avatarPath = $row['profile_photo'];
        } else {
            $avatarPath = '../' . $row['profile_photo'];
        }
    }
    
    $staffData[] = [
        'id' => $row['id'],
        'name' => $row['full_name'],
        'email' => $row['email'],
        'avatar' => $avatarPath,
        'staff_id' => 'STF' . str_pad($row['id'], 3, '0', STR_PAD_LEFT),
        'current_score' => $currentScore ? number_format($currentScore, 1) : '0.0',
        'current_percentage' => $currentPercentage ? round($currentPercentage) : 0,
        'previous_score' => $previousScore ? number_format($previousScore, 1) : null,
        'trend' => $trend,
        'trend_text' => $trendText,
        'level_text' => $levelText,
        'level_color' => $levelColor,
        'department' => 'Sales Associate'
    ];
}

// Debug: Uncomment to see what data is being fetched
/*
echo "<pre>";
echo "Current Year: $currentYear<br>";
echo "Previous Year: $prevYear<br><br>";
echo "Current Scores:<br>";
print_r($currentScores);
echo "<br>Previous Scores:<br>";
print_r($prevScores);
echo "<br>Staff Data:<br>";
print_r($staffData);
echo "</pre>";
exit();
*/
?>

<!DOCTYPE html>
<html>
<head>
    <title>Staff Profiles - KPI Dashboard</title>
    <link rel="stylesheet" href="../asset/universal.css">
    <link rel="stylesheet" href="../asset/dashboard.css">
    <link rel="stylesheet" href="../asset/staff_list.css">
</head>
<body>

<div class="dashboard">
    <?php include("../includes/sidebar.php"); ?>

    <div class="staff-list-content">
        <!-- Header -->
        <div class="staff-list-header">
            <div>
                <h1>Staff Profiles</h1>
                <p class="subtitle">View and manage individual performance</p>
            </div>
        </div>

        <!-- Filters & Search -->
        <div class="filters-card">
            <div class="filters-grid">
                <div class="search-wrapper">
                    <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" id="staffSearch" placeholder="Search by name or staff ID..." class="search-input">
                </div>

                <select id="departmentFilter" class="filter-select">
                    <option value="all">All Departments</option>
                    <option value="sales">Sales</option>
                    <option value="marketing">Marketing</option>
                    <option value="operations">Operations</option>
                </select>

                <select id="sortBy" class="filter-select">
                    <option value="kpi">Sort by KPI</option>
                    <option value="name">Sort by Name</option>
                    <option value="trend">Sort by Trend</option>
                </select>
            </div>
        </div>

        <!-- Staff Table -->
        <div class="staff-table-container">
            <div class="staff-table-wrapper">
                <table class="staff-table">
                    <thead>
                        <tr>
                            <th>Staff</th>
                            <th>Department</th>
                            <th>Current KPI</th>
                            <th>Trend</th>
                            <th>Performance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="staffTableBody">
                        <?php foreach ($staffData as $staff): ?>
                        <tr class="staff-row" 
                            data-name="<?php echo strtolower(htmlspecialchars($staff['name'])); ?>"
                            data-staff-id="<?php echo strtolower(htmlspecialchars($staff['staff_id'])); ?>"
                            data-department="<?php echo strtolower($staff['department']); ?>"
                            data-kpi="<?php echo floatval($staff['current_score']); ?>"
                            data-trend="<?php echo $staff['trend']; ?>">
                            
                            <td class="staff-cell">
                                <a href="staff_profile.php?id=<?php echo $staff['id']; ?>" class="staff-link">
                                    <div class="staff-avatar-sm">
                                        <img src="<?php echo htmlspecialchars($staff['avatar']); ?>" 
                                             alt="<?php echo htmlspecialchars($staff['name']); ?>"
                                             onerror="this.src='../asset/images/staff/default-profile.jpg'">
                                    </div>
                                    <div>
                                        <div class="staff-name"><?php echo htmlspecialchars($staff['name']); ?></div>
                                        <div class="staff-id"><?php echo $staff['staff_id']; ?></div>
                                    </div>
                                </a>
                            </td>
                            
                            <td class="department-cell">
                                <div class="staff-dept"><?php echo $staff['department']; ?></div>
                                <div class="staff-role">Staff</div>
                            </td>
                            
                            <td class="kpi-cell">
                                <div class="kpi-score <?php 
                                    $score = floatval($staff['current_score']);
                                    echo $score >= 4 ? 'score-high' : ($score >= 3 ? 'score-medium' : 'score-low'); 
                                ?>">
                                    <?php echo $staff['current_score']; ?>
                                </div>
                                <?php if ($staff['previous_score']): ?>
                                <div class="prev-score">Prev: <?php echo $staff['previous_score']; ?></div>
                                <?php endif; ?>
                            </td>
                            
                            <td class="trend-cell">
                                <div class="trend-badge trend-<?php echo $staff['trend']; ?>">
                                    <?php if ($staff['trend'] == 'up'): ?>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                        </svg>
                                        <span>Improving</span>
                                    <?php elseif ($staff['trend'] == 'down'): ?>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/>
                                        </svg>
                                        <span>Declining</span>
                                    <?php else: ?>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M5 12h14"/>
                                        </svg>
                                        <span>Stable</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            
                            <td class="performance-cell">
                                <span class="performance-badge performance-<?php echo $staff['level_color']; ?>">
                                    <?php echo $staff['level_text']; ?>
                                </span>
                            </td>
                            
                            <td class="actions-cell">
                                <div class="action-buttons">
                                    <button class="btn-add-kpi" onclick="openAddKPIModal(<?php echo $staff['id']; ?>, '<?php echo htmlspecialchars($staff['name']); ?>')">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M12 5v14M5 12h14"/>
                                        </svg>
                                        Add KPI
                                    </button>
                                    <a href="staff_profile.php?id=<?php echo $staff['id']; ?>" class="btn-view">View</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="table-footer">
                <span id="staffCount" class="staff-count">Showing <?php echo count($staffData); ?> of <?php echo count($staffData); ?> staff members</span>
            </div>
        </div>
    </div>
</div>

<!-- Add KPI Modal -->
<div id="addKPIModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add KPI Score</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form id="addKPIForm" action="add_kpi.php" method="POST">
            <input type="hidden" name="staff_id" id="modalStaffId">
            <input type="hidden" name="staff_name" id="modalStaffName">
            
            <div class="form-group">
                <label>Staff Member</label>
                <input type="text" id="modalStaffNameDisplay" readonly>
            </div>
            
            <div class="form-group">
                <label>KPI Category</label>
                <select name="kpi_code" required>
                    <option value="">Select KPI Category</option>
                    <?php
                    $kpiSql = "SELECT kpi_code, kpi_group, kpi_description FROM kpi_master_list ORDER BY kpi_group";
                    $kpiResult = $conn->query($kpiSql);
                    $currentGroup = '';
                    while ($kpi = $kpiResult->fetch_assoc()) {
                        if ($currentGroup != $kpi['kpi_group']) {
                            if ($currentGroup != '') echo '</optgroup>';
                            echo '<optgroup label="' . htmlspecialchars($kpi['kpi_group']) . '">';
                            $currentGroup = $kpi['kpi_group'];
                        }
                        echo '<option value="' . $kpi['kpi_code'] . '">' . htmlspecialchars($kpi['kpi_description']) . '</option>';
                    }
                    if ($currentGroup != '') echo '</optgroup>';
                    ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Score (1-5)</label>
                <input type="number" name="score" min="1" max="5" step="0.5" required>
            </div>
            
            <div class="form-group">
                <label>Date</label>
                <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-submit">Add Score</button>
            </div>
        </form>
    </div>
</div>

<script src="staff_list.js"></script>

</body>
</html>