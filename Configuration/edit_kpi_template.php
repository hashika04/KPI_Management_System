<?php
// staff_masterlist/edit_kpi_template.php
include("../includes/auth.php");
include("../config/db.php");
$activePage = 'config';
// Check if user is supervisor
if ($_SESSION['position'] !== 'Supervisor') {
    header("Location: stafflist.php");
    exit();
}

$template_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error_message = '';
$success_message = '';

if (!$template_id) {
    header("Location: kpi_template_management.php");
    exit();
}

// Fetch template data
$sql = "SELECT * FROM kpi_templates WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $template_id);
$stmt->execute();
$template = $stmt->get_result()->fetch_assoc();

if (!$template) {
    header("Location: kpi_template_management.php");
    exit();
}

// Determine template type
$is_active = ($template['status'] == 'active');
$has_kpi_data = false;

// Check if template has KPI data
$check_data_sql = "SELECT COUNT(*) as count FROM kpi_data WHERE template_id = ?";
$check_data_stmt = $conn->prepare($check_data_sql);
$check_data_stmt->bind_param("i", $template_id);
$check_data_stmt->execute();
$data_result = $check_data_stmt->get_result();
$data_count = $data_result->fetch_assoc()['count'];
$has_kpi_data = ($data_count > 0);

// Determine if draft: inactive and no KPI data
$is_draft = ($template['status'] == 'inactive' && !$has_kpi_data);
$is_previous = (!$is_active && $has_kpi_data);

// If previous template (has data), redirect to view only
if ($is_previous) {
    header("Location: view_kpi_template.php?id=" . $template_id . "&error=readonly");
    exit();
}

// Set permissions
$can_delete_targets = $is_draft;      // Only draft can delete targets
$can_delete_groups = $is_draft;       // Only draft can delete entire groups
$can_add_targets = true;               // Both active and draft can add
$can_add_groups = true;                // Both active and draft can add new groups
$can_rename = true;                    // Both can rename
$can_change_weights = true;            // Both can change weights

