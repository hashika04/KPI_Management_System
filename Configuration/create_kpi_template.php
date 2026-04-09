<?php
// staff_masterlist/create_kpi_template.php
include("../includes/auth.php");
include("../config/db.php");
$activePage = 'config';

// Check if user is supervisor
if ($_SESSION['position'] !== 'Supervisor') {
    header("Location: stafflist.php");
    exit();
}

$template_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$is_edit = $template_id > 0;
$error_message = '';
$success_message = '';

// Get previous year template for new template creation
$previous_year_template = null;
$previous_year_items = [];

if (!$is_edit) {
    // Get the latest template (usually previous year)
    $prev_sql = "SELECT * FROM kpi_templates WHERE year = (SELECT MAX(year) FROM kpi_templates WHERE status != 'archived')";
    $prev_result = $conn->query($prev_sql);
    $previous_year_template = $prev_result->fetch_assoc();
    
    if ($previous_year_template) {
        // Fetch items from previous template
        $prev_items_sql = "SELECT * FROM kpi_template_items WHERE template_id = ? ORDER BY section, display_order";
        $prev_items_stmt = $conn->prepare($prev_items_sql);
        $prev_items_stmt->bind_param("i", $previous_year_template['id']);
        $prev_items_stmt->execute();
        $prev_items_result = $prev_items_stmt->get_result();
        while($item = $prev_items_result->fetch_assoc()) {
            $previous_year_items[] = $item;
        }
    }
}

// Fetch existing template data if editing
$template_data = null;
$template_items = [];
if ($is_edit) {
    $sql = "SELECT * FROM kpi_templates WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $template_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $template_data = $result->fetch_assoc();
    
    if (!$template_data) {
        header("Location: kpi_template_management.php");
        exit();
    }
    
    // Fetch template items
    $items_sql = "SELECT * FROM kpi_template_items WHERE template_id = ? ORDER BY section, display_order";
    $items_stmt = $conn->prepare($items_sql);
    $items_stmt->bind_param("i", $template_id);
    $items_stmt->execute();
    $template_items_result = $items_stmt->get_result();
    while($item = $template_items_result->fetch_assoc()) {
        $template_items[] = $item;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $template_name = $_POST['template_name'];
    $year = intval($_POST['year']);
    $section1_weight = floatval($_POST['section1_weight']);
    $section2_weight = floatval($_POST['section2_weight']);
    
    // Validate total weight
    if (abs(($section1_weight + $section2_weight) - 100) > 0.01) {
        $error_message = "Total weight must equal 100% (currently " . ($section1_weight + $section2_weight) . "%)";
    } else {
        if ($is_edit) {
            // Update template
            $sql = "UPDATE kpi_templates SET template_name = ?, year = ?, section1_weight = ?, section2_weight = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("siddi", $template_name, $year, $section1_weight, $section2_weight, $template_id);
            
            if ($stmt->execute()) {
                // Delete existing items
                $delete_sql = "DELETE FROM kpi_template_items WHERE template_id = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bind_param("i", $template_id);
                $delete_stmt->execute();
                
                $success_message = "Template updated successfully!";
            } else {
                $error_message = "Error updating template: " . $conn->error;
            }
        } else {
            // Check if year already exists
            $check_sql = "SELECT id FROM kpi_templates WHERE year = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $year);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error_message = "A template for year $year already exists!";
            } else {
                // Insert new template
                $sql = "INSERT INTO kpi_templates (template_name, year, section1_weight, section2_weight, status, created_by) 
                        VALUES (?, ?, ?, ?, 'inactive', ?)";
                $stmt = $conn->prepare($sql);
                $created_by = $_SESSION['full_name'];
                $stmt->bind_param("sidds", $template_name, $year, $section1_weight, $section2_weight, $created_by);
                
                if ($stmt->execute()) {
                    $template_id = $conn->insert_id;
                    $success_message = "Template created successfully!";
                } else {
                    $error_message = "Error creating template: " . $conn->error;
                }
            }
        }
        
        // Process KPI items
        if (empty($error_message) && isset($_POST['kpi_groups'])) {
            $kpi_groups = json_decode($_POST['kpi_groups'], true);
            $insert_sql = "INSERT INTO kpi_template_items (template_id, kpi_code, section, kpi_group, kpi_description, weight, display_order) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            
            $display_order = 0;
            
            foreach ($kpi_groups as $group) {
                $section = $group['section'];
                $kpi_group = $group['kpi_group'];
                $targets = $group['targets'];
                $kpi_code_prefix = $group['kpi_code_prefix'];
                
                if ($section == 'Section 1') {
                    foreach ($targets as $target_index => $target_data) {
                        $kpi_code = $kpi_code_prefix . ($target_index + 1);
                        $weight = floatval($target_data['weight']);
                        $description = $target_data['description'];
                        $current_order = $display_order++;
                        
                        $insert_stmt->bind_param("issssdi", 
                            $template_id, 
                            $kpi_code, 
                            $section, 
                            $kpi_group, 
                            $description, 
                            $weight, 
                            $current_order
                        );
                        $insert_stmt->execute();
                    }
                } else {
                    $group_weight = floatval($group['weight']);
                    $num_targets = count($targets);
                    $weight_per_target = $num_targets > 0 ? $group_weight / $num_targets : 0;
                    
                    foreach ($targets as $target_index => $target_description) {
                        $kpi_code = $kpi_code_prefix . ($target_index + 1);
                        $current_order = $display_order++;
                        
                        $insert_stmt->bind_param("issssdi", 
                            $template_id, 
                            $kpi_code, 
                            $section, 
                            $kpi_group, 
                            $target_description, 
                            $weight_per_target, 
                            $current_order
                        );
                        $insert_stmt->execute();
                    }
                }
            }
            
            // Redirect after successful creation
            if (!$is_edit) {
                header("Location: kpi_template_management.php?created=1");
                exit();
            } else {
                header("Location: edit_kpi_template.php?id=" . $template_id . "&success=1");
                exit();
            }
        }
    }
}

