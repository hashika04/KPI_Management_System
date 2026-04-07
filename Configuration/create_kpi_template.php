<?php
// staff_masterlist/create_kpi_template.php
include("../includes/auth.php");
include("../config/db.php");

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
        
        // Process KPI items - now with group weights
// Process KPI items - now with group weights
        if (empty($error_message) && isset($_POST['kpi_groups'])) {
            $kpi_groups = json_decode($_POST['kpi_groups'], true);
            $insert_sql = "INSERT INTO kpi_template_items (template_id, kpi_code, section, kpi_group, kpi_description, weight, display_order) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            
            $display_order = 0;
            
            foreach ($kpi_groups as $group) {
                $section = $group['section'];
                $kpi_group = $group['kpi_group'];
                $group_weight = floatval($group['weight']);
                $targets = $group['targets']; // Array of target descriptions
                
                // Calculate weight per target (distribute evenly)
                $num_targets = count($targets);
                $weight_per_target = $num_targets > 0 ? $group_weight / $num_targets : 0;
                
                // Insert each target with calculated weight
                foreach ($targets as $target_index => $target_description) {
                    $kpi_code = $group['kpi_code_prefix'] . ($target_index + 1);
                    
                    // Store values in variables to pass by reference
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
            
            // REDIRECT AFTER SUCCESSFUL CREATION
            if (!$is_edit) {
                header("Location: kpi_template_management.php");
                exit();
            }
        }
    }
}