// Fetch existing template items grouped
$items_sql = "SELECT * FROM kpi_template_items WHERE template_id = ? ORDER BY section, display_order";
$items_stmt = $conn->prepare($items_sql);
$items_stmt->bind_param("i", $template_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();

$template_items = [];
while($item = $items_result->fetch_assoc()) {
    $template_items[] = $item;
}

// Group items by kpi_group for display
$groups = [];
foreach ($template_items as $item) {
    $group_key = $item['section'] . '|' . $item['kpi_group'];
    if (!isset($groups[$group_key])) {
        $groups[$group_key] = [
            'id' => count($groups),
            'section' => $item['section'],
            'section_num' => $item['section'] == 'Section 1' ? 1 : 2,
            'kpi_group' => $item['kpi_group'],
            'weight' => 0,
            'targets' => [],
            'kpi_code_prefix' => $item['section'] == 'Section 1' ? 'S' : substr($item['kpi_code'], 0, -1),
            'is_existing_group' => true  // Mark as existing group
        ];
    }
    
    if ($item['section'] == 'Section 1') {
        // For Section 1, store each target with its individual weight
        $groups[$group_key]['targets'][] = [
            'description' => $item['kpi_description'],
            'weight' => $item['weight'],
            'code' => $item['kpi_code'],
            'id' => $item['id'],
            'is_existing' => true
        ];
        $groups[$group_key]['weight'] += $item['weight']; // Sum for validation
    } else {
        // For Section 2, just store descriptions, weight is at group level
        $groups[$group_key]['targets'][] = [
            'description' => $item['kpi_description'],
            'code' => $item['kpi_code'],
            'id' => $item['id'],
            'is_existing' => true
        ];
        $groups[$group_key]['weight'] += $item['weight'];
    }
}
$display_groups = array_values($groups);

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
        // Update template
        $sql = "UPDATE kpi_templates SET template_name = ?, year = ?, section1_weight = ?, section2_weight = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("siddi", $template_name, $year, $section1_weight, $section2_weight, $template_id);
        
        if ($stmt->execute()) {
            // Delete existing items only if we're replacing them
            if (isset($_POST['kpi_groups'])) {
                $delete_sql = "DELETE FROM kpi_template_items WHERE template_id = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bind_param("i", $template_id);
                $delete_stmt->execute();
                
                // Process new items
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
                        // Section 1: Each competency has its own individual weight
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
                        // Section 2: Distribute group weight evenly among targets
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
            }
            
            $success_message = "Template updated successfully!";
            
            // Refresh page to show updated data
            header("Location: edit_kpi_template.php?id=" . $template_id . "&success=1");
            exit();
        } else {
            $error_message = "Error updating template: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_active ? 'Edit Active Template' : 'Edit Draft Template'; ?></title>
    <link rel="stylesheet" href="../asset/universal.css">
    <link rel="stylesheet" href="../asset/dashboard.css">
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
            transition: all 0.2s;
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
            transition: all 0.2s;
        }
        .weight-summary {
            position: sticky;
            top: 20px;
        }
        .group-weight-input {
            width: 100px;
        }
        .individual-weight-input {
            width: 80px;
        }
        .alert-info {
            background-color: #e7f3ff;
            border-left: 4px solid #007bff;
        }
        .alert-warning {
            background-color: #fff3e0;
            border-left: 4px solid #ff9800;
        }
        .section-badge {
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 4px;
            margin-left: 10px;
        }
        .badge-section1 {
            background-color: #17a2b8;
            color: white;
        }
        .badge-section2 {
            background-color: #007bff;
            color: white;
        }
        
        /* ACTIVE TEMPLATE STYLES - Highlight Existing (Orange/Warning) */
        .active-mode .group-card.existing-group {
            border-left-color: #ff9800;
            background-color: #fff8f0;
        }
        .active-mode .target-item.existing-target {
            background-color: #fff3e0;
            border-left: 3px solid #ff9800;
        }
        .active-mode .existing-badge {
            background-color: #ff9800;
            color: white;
        }
        
        /* DRAFT TEMPLATE STYLES - Highlight New (Blue/Info) */
        .draft-mode .group-card:not(.existing-group) {
            border-left-color: #2196f3;
            background-color: #f0f8ff;
        }
        .draft-mode .target-item:not(.existing-target) {
            background-color: #e3f2fd;
            border-left: 3px solid #2196f3;
        }
        .draft-mode .new-badge {
            background-color: #2196f3;
            color: white;
        }
        
        .existing-badge {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
            margin-left: 5px;
        }
        .new-badge {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
            margin-left: 5px;
            background-color: #6c757d;
            color: white;
        }
        .badge-secondary {
            background-color: #6c757d;
        }
    </style>
</head>
<body class="<?php echo $is_active ? 'active-mode' : 'draft-mode'; ?>">
    <div class="dashboard">
    <?php include("../includes/sidebar.php"); ?>

    <div class="reports-content">
        <div class="container mt-4">
            <div class="row">
                <div class="col-md-8">
                    <h2>
                        <i class="fas fa-edit"></i> 
                        <?php echo $is_active ? 'Edit Active Template' : 'Edit Draft Template'; ?>
                        <small class="text-muted"><?php echo htmlspecialchars($template['template_name']); ?></small>
                    </h2>
                    
                    <?php if($is_active): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-shield-alt"></i> 
                            <strong>⚠️ Active Template Mode - Protected Items:</strong> 
                            Existing groups and targets (<span class="badge" style="background-color:#ff9800; color:white;">Orange highlighted</span>) cannot be deleted to preserve historical KPI data.
                            You can add new groups/targets (<span class="badge bg-secondary">Gray badges</span>), rename items, and adjust weights.
                        </div>
                    <?php endif; ?>
                    
                    <?php if($is_draft): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-edit"></i> 
                            <strong>📝 Draft Mode - Full Control:</strong> 
                            Newly added items (<span class="badge" style="background-color:#2196f3; color:white;">Blue highlighted</span>) are clearly visible.
                            You have full control - add, delete groups/targets, rename, and adjust weights freely.
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success">Template updated successfully!</div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" id="templateForm" onsubmit="return validateGroups()">
                        <div class="card mb-4">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">Template Name <span class="text-danger">*</span></label>
                                        <input type="text" name="template_name" class="form-control" required
                                               value="<?php echo htmlspecialchars($template['template_name']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Year <span class="text-danger">*</span></label>
                                        <input type="number" name="year" class="form-control" required min="2020" max="2030"
                                               value="<?php echo $template['year']; ?>">
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Section 1 Total Weight (Competency) %</label>
                                        <input type="number" name="section1_weight" id="section1_weight" 
                                               class="form-control" required step="0.01" min="0" max="100"
                                               value="<?php echo $template['section1_weight']; ?>"
                                               onchange="updateTotalWeight()">
                                        <small class="text-muted">Sum of all competency weights</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Section 2 Total Weight (KPIs) %</label>
                                        <input type="number" name="section2_weight" id="section2_weight" 
                                               class="form-control" required step="0.01" min="0" max="100"
                                               value="<?php echo $template['section2_weight']; ?>"
                                               onchange="updateTotalWeight()">
                                        <small class="text-muted">Sum of all KPI group weights</small>
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
                                    <h4>
                                        <i class="fas fa-star"></i> Section 1: Core Competencies
                                        <span class="section-badge badge-section1">Individual weights</span>
                                    </h4>
                                    <small>Total Weight: <span class="section1-total">0</span>% (Target: <span id="targetSection1"><?php echo $template['section1_weight']; ?></span>%)</small>
                                </div>
                                <div id="section1-groups"></div>
                                <button type="button" class="btn btn-sm btn-success mt-2" onclick="addGroup(1)">
                                    <i class="fas fa-plus"></i> Add Competency Group
                                </button>
                            </div>
                            
                            <!-- Section 2: KPIs -->
                            <div class="section-card" data-section="2">
                                <div class="section-header">
                                    <h4>
                                        <i class="fas fa-chart-line"></i> Section 2: Key Performance Indicators
                                        <span class="section-badge badge-section2">Auto-distributed weights</span>
                                    </h4>
                                    <small>Total Weight: <span class="section2-total">0</span>% (Target: <span id="targetSection2"><?php echo $template['section2_weight']; ?></span>%)</small>
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
                                <i class="fas fa-save"></i> Save Changes
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
                                <strong>Section 1:</strong> Each competency has its own individual weight.<br>
                                <strong>Section 2:</strong> Group weight is automatically distributed evenly among all targets.
                            </div>
                        </div>
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
        let existingGroups = <?php echo json_encode($display_groups); ?>;
        let canDeleteTargets = <?php echo $can_delete_targets ? 'true' : 'false'; ?>;
        let canDeleteGroups = <?php echo $can_delete_groups ? 'true' : 'false'; ?>;
        let isActiveMode = <?php echo $is_active ? 'true' : 'false'; ?>;
        
        // Load existing groups
        kpiGroups = existingGroups.map((group, index) => {
            if (group.section_num == 1) {
                // Section 1: Targets have individual weights
                return {
                    id: index,
                    section_num: group.section_num,
                    kpi_group: group.kpi_group,
                    weight: 0, // Not used for section 1, total calculated from targets
                    targets: group.targets.map(t => ({
                        description: t.description,
                        weight: t.weight,
                        id: t.id,
                        is_existing: t.is_existing || true
                    })),
                    target_ids: group.targets.map(t => t.id),
                    kpi_code_prefix: group.kpi_code_prefix,
                    is_existing_group: group.is_existing_group || true
                };
            } else {
                // Section 2: Targets are just descriptions
                return {
                    id: index,
                    section_num: group.section_num,
                    kpi_group: group.kpi_group,
                    weight: group.weight,
                    targets: group.targets.map(t => t.description),
                    target_ids: group.targets.map(t => t.id),
                    target_existing: group.targets.map(t => t.is_existing || true),
                    kpi_code_prefix: group.kpi_code_prefix,
                    is_existing_group: group.is_existing_group || true
                };
            }
        });
        groupCounter = kpiGroups.length;
        renderAllGroups();
        
        function addGroup(section) {
            const newGroup = {
                id: groupCounter++,
                section_num: section,
                kpi_group: '',
                weight: 0,
                targets: section == 1 ? [] : [''],
                target_ids: [],
                target_existing: [],
                kpi_code_prefix: section == 1 ? 'S' : 'K',
                is_existing_group: false  // Mark as new group
            };
            
            // For Section 1, add a default competency with weight
            if (section == 1) {
                newGroup.targets = [{ description: '', weight: 0, id: null, is_existing: false }];
                newGroup.target_ids = [null];
            }
            
            kpiGroups.push(newGroup);
            renderAllGroups();
        }
        
        function removeGroup(groupId) {
            const group = kpiGroups.find(g => g.id === groupId);
            if (group && group.is_existing_group && !canDeleteGroups) {
                alert('⚠️ Cannot delete existing groups from an active template. You can only add new groups.');
                return;
            }
            
            if (confirm('Remove this KPI group and all its targets?')) {
                kpiGroups = kpiGroups.filter(group => group.id !== groupId);
                renderAllGroups();
            }
        }
        
        function addTarget(groupId) {
            const group = kpiGroups.find(g => g.id === groupId);
            if (group) {
                if (group.section_num == 1) {
                    // Section 1: Add competency with individual weight
                    group.targets.push({ description: '', weight: 0, id: null, is_existing: false });
                    group.target_ids.push(null);
                } else {
                    // Section 2: Add target description
                    group.targets.push('');
                    group.target_ids.push(null);
                }
                renderAllGroups();
            }
        }
        
        function removeTarget(groupId, targetIndex) {
            const group = kpiGroups.find(g => g.id === groupId);
            if (group) {
                // Check if this is an existing target (has ID) and if deletion is allowed
                const isExisting = group.section_num == 1 ? 
                    group.targets[targetIndex].is_existing : 
                    (group.target_ids[targetIndex] !== null && group.target_ids[targetIndex] !== undefined);
                
                if (isExisting && !canDeleteTargets) {
                    alert('⚠️ Cannot delete existing targets from an active template. You can only add new ones or rename existing ones.');
                    return;
                }
                
                if (group.targets.length > 1) {
                    group.targets.splice(targetIndex, 1);
                    group.target_ids.splice(targetIndex, 1);
                    if (group.target_existing) group.target_existing.splice(targetIndex, 1);
                    renderAllGroups();
                } else {
                    alert('Each group must have at least one target');
                }
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
            const groupDeleteDisabled = group.is_existing_group && !canDeleteGroups;
            const groupDeleteTitle = groupDeleteDisabled ? 'Cannot delete existing groups from active template' : '';
            const groupCardClass = group.is_existing_group ? 'existing-group' : '';
            
            if (group.section_num == 1) {
                // Section 1: Each competency has its own weight field
                group.targets.forEach((target, index) => {
                    const isExisting = target.is_existing;
                    const deleteDisabled = isExisting && !canDeleteTargets;
                    const deleteTitle = deleteDisabled ? 'Cannot delete existing competencies from active template' : '';
                    const targetClass = isExisting ? 'existing-target' : '';
                    
                    // Determine badge styling based on mode
                    let badgeHtml = '';
                    if (isExisting) {
                        badgeHtml = '<span class="existing-badge"><i class="fas fa-history"></i> Existing</span>';
                    } else {
                        badgeHtml = '<span class="new-badge"><i class="fas fa-plus"></i> New</span>';
                    }
                    
                    targetsHtml += `
                        <div class="target-item ${targetClass}">
                            <div class="row">
                                <div class="col-md-5">
                                    <input type="text" class="form-control form-control-sm" 
                                           value="${escapeHtml(target.description)}"
                                           placeholder="Competency name (e.g., Initiative)"
                                           onchange="updateTarget(${group.id}, ${index}, 'description', this.value)">
                                    ${badgeHtml}
                                </div>
                                <div class="col-md-4">
                                    <div class="input-group">
                                        <input type="number" class="form-control form-control-sm individual-weight-input" 
                                               step="0.01" min="0" max="100"
                                               value="${target.weight}"
                                               placeholder="Weight"
                                               onchange="updateTarget(${group.id}, ${index}, 'weight', this.value)">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <button type="button" class="btn btn-sm ${deleteDisabled ? 'btn-secondary' : 'btn-danger'}" 
                                            onclick="removeTarget(${group.id}, ${index})"
                                            ${deleteDisabled ? 'disabled' : ''}
                                            title="${deleteTitle}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                });
            } else {
                // Section 2: Targets with auto-distributed weights
                group.targets.forEach((target, index) => {
                    const isExisting = group.target_ids[index] !== null && group.target_ids[index] !== undefined;
                    const deleteDisabled = isExisting && !canDeleteTargets;
                    const deleteTitle = deleteDisabled ? 'Cannot delete existing targets from active template' : '';
                    const targetClass = isExisting ? 'existing-target' : '';
                    
                    // Calculate what weight this target would get (for display only)
                    const displayWeight = group.weight > 0 && group.targets.length > 0 ? (group.weight / group.targets.length).toFixed(2) : 0;
                    
                    // Determine badge styling based on mode
                    let badgeHtml = '';
                    if (isExisting) {
                        badgeHtml = '<span class="existing-badge"><i class="fas fa-history"></i> Existing</span>';
                    } else {
                        badgeHtml = '<span class="new-badge"><i class="fas fa-plus"></i> New</span>';
                    }
                    
                    targetsHtml += `
                        <div class="target-item ${targetClass}">
                            <div class="row">
                                <div class="col-md-8">
                                    <input type="text" class="form-control form-control-sm" 
                                           value="${escapeHtml(target)}"
                                           placeholder="Enter target description or measurable goal"
                                           onchange="updateTarget(${group.id}, ${index}, null, this.value)">
                                    ${badgeHtml}
                                </div>
                                <div class="col-md-2">
                                    <small class="text-muted">Weight: ${displayWeight}%</small>
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-sm ${deleteDisabled ? 'btn-secondary' : 'btn-danger'}" 
                                            onclick="removeTarget(${group.id}, ${index})"
                                            ${deleteDisabled ? 'disabled' : ''}
                                            title="${deleteTitle}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                });
            }
            
            // Determine group badge styling based on mode
            let groupBadgeHtml = '';
            if (group.is_existing_group) {
                groupBadgeHtml = '<span class="existing-badge"><i class="fas fa-history"></i> Existing Group</span>';
            } else {
                groupBadgeHtml = '<span class="new-badge"><i class="fas fa-plus"></i> New Group</span>';
            }
            
            return `
                <div class="group-card ${groupCardClass}" data-group-id="${group.id}">
                    <div class="group-header">
                        <div class="row w-100">
                            <div class="col-md-5">
                                <input type="text" class="form-control" 
                                       value="${escapeHtml(group.kpi_group)}"
                                       placeholder="Group Name"
                                       onchange="updateGroupField(${group.id}, 'kpi_group', this.value)">
                                ${groupBadgeHtml}
                            </div>
                            <div class="col-md-3">
                                ${group.section_num == 1 ? 
                                    `<small class="text-muted">Group total: <span id="group-total-${group.id}">0</span>%</small>` :
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
                                <button type="button" class="btn btn-sm ${groupDeleteDisabled ? 'btn-secondary' : 'btn-danger'}" 
                                        onclick="removeGroup(${group.id})"
                                        ${groupDeleteDisabled ? 'disabled' : ''}
                                        title="${groupDeleteTitle}">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="target-list">
                        <label class="small text-muted">
                            ${group.section_num == 1 ? 'Competencies / Measurable Indicators:' : 'Targets / Measurable Indicators:'}
                        </label>
                        ${targetsHtml}
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addTarget(${group.id})">
                            <i class="fas fa-plus"></i> Add ${group.section_num == 1 ? 'Competency' : 'Target'}
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
                    // Section 1: Sum individual competency weights
                    let groupTotal = 0;
                    group.targets.forEach(target => {
                        const weight = parseFloat(target.weight) || 0;
                        groupTotal += weight;
                    });
                    section1Total += groupTotal;
                    
                    // Update group total display
                    $(`#group-total-${group.id}`).text(groupTotal.toFixed(2));
                } else {
                    // Section 2: Sum group weights
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
                    alert('Please enter a name for all groups');
                    return false;
                }
                
                if (group.section_num == 1) {
                    // Validate Section 1 competencies
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
                    // Validate Section 2 targets
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
            
            // Prepare data for submission
            const groupsToSave = kpiGroups.map(group => {
                if (group.section_num == 1) {
                    return {
                        section: 'Section 1',
                        kpi_group: group.kpi_group,
                        weight: 0, // Not used for section 1
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