// Determine which items to display in the form
$display_groups = [];
if ($is_edit && !empty($template_items)) {
    $groups = [];
    foreach ($template_items as $item) {
        $group_key = $item['section'] . '|' . $item['kpi_group'];
        if (!isset($groups[$group_key])) {
            $groups[$group_key] = [
                'section' => $item['section'],
                'section_num' => $item['section'] == 'Section 1' ? 1 : 2,
                'kpi_group' => $item['kpi_group'],
                'weight' => 0,
                'targets' => [],
                'kpi_code_prefix' => $item['section'] == 'Section 1' ? 'S' : substr($item['kpi_code'], 0, -1)
            ];
        }
        
        if ($item['section'] == 'Section 1') {
            $groups[$group_key]['targets'][] = [
                'description' => $item['kpi_description'],
                'weight' => $item['weight']
            ];
            $groups[$group_key]['weight'] += $item['weight'];
        } else {
            $groups[$group_key]['targets'][] = $item['kpi_description'];
            $groups[$group_key]['weight'] += $item['weight'];
        }
    }
    $display_groups = array_values($groups);
} elseif (!empty($previous_year_items)) {
    $groups = [];
    foreach ($previous_year_items as $item) {
        $group_key = $item['section'] . '|' . $item['kpi_group'];
        if (!isset($groups[$group_key])) {
            $groups[$group_key] = [
                'section' => $item['section'],
                'section_num' => $item['section'] == 'Section 1' ? 1 : 2,
                'kpi_group' => $item['kpi_group'],
                'weight' => 0,
                'targets' => [],
                'kpi_code_prefix' => $item['section'] == 'Section 1' ? 'S' : substr($item['kpi_code'], 0, -1)
            ];
        }
        
        if ($item['section'] == 'Section 1') {
            $groups[$group_key]['targets'][] = [
                'description' => $item['kpi_description'],
                'weight' => $item['weight']
            ];
            $groups[$group_key]['weight'] += $item['weight'];
        } else {
            $groups[$group_key]['targets'][] = $item['kpi_description'];
            $groups[$group_key]['weight'] += $item['weight'];
        }
    }
    $display_groups = array_values($groups);
}