// Determine which items to display in the form
$display_groups = [];
if ($is_edit && !empty($template_items)) {
    // Group items by kpi_group for editing
    $groups = [];
    foreach ($template_items as $item) {
        $group_key = $item['section'] . '|' . $item['kpi_group'];
        if (!isset($groups[$group_key])) {
            $groups[$group_key] = [
                'section' => $item['section'],
                'kpi_group' => $item['kpi_group'],
                'weight' => 0,
                'targets' => [],
                'kpi_code_prefix' => $item['section'] == 'Section 1' ? 'S' : substr($item['kpi_code'], 0, -1)
            ];
        }
        $groups[$group_key]['weight'] += $item['weight'];
        $groups[$group_key]['targets'][] = $item['kpi_description'];
    }
    $display_groups = array_values($groups);
} elseif (!empty($previous_year_items)) {
    // Group previous year items by kpi_group
    $groups = [];
    foreach ($previous_year_items as $item) {
        $group_key = $item['section'] . '|' . $item['kpi_group'];
        if (!isset($groups[$group_key])) {
            $groups[$group_key] = [
                'section' => $item['section'],
                'kpi_group' => $item['kpi_group'],
                'weight' => 0,
                'targets' => [],
                'kpi_code_prefix' => $item['section'] == 'Section 1' ? 'S' : substr($item['kpi_code'], 0, -1)
            ];
        }
        $groups[$group_key]['weight'] += $item['weight'];
        $groups[$group_key]['targets'][] = $item['kpi_description'];
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .section-card {
            margin-bottom: 30px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .group-card {
            background: white;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
        }
        .group-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        .target-list {
            margin-left: 20px;
            padding-left: 15px;
            border-left: 2px solid #e9ecef;
        }
        .target-item {
            padding: 8px;
            margin-bottom: 8px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .weight-summary {
            position: sticky;
            top: 20px;
        }
        .group-weight-input {
            width: 100px;
        }
        .copy-alert {
            background-color: #e7f3ff;
            border-left: 4px solid #007bff;
        }
    </style>
</head>
<body>
    <?php include("../includes/sidebar.php"); ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8">
                <h2>
                    <?php echo $is_edit ? 'Edit' : 'Create'; ?> KPI Template
                    <?php if (!$is_edit && $previous_year_template): ?>
                        <small class="text-muted">Based on <?php echo $previous_year_template['year']; ?> template</small>
                    <?php endif; ?>
                </h2>
                
                <?php if (!$is_edit && $previous_year_template): ?>
                    <div class="alert alert-info copy-alert">
                        <i class="fas fa-info-circle"></i> 
                        This template is pre-loaded with all KPI groups from <?php echo $previous_year_template['year']; ?>.
                        Set the weight for each KPI group, and the system will automatically distribute it to all targets.
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <form method="POST" id="templateForm" onsubmit="return validateGroups()">
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label">Template Name <span class="text-danger">*</span></label>
                                    <input type="text" name="template_name" class="form-control" required
                                           value="<?php echo htmlspecialchars($suggested_name); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Year <span class="text-danger">*</span></label>
                                    <input type="number" name="year" class="form-control" required min="2020" max="2030"
                                           value="<?php echo $suggested_year; ?>">
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label class="form-label">Section 1 Weight (Competency) %</label>
                                    <input type="number" name="section1_weight" id="section1_weight" 
                                           class="form-control" required step="0.01" min="0" max="100"
                                           value="<?php echo $suggested_section1; ?>"
                                           onchange="updateTotalWeight()">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Section 2 Weight (KPIs) %</label>
                                    <input type="number" name="section2_weight" id="section2_weight" 
                                           class="form-control" required step="0.01" min="0" max="100"
                                           value="<?php echo $suggested_section2; ?>"
                                           onchange="updateTotalWeight()">
                                </div>
                            </div>
                            <div class="mt-2">
                                <small id="weightWarning" class="text-danger"></small>
                            </div>
                        </div>
                    </div>
                    
                    <div id="groupsContainer">
                        <!-- Section 1: Competency -->
                        <div class="section-card" data-section="1">
                            <div class="section-header">
                                <h4><i class="fas fa-star"></i> Section 1: Core Competencies</h4>
                                <small>Total Weight: <span class="section1-total">0</span>% (Target: <span id="targetSection1"><?php echo $suggested_section1; ?></span>%)</small>
                            </div>
                            <div id="section1-groups"></div>
                            <button type="button" class="btn btn-sm btn-success mt-2" onclick="addGroup(1)">
                                <i class="fas fa-plus"></i> Add Competency Group
                            </button>
                        </div>
                        
                        <!-- Section 2: KPIs -->
                        <div class="section-card" data-section="2">
                            <div class="section-header">
                                <h4><i class="fas fa-chart-line"></i> Section 2: Key Performance Indicators</h4>
                                <small>Total Weight: <span class="section2-total">0</span>% (Target: <span id="targetSection2"><?php echo $suggested_section2; ?></span>%)</small>
                            </div>
                            <div id="section2-groups"></div>
                            <button type="button" class="btn btn-sm btn-success mt-2" onclick="addGroup(2)">
                                <i class="fas fa-plus"></i> Add KPI Group
                            </button>
                        </div>
                    </div>
                    
                    <input type="hidden" name="kpi_groups" id="kpi_groups">
                    
                    <div class="mt-4 mb-5">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> <?php echo $is_edit ? 'Update' : 'Create'; ?> Template
                        </button>
                        <a href="kpi_template_management.php" class="btn btn-secondary btn-lg">Cancel</a>
                    </div>
                </form>
            </div>
            
            <div class="col-md-4">
                <div class="card weight-summary">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-balance-scale"></i> Weight Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label>Section 1 Total:</label>
                            <h3 id="summarySection1" class="text-info">0%</h3>
                            <div class="progress">
                                <div id="progressSection1" class="progress-bar bg-info" style="width: 0%"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>Section 2 Total:</label>
                            <h3 id="summarySection2" class="text-primary">0%</h3>
                            <div class="progress">
                                <div id="progressSection2" class="progress-bar bg-primary" style="width: 0%"></div>
                            </div>
                        </div>
                        <hr>
                        <div>
                            <label>Overall Status:</label>
                            <h4 id="overallStatus" class="text-success">
                                <i class="fas fa-check-circle"></i> Valid
                            </h4>
                        </div>
                        <div class="alert alert-info mt-3 small">
                            <i class="fas fa-info-circle"></i> 
                            The weight you set for each group will be automatically distributed evenly among all targets in that group.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        let kpiGroups = [];
        let groupCounter = 0;
        let previousYearGroups = <?php echo json_encode($display_groups); ?>;
        
        <?php if (!empty($display_groups)): ?>
            // Load existing groups
            kpiGroups = previousYearGroups.map((group, index) => ({
                id: index,
                section_num: group.section === 'Section 1' ? 1 : 2,
                kpi_group: group.kpi_group,
                weight: group.weight,
                targets: group.targets,
                kpi_code_prefix: group.kpi_code_prefix || (group.section === 'Section 1' ? 'S' : 'K')
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
                targets: [''],
                kpi_code_prefix: section == 1 ? 'S' : 'K'
            };
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
                group.targets.push('');
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
        
        function updateTarget(groupId, targetIndex, value) {
            const group = kpiGroups.find(g => g.id === groupId);
            if (group) {
                group.targets[targetIndex] = value;
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
            group.targets.forEach((target, index) => {
                targetsHtml += `
                    <div class="target-item">
                        <div class="row">
                            <div class="col-md-10">
                                <input type="text" class="form-control form-control-sm" 
                                       value="${escapeHtml(target)}"
                                       placeholder="Enter target description or measurable goal"
                                       onchange="updateTarget(${group.id}, ${index}, this.value)">
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-sm btn-danger" onclick="removeTarget(${group.id}, ${index})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            return `
                <div class="group-card" data-group-id="${group.id}">
                    <div class="group-header">
                        <div class="row w-100">
                            <div class="col-md-5">
                                <input type="text" class="form-control" 
                                       value="${escapeHtml(group.kpi_group)}"
                                       placeholder="Group Name (e.g., Customer Service)"
                                       onchange="updateGroupField(${group.id}, 'kpi_group', this.value)">
                            </div>
                            <div class="col-md-3">
                                <div class="input-group">
                                    <input type="number" class="form-control group-weight-input" 
                                           step="0.01" min="0" max="100"
                                           value="${group.weight}"
                                           onchange="updateGroupField(${group.id}, 'weight', this.value)">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <input type="text" class="form-control form-control-sm" 
                                       value="${escapeHtml(group.kpi_code_prefix)}"
                                       placeholder="Prefix"
                                       style="width: 80px;"
                                       onchange="updateGroupField(${group.id}, 'kpi_code_prefix', this.value)">
                            </div>
                            <div class="col-md-2 text-end">
                                <button type="button" class="btn btn-sm btn-danger" onclick="removeGroup(${group.id})">
                                    <i class="fas fa-trash"></i> Remove Group
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="target-list">
                        <label class="small text-muted">Targets / Measurable Indicators:</label>
                        ${targetsHtml}
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addTarget(${group.id})">
                            <i class="fas fa-plus"></i> Add Target
                        </button>
                    </div>
                </div>
            `;
        }
        
        function updateWeights() {
            let section1Total = 0;
            let section2Total = 0;
            
            kpiGroups.forEach(group => {
                const weight = parseFloat(group.weight) || 0;
                if (group.section_num == 1) {
                    section1Total += weight;
                } else {
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
                $('#progressSection1').addClass('bg-danger').removeClass('bg-info');
            } else {
                $('#progressSection1').addClass('bg-info').removeClass('bg-danger');
            }
            
            if (progress2 > 100) {
                $('#progressSection2').addClass('bg-danger').removeClass('bg-primary');
            } else {
                $('#progressSection2').addClass('bg-primary').removeClass('bg-danger');
            }
            
            // Validate weights
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
                $('#weightWarning').html(`<i class="fas fa-exclamation-circle"></i> Total weight is ${total}%. Must equal 100%!`);
                return false;
            } else {
                $('#weightWarning').text('');
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
            
            // Validate each group
            for (const group of kpiGroups) {
                if (!group.kpi_group.trim()) {
                    alert('Please enter a name for all KPI groups');
                    return false;
                }
                
                // Check if any target is empty
                for (const target of group.targets) {
                    if (!target.trim()) {
                        alert(`Please enter all targets for group: ${group.kpi_group}`);
                        return false;
                    }
                }
                
                const weight = parseFloat(group.weight) || 0;
                if (group.section_num == 1) {
                    section1Total += weight;
                } else {
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
            
            // Prepare data for submission
            const groupsToSave = kpiGroups.map(group => ({
                section: group.section_num == 1 ? 'Section 1' : 'Section 2',
                kpi_group: group.kpi_group,
                weight: group.weight,
                targets: group.targets.filter(t => t.trim()),
                kpi_code_prefix: group.kpi_code_prefix
            }));
            
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