// Calculate next year for new template
$next_year = date('Y') + 1;
$suggested_year = $is_edit ? $template_data['year'] : $next_year;
$suggested_name = $is_edit ? $template_data['template_name'] : ($next_year . " KPI Template");
$suggested_section1 = $is_edit ? $template_data['section1_weight'] : ($previous_year_template ? $previous_year_template['section1_weight'] : 25);
$suggested_section2 = $is_edit ? $template_data['section2_weight'] : ($previous_year_template ? $previous_year_template['section2_weight'] : 75);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit' : 'Create'; ?> KPI Template</title>
    <link rel="stylesheet" href="../asset/universal.css">
    <link rel="stylesheet" href="../asset/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --success: #06d6a0;
            --warning: #ffb703;
            --danger: #ef476f;
            --dark: #2b2d42;
            --light: #f8f9fa;
            --text-main: #1a1a2e;
            --text-muted: #b08090;
            --border-soft: #e9ecef;
            --bg-main: #f0f2f5;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #fcf2fa;
        }
        
        .dashboard {
            margin-left: 200px;
            background: #fcf2fa;        /* ← PINK BACKGROUND */
            padding: 85px 45px 40px;
            min-height: 100vh;
        }
        
        .reports-content {
            width: 100%;
            padding: 0;
            margin: 0;
        }
        
        /* Header styling - EXACT MATCH with reporting page */
        .reports-header {
            background: #fcf2fa;
            padding-bottom: 16px;
            margin-bottom: 0;
        }

        .reports-header h1 {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 4px;
            color: var(--text-main);
            letter-spacing: -0.4px;
        }

        .reports-subtitle {
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 20px;
        }
        
        /* Top bar container */
        .top-bar {
            margin-bottom: 12px;
        }
        
        /* Back button pill style */
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 999px;
            background: white;
            color: #e83e8c;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            border: 1px solid #f3e5f5;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            transition: all 0.2s ease;
        }
        
        .btn-back:hover {
            background: #fdf2f8;
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(0,0,0,0.08);
            color: #c2185b;
        }
        
        /* Modern Hero Header */
        .template-hero {
            background: linear-gradient(135deg, #c070e0 0%, #e83e8c 100%);
            border-radius: 24px;
            padding: 28px 32px;
            color: white;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 12px 30px rgba(232, 62, 140, 0.18);
            margin-bottom: 30px;
        }
        
        .template-icon {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            backdrop-filter: blur(6px);
        }
        
        .template-info h2 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        
        .template-meta {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 12px;
        }
        
        .template-tags {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .tag {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            background: rgba(255,255,255,0.2);
        }
        
        .tag.draft {
            background: #fff3e0;
            color: #e65100;
        }
        
        .tag.config {
            background: rgba(255,255,255,0.25);
            color: white;
        }
        
        .tag.copy {
            background: rgba(255,255,255,0.3);
            color: white;
        }
        
        /* Card wrapper styles */
        .config-card {
            background: white;
            border-radius: 20px;
            border: 1px solid var(--border-soft);
            overflow: hidden;
            margin-bottom: 32px;
        }
        
        .card-header-custom {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-soft);
            background: #fafafa;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .card-header-custom h3 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
            color: var(--text-main);
        }
        
        .card-body-custom {
            padding: 24px;
        }
        
        .section-card {
            margin-bottom: 30px;
            border: 1px solid var(--border-soft);
            border-radius: 20px;
            padding: 20px;
            background-color: white;
            transition: box-shadow 0.2s;
        }
        
        .section-card:hover {
            box-shadow: 0 8px 20px rgba(75, 21, 53, 0.08);
        }
        
        .section-header {
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-soft);
        }
        
        .section-header h4 {
            font-weight: 600;
            color: var(--text-main);
        }
        
        .group-card {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 16px;
            border: 1px solid var(--border-soft);
            transition: all 0.2s;
            border-left: 4px solid #e83e8c;
        }
        
        .group-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-soft);
        }
        
        .target-list {
            margin-left: 20px;
            padding-left: 15px;
            border-left: 2px solid var(--border-soft);
        }
        
        .target-item {
            padding: 8px;
            margin-bottom: 8px;
            background: white;
            border-radius: 12px;
            transition: all 0.2s;
        }
        
        .weight-summary-card {
            position: sticky;
            top: 20px;
            border-radius: 20px;
            border: 1px solid var(--border-soft);
            background: white;
        }
        
        .weight-summary-card .card-header {
            background: linear-gradient(135deg, #c070e0 0%, #e83e8c 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 16px 20px;
            font-weight: 600;
        }
        
        .section-badge {
            font-size: 12px;
            padding: 4px 12px;
            border-radius: 20px;
            margin-left: 10px;
        }
        
        .badge-section1 {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
        }
        
        .badge-section2 {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
        }
        
        .group-weight-input {
            width: 100px;
        }
        
        .individual-weight-input {
            width: 80px;
        }
        
        .progress {
            height: 8px;
            border-radius: 10px;
        }
        
        .form-control, .form-select {
            border-radius: 12px;
            border: 1px solid var(--border-soft);
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, #c070e0 0%, #e83e8c 100%);
            border: none;
            border-radius: 14px;
            padding: 12px 28px;
            font-weight: 600;
            color: white;
            transition: all 0.2s ease;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(232, 62, 140, 0.3);
            color: white;
        }
        
        .btn-secondary-custom {
            border-radius: 14px;
            padding: 12px 28px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        
        .alert {
            border-radius: 16px;
        }
        
        .template-info-card {
            border-radius: 20px;
            border: 1px solid var(--border-soft);
            margin-bottom: 24px;
            background: white;
        }
        
        .template-info-card .card-header {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-bottom: 1px solid var(--border-soft);
            padding: 16px 20px;
            font-weight: 600;
            font-size: 16px;
            color: #1e293b;
        }
        
        @media (max-width: 768px) {
            .dashboard {
                margin-left: 0;
                padding: 20px 15px;
            }
            
            .template-hero {
                flex-direction: column;
                text-align: center;
            }
            
            .group-header .row {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <?php include("../includes/sidebar.php"); ?>

        <div class="reports-content">
            <!-- Back Button -->
            <div class="top-bar">
                <a href="kpi_template_management.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i>
                    Back to Templates
                </a>
            </div>

            <!-- Modern Hero Header -->
            <div class="template-hero">
                <div class="template-icon">
                    <i class="fas <?php echo $is_edit ? 'fa-edit' : 'fa-plus-circle'; ?>"></i>
                </div>
                <div class="template-info">
                    <h2><?php echo $is_edit ? 'Edit' : 'Create New'; ?> KPI Template</h2>
                    <div class="template-meta">
                        <?php if (!$is_edit && $previous_year_template): ?>
                            <i class="fas fa-copy me-1"></i>
                            Based on <?php echo $previous_year_template['year']; ?> template
                        <?php else: ?>
                            <i class="fas fa-chart-line me-1"></i>
                            Configure KPI structure for the upcoming year
                        <?php endif; ?>
                    </div>
                    <div class="template-tags">
                        <span class="tag draft">
                            <i class="fas fa-pen me-1"></i> Draft Mode
                        </span>
                        <span class="tag config">
                            <i class="fas fa-sliders-h me-1"></i> KPI Configuration
                        </span>
                        <?php if (!$is_edit && $previous_year_template): ?>
                            <span class="tag copy">
                                <i class="fas fa-copy me-1"></i> Copied from <?php echo $previous_year_template['year']; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Copy Alert -->
            <?php if (!$is_edit && $previous_year_template): ?>
                <div class="alert alert-info alert-dismissible fade show mb-4" role="alert" style="background: linear-gradient(135deg, #e7f3ff 0%, #d4e8ff 100%); border-left: 4px solid #007bff; border-radius: 16px;">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Template Pre-loaded:</strong> This template is pre-loaded with all KPI groups and competencies from <?php echo $previous_year_template['year']; ?>. You can modify, add, or remove items as needed.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-lg-8">
                    <form method="POST" id="templateForm" onsubmit="return validateGroups()">
                        <!-- Template Info Card -->
                        <div class="card template-info-card">
                            <div class="card-header">
                                <i class="fas fa-info-circle me-2" style="color: #e83e8c;"></i> Template Information
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Template Name <span class="text-danger">*</span></label>
                                        <input type="text" name="template_name" class="form-control" required
                                               value="<?php echo htmlspecialchars($suggested_name); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Year <span class="text-danger">*</span></label>
                                        <input type="number" name="year" class="form-control" required min="2020" max="2030"
                                               value="<?php echo $suggested_year; ?>">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Section 1 Total Weight (Competency) %</label>
                                        <input type="number" name="section1_weight" id="section1_weight" 
                                               class="form-control" required step="0.01" min="0" max="100"
                                               value="<?php echo $suggested_section1; ?>"
                                               onchange="updateTotalWeight()">
                                        <small class="text-muted">Sum of all competency weights</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Section 2 Total Weight (KPIs) %</label>
                                        <input type="number" name="section2_weight" id="section2_weight" 
                                               class="form-control" required step="0.01" min="0" max="100"
                                               value="<?php echo $suggested_section2; ?>"
                                               onchange="updateTotalWeight()">
                                        <small class="text-muted">Sum of all KPI group weights</small>
                                    </div>
                                </div>
                                <div id="weightWarning" class="text-danger small mt-1"></div>
                            </div>
                        </div>
                        
                        <!-- Groups Container -->
                        <div id="groupsContainer">
                            <!-- Section 1: Competency -->
                            <div class="section-card" data-section="1">
                                <div class="section-header">
                                    <h4>
                                        <i class="fas fa-star text-info me-2"></i> Section 1: Core Competencies
                                        <span class="section-badge badge-section1">Individual weights</span>
                                    </h4>
                                    <small class="text-muted">Total Weight: <strong class="section1-total">0</strong>% (Target: <span id="targetSection1"><?php echo $suggested_section1; ?></span>%)</small>
                                </div>
                                <div id="section1-groups"></div>
                                <button type="button" class="btn btn-sm btn-success mt-3" onclick="addGroup(1)">
                                    <i class="fas fa-plus me-1"></i> Add Competency Group
                                </button>
                            </div>
                            
                            <!-- Section 2: KPIs -->
                            <div class="section-card mt-4" data-section="2">
                                <div class="section-header">
                                    <h4>
                                        <i class="fas fa-chart-line text-primary me-2"></i> Section 2: Key Performance Indicators
                                        <span class="section-badge badge-section2">Auto-distributed weights</span>
                                    </h4>
                                    <small class="text-muted">Total Weight: <strong class="section2-total">0</strong>% (Target: <span id="targetSection2"><?php echo $suggested_section2; ?></span>%)</small>
                                </div>
                                <div id="section2-groups"></div>
                                <button type="button" class="btn btn-sm btn-success mt-3" onclick="addGroup(2)">
                                    <i class="fas fa-plus me-1"></i> Add KPI Group
                                </button>
                            </div>
                        </div>
                        
                        <input type="hidden" name="kpi_groups" id="kpi_groups">
                        
                        <div class="mt-4 mb-5 d-flex gap-3">
                            <button type="submit" class="btn btn-primary-custom">
                                <i class="fas fa-save me-2"></i> <?php echo $is_edit ? 'Update' : 'Create'; ?> Template
                            </button>
                            <a href="kpi_template_management.php" class="btn btn-secondary-custom btn-outline-secondary">
                                <i class="fas fa-times me-2"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
                
                <div class="col-lg-4">
                    <div class="card weight-summary-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-balance-scale me-2"></i> Weight Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-4">
                                <label class="fw-semibold">Section 1 Total:</label>
                                <h3 id="summarySection1" class="text-info mt-1">0%</h3>
                                <div class="progress mt-2">
                                    <div id="progressSection1" class="progress-bar bg-info" style="width: 0%"></div>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="fw-semibold">Section 2 Total:</label>
                                <h3 id="summarySection2" class="text-primary mt-1">0%</h3>
                                <div class="progress mt-2">
                                    <div id="progressSection2" class="progress-bar bg-primary" style="width: 0%"></div>
                                </div>
                            </div>
                            <hr>
                            <div>
                                <label class="fw-semibold">Overall Status:</label>
                                <h4 id="overallStatus" class="text-success mt-1">
                                    <i class="fas fa-check-circle"></i> Valid
                                </h4>
                            </div>
                            <div class="alert alert-info mt-3 small mb-0">
                                <i class="fas fa-info-circle me-1"></i> 
                                <strong>Section 1:</strong> Each competency has its own individual weight.<br>
                                <strong>Section 2:</strong> Group weight is automatically distributed evenly among all targets.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let kpiGroups = [];
        let groupCounter = 0;
        let previousYearGroups = <?php echo json_encode($display_groups); ?>;
        
        <?php if (!empty($display_groups)): ?>
            // Load existing groups
            kpiGroups = previousYearGroups.map((group, index) => ({
                id: index,
                section_num: group.section_num,
                kpi_group: group.kpi_group,
                weight: group.weight,
                targets: group.targets,
                kpi_code_prefix: group.kpi_code_prefix || (group.section_num == 1 ? 'S' : 'K')
            }));
            groupCounter = kpiGroups.length;
            renderAllGroups();
        <?php else: ?>
            // Add default groups
            addGroup(1);
            addGroup(2);
        <?php endif; ?>
        
        function addGroup(section) {
            const newGroup = {
                id: groupCounter++,
                section_num: section,
                kpi_group: '',
                weight: 0,
                targets: section == 1 ? [] : [''],
                kpi_code_prefix: section == 1 ? 'S' : 'K'
            };
            
            if (section == 1) {
                newGroup.targets = [{ description: '', weight: 0 }];
            }
            
            kpiGroups.push(newGroup);
            renderAllGroups();
        }
        
        function removeGroup(groupId) {
            if (confirm('Remove this KPI group and all its targets?')) {
                kpiGroups = kpiGroups.filter(group => group.id !== groupId);
                renderAllGroups();
            }
        }
        
        function addTarget(groupId) {
            const group = kpiGroups.find(g => g.id === groupId);
            if (group) {
                if (group.section_num == 1) {
                    group.targets.push({ description: '', weight: 0 });
                } else {
                    group.targets.push('');
                }
                renderAllGroups();
            }
        }
        
        function removeTarget(groupId, targetIndex) {
            const group = kpiGroups.find(g => g.id === groupId);
            if (group && group.targets.length > 1) {
                group.targets.splice(targetIndex, 1);
                renderAllGroups();
            } else {
                alert('Each group must have at least one target');
            }
        }
        
        function updateGroupField(groupId, field, value) {
            const group = kpiGroups.find(g => g.id === groupId);
            if (group) {
                group[field] = value;
                if (field === 'weight') {
                    group.weight = parseFloat(value) || 0;
                }
                updateWeights();
            }
        }
        
        function updateTarget(groupId, targetIndex, field, value) {
            const group = kpiGroups.find(g => g.id === groupId);
            if (group) {
                if (group.section_num == 1) {
                    group.targets[targetIndex][field] = value;
                    if (field === 'weight') {
                        group.targets[targetIndex].weight = parseFloat(value) || 0;
                    }
                } else {
                    group.targets[targetIndex] = value;
                }
                updateWeights();
            }
        }
        
        function renderAllGroups() {
            const section1Container = $('#section1-groups');
            const section2Container = $('#section2-groups');
            
            section1Container.empty();
            section2Container.empty();
            
            kpiGroups.forEach(group => {
                const groupHtml = createGroupHtml(group);
                if (group.section_num == 1) {
                    section1Container.append(groupHtml);
                } else {
                    section2Container.append(groupHtml);
                }
            });
            
            updateWeights();
        }
        
        function createGroupHtml(group) {
            let targetsHtml = '';
            
            if (group.section_num == 1) {
                group.targets.forEach((target, index) => {
                    targetsHtml += `
                        <div class="target-item">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <input type="text" class="form-control form-control-sm" 
                                           value="${escapeHtml(target.description)}"
                                           placeholder="Competency name (e.g., Initiative)"
                                           onchange="updateTarget(${group.id}, ${index}, 'description', this.value)">
                                </div>
                                <div class="col-md-3">
                                    <div class="input-group input-group-sm">
                                        <input type="number" class="form-control individual-weight-input" 
                                               step="0.01" min="0" max="100"
                                               value="${target.weight}"
                                               placeholder="Weight"
                                               onchange="updateTarget(${group.id}, ${index}, 'weight', this.value)">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeTarget(${group.id}, ${index})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                });
            } else {
                group.targets.forEach((target, index) => {
                    const displayWeight = group.weight > 0 && group.targets.length > 0 ? (group.weight / group.targets.length).toFixed(2) : 0;
                    
                    targetsHtml += `
                        <div class="target-item">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <input type="text" class="form-control form-control-sm" 
                                           value="${escapeHtml(target)}"
                                           placeholder="Enter target description or measurable goal"
                                           onchange="updateTarget(${group.id}, ${index}, null, this.value)">
                                </div>
                                <div class="col-md-2">
                                    <small class="text-muted">Weight: ${displayWeight}%</small>
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeTarget(${group.id}, ${index})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                });
            }
            
            return `
                <div class="group-card" data-group-id="${group.id}">
                    <div class="group-header">
                        <div class="row w-100">
                            <div class="col-md-5">
                                <input type="text" class="form-control" 
                                       value="${escapeHtml(group.kpi_group)}"
                                       placeholder="Group Name"
                                       onchange="updateGroupField(${group.id}, 'kpi_group', this.value)">
                            </div>
                            <div class="col-md-3">
                                ${group.section_num == 1 ? 
                                    `<small class="text-muted">Group total will be calculated</small>` :
                                    `<div class="input-group">
                                        <input type="number" class="form-control group-weight-input" 
                                               step="0.01" min="0" max="100"
                                               value="${group.weight}"
                                               onchange="updateGroupField(${group.id}, 'weight', this.value)">
                                        <span class="input-group-text">%</span>
                                    </div>`
                                }
                            </div>
                            <div class="col-md-2">
                                <input type="text" class="form-control form-control-sm" 
                                       value="${escapeHtml(group.kpi_code_prefix)}"
                                       placeholder="Prefix"
                                       style="width: 80px;"
                                       onchange="updateGroupField(${group.id}, 'kpi_code_prefix', this.value)">
                            </div>
                            <div class="col-md-2 text-end">
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeGroup(${group.id})">
                                    <i class="fas fa-trash me-1"></i> Remove
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="target-list">
                        <label class="small text-muted mb-2">
                            ${group.section_num == 1 ? 'Competencies / Measurable Indicators:' : 'Targets / Measurable Indicators:'}
                        </label>
                        ${targetsHtml}
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addTarget(${group.id})">
                            <i class="fas fa-plus me-1"></i> Add ${group.section_num == 1 ? 'Competency' : 'Target'}
                        </button>
                    </div>
                </div>
            `;
        }
        
        function updateWeights() {
            let section1Total = 0;
            let section2Total = 0;
            
            kpiGroups.forEach(group => {
                if (group.section_num == 1) {
                    let groupTotal = 0;
                    group.targets.forEach(target => {
                        const weight = parseFloat(target.weight) || 0;
                        groupTotal += weight;
                    });
                    section1Total += groupTotal;
                } else {
                    const weight = parseFloat(group.weight) || 0;
                    section2Total += weight;
                }
            });
            
            const targetSection1 = parseFloat($('#section1_weight').val()) || 0;
            const targetSection2 = parseFloat($('#section2_weight').val()) || 0;
            
            $('.section1-total').text(section1Total.toFixed(2));
            $('.section2-total').text(section2Total.toFixed(2));
            
            $('#summarySection1').text(section1Total.toFixed(2) + '%');
            $('#summarySection2').text(section2Total.toFixed(2) + '%');
            
            const progress1 = targetSection1 > 0 ? (section1Total / targetSection1) * 100 : 0;
            const progress2 = targetSection2 > 0 ? (section2Total / targetSection2) * 100 : 0;
            
            $('#progressSection1').css('width', Math.min(progress1, 100) + '%');
            $('#progressSection2').css('width', Math.min(progress2, 100) + '%');
            
            if (progress1 > 100) {
                $('#progressSection1').removeClass('bg-info').addClass('bg-danger');
            } else {
                $('#progressSection1').removeClass('bg-danger').addClass('bg-info');
            }
            
            if (progress2 > 100) {
                $('#progressSection2').removeClass('bg-primary').addClass('bg-danger');
            } else {
                $('#progressSection2').removeClass('bg-danger').addClass('bg-primary');
            }
            
            const section1Valid = Math.abs(section1Total - targetSection1) < 0.01;
            const section2Valid = Math.abs(section2Total - targetSection2) < 0.01;
            
            if (section1Valid && section2Valid && (targetSection1 + targetSection2) === 100) {
                $('#overallStatus').html('<i class="fas fa-check-circle"></i> Valid').removeClass('text-danger').addClass('text-success');
            } else {
                $('#overallStatus').html('<i class="fas fa-exclamation-triangle"></i> Invalid').removeClass('text-success').addClass('text-danger');
            }
        }
        
        function updateTotalWeight() {
            const section1 = parseFloat($('#section1_weight').val()) || 0;
            const section2 = parseFloat($('#section2_weight').val()) || 0;
            const total = section1 + section2;
            
            if (Math.abs(total - 100) > 0.01) {
                $('#weightWarning').html(`<i class="fas fa-exclamation-circle me-1"></i> Total weight is ${total}%. Must equal 100%!`);
                return false;
            } else {
                $('#weightWarning').html('');
                updateWeights();
                return true;
            }
        }
        
        function validateGroups() {
            if (!updateTotalWeight()) {
                alert('Please ensure total section weights equal 100%');
                return false;
            }
            
            const targetSection1 = parseFloat($('#section1_weight').val()) || 0;
            const targetSection2 = parseFloat($('#section2_weight').val()) || 0;
            
            let section1Total = 0;
            let section2Total = 0;
            
            for (const group of kpiGroups) {
                if (!group.kpi_group.trim()) {
                    alert('Please enter a name for all groups');
                    return false;
                }
                
                if (group.section_num == 1) {
                    let groupTotal = 0;
                    for (const target of group.targets) {
                        if (!target.description.trim()) {
                            alert(`Please enter all competency names for group: ${group.kpi_group}`);
                            return false;
                        }
                        if (isNaN(target.weight) || target.weight < 0) {
                            alert(`Please enter valid weight for competency: ${target.description || 'Unnamed'}`);
                            return false;
                        }
                        groupTotal += target.weight;
                    }
                    section1Total += groupTotal;
                } else {
                    for (const target of group.targets) {
                        if (!target.trim()) {
                            alert(`Please enter all targets for group: ${group.kpi_group}`);
                            return false;
                        }
                    }
                    const weight = parseFloat(group.weight) || 0;
                    section2Total += weight;
                }
            }
            
            if (Math.abs(section1Total - targetSection1) > 0.01) {
                alert(`Section 1 total weight (${section1Total}%) does not match target (${targetSection1}%)`);
                return false;
            }
            
            if (Math.abs(section2Total - targetSection2) > 0.01) {
                alert(`Section 2 total weight (${section2Total}%) does not match target (${targetSection2}%)`);
                return false;
            }
            
            const groupsToSave = kpiGroups.map(group => {
                if (group.section_num == 1) {
                    return {
                        section: 'Section 1',
                        kpi_group: group.kpi_group,
                        weight: 0,
                        targets: group.targets.map(t => ({
                            description: t.description,
                            weight: t.weight
                        })),
                        kpi_code_prefix: group.kpi_code_prefix
                    };
                } else {
                    return {
                        section: 'Section 2',
                        kpi_group: group.kpi_group,
                        weight: group.weight,
                        targets: group.targets.filter(t => t.trim()),
                        kpi_code_prefix: group.kpi_code_prefix
                    };
                }
            });
            
            $('#kpi_groups').val(JSON.stringify(groupsToSave));
            return true;
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            return String(text).replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }
        
        $(document).ready(function() {
            updateTotalWeight();
        });
    </script>
</body>
</html>