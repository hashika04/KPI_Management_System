<?php
// staff_masterlist/kpi_configuration.php
include("../includes/auth.php");
include("../config/db.php");
$activePage = 'config';

// Check if user is supervisor
if ($_SESSION['position'] !== 'Supervisor') {
    header("Location: stafflist.php");
    exit();
}

$employee_success = '';
$employee_error = '';
$template_success = '';
$template_error = '';

// ==================== KPI TEMPLATE ACTIONS (UNCHANGED) ====================
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $template_id = intval($_GET['id']);
    
    if ($action == 'activate') {
        // Deactivate all templates first
        $conn->query("UPDATE kpi_templates SET status = 'inactive'");
        $sql = "UPDATE kpi_templates SET status = 'active' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $template_id);
        if ($stmt->execute()) {
            $template_success = "Template activated successfully!";
        }
    } elseif ($action == 'delete') {
        // Check if template has associated KPI data
        $check_sql = "SELECT COUNT(*) as count FROM kpi_data WHERE template_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $template_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $count = $check_result->fetch_assoc()['count'];
        
        if ($count > 0) {
            $template_error = "Cannot delete template as it has $count KPI records associated.";
        } else {
            $sql = "DELETE FROM kpi_templates WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $template_id);
            if ($stmt->execute()) {
                $template_success = "Template deleted successfully!";
            }
        }
    }
}

// ==================== EMPLOYEE MANAGEMENT ====================
if (isset($_POST['employee_action'])) {
    $action = $_POST['employee_action'];
    
    if ($action == 'add') {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $staff_code = trim($_POST['staff_code']);
        $department = trim($_POST['department']);
        $position = trim($_POST['position']);
        
        // Check for duplicate staff code or email
        $check_sql = "SELECT id FROM staff WHERE staff_code = ? OR email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $staff_code, $email);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $error_message = "Staff code or email already exists!";
        } else {
            $sql = "INSERT INTO staff (full_name, email, staff_code, department, position) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssss", $full_name, $email, $staff_code, $department, $position);
            if ($stmt->execute()) {
                $employee_success = "Employee added successfully!";
                $_SESSION['departments_updated'] = true;
            } else {
                $employee_error = "Error adding employee: " . $conn->error;
            }
        }
    } elseif ($action == 'edit') {
        $id = intval($_POST['employee_id']);
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $staff_code = trim($_POST['staff_code']);
        $department = trim($_POST['department']);
        $position = trim($_POST['position']);
        
        $sql = "UPDATE staff SET full_name = ?, email = ?, staff_code = ?, department = ?, position = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $full_name, $email, $staff_code, $department, $position, $id);
        if ($stmt->execute()) {
            $employee_success = "Employee updated successfully!";
            $_SESSION['departments_updated'] = true;
        } else {
            $employee_error = "Error updating employee: " . $conn->error;
        }
    } elseif ($action == 'delete') {
        $id = intval($_POST['employee_id']);
        
        // Check if employee has KPI data
        $check_sql = "SELECT COUNT(*) as count FROM kpi_data WHERE Name = (SELECT full_name FROM staff WHERE id = ?)";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $count = $check_stmt->get_result()->fetch_assoc()['count'];
        
        if ($count > 0) {
            $error_message = "Cannot delete employee as they have $count KPI records.";
        } else {
            $sql = "DELETE FROM staff WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $employee_success = "Employee removed successfully!";
                $_SESSION['departments_updated'] = true;
            } else {
                $employee_error = "Error deleting employee: " . $conn->error;
            }
        }
    }
}

// Fetch all templates (KPI)
$templates_query = "SELECT * FROM kpi_templates ORDER BY year DESC";
$templates_result = $conn->query($templates_query);

// Get the currently active template ID
$active_template_query = "SELECT id, year FROM kpi_templates WHERE status = 'active' LIMIT 1";
$active_result = $conn->query($active_template_query);
$active_template = $active_result ? $active_result->fetch_assoc() : null;

// Fetch all employees
$employees_query = "SELECT * FROM staff ORDER BY id DESC";
$employees_result = $conn->query($employees_query);

// Get unique departments from staff table (for dropdown)
$depts_query = "SELECT DISTINCT department FROM staff WHERE department IS NOT NULL AND department != '' ORDER BY department";
$depts_result = $conn->query($depts_query);
$departments = [];
while ($row = $depts_result->fetch_assoc()) {
    $departments[] = $row['department'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration - KPI System</title>
    <link rel="stylesheet" href="../asset/universal.css">
    <link rel="stylesheet" href="../asset/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
    /* Root variables - matching reporting page */
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
        background: #fcf2fa;
        padding: 85px 45px 40px;
        min-height: 100vh;
    }
    
    /* Main content area matching reporting page */
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
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 4px;
        color: var(--text-main);
        letter-spacing: -0.3px;
    }

    .reports-subtitle {
        font-size: 13px;
        color: var(--text-muted);
        margin-bottom: 15px;
    }
    
    /* Card wrapper styles - matching reporting page */
    .config-card {
        background: white;
        border-radius: 16px;
        border: 1px solid var(--border-soft);
        overflow: hidden;
        margin-bottom: 24px;
    }
    
    .card-header-custom {
        padding: 14px 20px;
        border-bottom: 1px solid var(--border-soft);
        background: #fafafa;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
    }
    
    .card-header-custom h3 {
        font-size: 16px;
        font-weight: 600;
        margin: 0;
        color: var(--text-main);
    }
    
    .card-body-custom {
        padding: 20px;
    }
    
    /* Button styling - matching reporting page */
    .btn-primary-custom {
        background: #e83e8c;
        border: none;
        border-radius: 8px;
        padding: 6px 16px;
        font-size: 13px;
        font-weight: 500;
        color: white;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .btn-primary-custom:hover {
        background: var(--primary-dark);
        color: white;
    }
    
    /* Templates Grid - preserved but with refined styling */
    .templates-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 16px;
        margin-top: 16px;
    }
    
    .template-card {
        transition: transform 0.2s, box-shadow 0.2s;
        border-radius: 16px;
        border: 1px solid var(--border-soft);
        position: relative;
        padding: 16px;
    }
    
    .template-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(75, 21, 53, 0.08);
    }
    
    .active-template {
        border-left: 3px solid #28a745;
        background-color: #f8fff8;
    }
    
    .draft-template {
        border-left: 3px solid #ffc107;
        background-color: #fffbf0;
    }
    
    .previous-template {
        border-left: 3px solid #6c757d;
        background-color: #f8f9fa;
    }
    
    .status-badge {
        position: absolute;
        top: 12px;
        right: 12px;
    }
    
    .status-badge .badge {
        font-size: 10px;
        padding: 3px 8px;
    }
    
    .weight-badge {
        font-size: 11px;
        padding: 4px 8px;
        border-radius: 16px;
        display: inline-block;
    }
    
    .action-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 12px;
    }
    
    .btn-sm {
        padding: 4px 10px;
        font-size: 11px;
        border-radius: 16px;
    }
    
    .info-message {
        margin-top: 10px;
        padding-top: 6px;
        border-top: 1px solid var(--border-soft);
        font-size: 11px;
    }
    
    .alert {
        border-radius: 10px;
        margin-bottom: 16px;
        padding: 10px 16px;
        font-size: 12px;
    }
    
    .card-title {
        font-weight: 600;
        font-size: 15px;
        color: var(--text-main);
        margin-bottom: 6px;
    }
    
    .card-subtitle {
        font-size: 12px;
        color: #6c757d;
    }
    
    /* Employee table styling */
    .employee-table th, 
    .employee-table td {
        vertical-align: middle;
        padding: 8px 12px;
        font-size: 12px;
    }
    
    .employee-table th {
        font-size: 12px;
        font-weight: 600;
        background-color: #f8f9fa;
    }
    
    .department-hint {
        font-size: 11px;
        color: #6c757d;
        margin-top: 4px;
    }
    
    .department-hint i {
        margin-right: 3px;
        font-size: 10px;
    }
    
    /* Modal styling */
    .modal-content {
        border-radius: 12px;
    }
    
    .modal-header {
        border-bottom: 1px solid var(--border-soft);
        background: #fafafa;
        padding: 12px 16px;
    }
    
    .modal-header h5 {
        font-size: 15px;
        margin: 0;
    }
    
    .modal-body {
        padding: 16px;
        font-size: 13px;
    }
    
    .modal-footer {
        padding: 12px 16px;
    }
    
    /* Form styling - compact */
    .form-label {
        font-size: 12px;
        margin-bottom: 4px;
        font-weight: 500;
    }
    
    .form-control, .form-select {
        font-size: 13px;
        padding: 6px 10px;
        border-radius: 8px;
        border: 1px solid var(--border-soft);
    }
    
    .form-control:focus, .form-select:focus {
        box-shadow: none;
        border-color: var(--primary);
    }
    
    /* Table styling */
    .table {
        font-size: 12px;
    }
    
    .table th, .table td {
        padding: 8px 12px;
        vertical-align: middle;
    }
    
    /* Button group styling */
    .btn-group-sm > .btn, .btn-sm {
        padding: 4px 10px;
        font-size: 11px;
        border-radius: 6px;
    }
    
    /* Badge styling */
    .badge {
        font-size: 10px;
        padding: 3px 8px;
        border-radius: 12px;
    }
    
    /* Icon sizing */
    .fas, .far {
        font-size: 12px;
    }
    
    /* Margin and spacing adjustments */
    .mb-4 {
        margin-bottom: 20px !important;
    }
    
    .mb-3 {
        margin-bottom: 12px !important;
    }
    
    .mt-3 {
        margin-top: 12px !important;
    }
    
    .mt-4 {
        margin-top: 20px !important;
    }
    
    .gap-2 {
        gap: 6px !important;
    }
    
    /* Progress bar styling */
    .progress {
        height: 6px;
        border-radius: 3px;
    }
    
    /* List group styling */
    .list-group-item {
        padding: 10px 12px;
        font-size: 12px;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .dashboard {
            margin-left: 0;
            padding: 20px 15px;
        }
        
        .templates-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }
        
        .card-header-custom {
            flex-direction: column;
            align-items: flex-start;
            padding: 12px 16px;
        }
        
        .card-body-custom {
            padding: 16px;
        }
        
        .reports-header h1 {
            font-size: 20px;
        }
    }
    
    /* Print styles */
    @media print {
        .no-print {
            display: none !important;
        }
        
        .config-card {
            break-inside: avoid;
            page-break-inside: avoid;
            margin-bottom: 15px;
        }
        
        .card-header-custom {
            padding: 10px 15px;
        }
        
        .card-body-custom {
            padding: 15px;
        }
    }

    /* Card hover effect */
    .template-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 28px rgba(0,0,0,0.12) !important;
    }

    /* Button hover effects */
    .action-buttons .btn:hover {
        transform: translateY(-1px);
    }

    .action-buttons .btn-outline-info:hover {
        background: #0dcaf0;
        color: white !important;
    }

    .action-buttons .btn-outline-success:hover {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white !important;
    }

    .action-buttons .btn-outline-danger:hover {
        background: #ef476f;
        color: white !important;
        border-color: #ef476f !important;
    }
</style>
</head>
<body>
    <div class="dashboard">
        <?php include("../includes/sidebar.php"); ?>

        <div class="reports-content">
            <!-- Header - EXACT MATCH with reporting page -->
            <div class="reports-header">
                <div>
                    <h1>Configuration</h1>
                    <p class="reports-subtitle">Manage KPI templates and employees</p>
                </div>
            </div>
            
            <!-- Success/Error Messages -->
            <?php if ($template_success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $template_success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($template_error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $template_error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- ==================== SECTION 1: KPI TEMPLATE MANAGEMENT ==================== -->
            <div class="config-card">
                <div class="card-header-custom">
                    <h3><i class="fas fa-file-alt me-2" style="color: #e83e8c;"></i> KPI Template Management</h3>
                    <a href="create_kpi_template.php" class="btn-primary-custom">
                        <i class="fas fa-plus"></i> Create New Template
                    </a>
                </div>
                <div class="card-body-custom">
                    <p class="reports-subtitle" style="margin-bottom: 20px;">Create and manage KPI templates for different years</p>
                    
                    <!-- Templates Grid -->
                                        <div class="templates-grid">
                                            <?php while($template = $templates_result->fetch_assoc()): 
                                                $is_active = ($template['status'] == 'active');
                                                $has_kpi_data = false;
                                                
                                                $check_data_sql = "SELECT COUNT(*) as count FROM kpi_data WHERE template_id = ?";
                                                $check_data_stmt = $conn->prepare($check_data_sql);
                                                $check_data_stmt->bind_param("i", $template['id']);
                                                $check_data_stmt->execute();
                                                $data_result = $check_data_stmt->get_result();
                                                $data_count = $data_result->fetch_assoc()['count'];
                                                $has_kpi_data = ($data_count > 0);
                                                
                                                $is_draft = ($template['status'] == 'inactive' && !$has_kpi_data);
                                                $is_previous = (!$is_active && $has_kpi_data);
                                            ?>
                                            <div class="card template-card 
                                                    <?php echo $is_active ? 'active-template' : ($is_draft ? 'draft-template' : 'previous-template'); ?>"
                                                    style="border-radius: 20px; border: none; background: #ffffff; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer; position: relative; overflow: hidden;">
                                                    
                                                    <!-- Decorative gradient border for active templates -->
                                                    <?php if($is_active): ?>
                                                    <div style="position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #28a745, #20c997, #28a745);"></div>
                                                    <?php elseif($is_draft): ?>
                                                    <div style="position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #ffc107, #ffca2c, #ffc107);"></div>
                                                    <?php elseif($is_previous): ?>
                                                    <div style="position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #6c757d, #868e96, #6c757d);"></div>
                                                    <?php endif; ?>
                                                    
                                                    <div class="card-body" style="padding: 20px;">
                                                        <div class="status-badge" style="position: absolute; top: 16px; right: 16px;">
                                                            <?php if($is_active): ?>
                                                                <span class="badge" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; letter-spacing: 0.3px; box-shadow: 0 2px 4px rgba(40,167,69,0.2);">
                                                                    <i class="fas fa-check-circle me-1" style="font-size: 10px;"></i> Active
                                                                </span>
                                                            <?php elseif($is_draft): ?>
                                                                <span class="badge" style="background: linear-gradient(135deg, #ffc107 0%, #ffca2c 100%); padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; letter-spacing: 0.3px; color: #856404; box-shadow: 0 2px 4px rgba(255,193,7,0.2);">
                                                                    <i class="fas fa-pen me-1" style="font-size: 10px;"></i> Draft
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="badge" style="background: linear-gradient(135deg, #6c757d 0%, #868e96 100%); padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; letter-spacing: 0.3px; box-shadow: 0 2px 4px rgba(108,117,125,0.2);">
                                                                    <i class="fas fa-archive me-1" style="font-size: 10px;"></i> Inactive
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <div style="margin-bottom: 16px;">
                                                            <h5 class="card-title" style="font-size: 18px; font-weight: 700; color: #1a1a2e; margin-bottom: 6px; padding-right: 70px;">
                                                                <?php echo htmlspecialchars($template['template_name']); ?>
                                                            </h5>
                                                            <h6 class="card-subtitle" style="font-size: 13px; color: #e83e8c; font-weight: 500; margin-bottom: 0;">
                                                                <i class="far fa-calendar-alt me-1" style="font-size: 11px;"></i> Year: <?php echo $template['year']; ?>
                                                            </h6>
                                                        </div>
                                                        
                                                        <!-- Weight indicators with progress bars -->
                                                        <div class="mt-3" style="margin-bottom: 16px;">
                                                            <div class="row" style="margin: 0 -6px;">
                                                                <div class="col-6" style="padding: 0 6px;">
                                                                    <div style="background: #f8f9fa; border-radius: 12px; padding: 10px; text-align: center;">
                                                                        <small class="text-muted" style="font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Section 1</small>
                                                                        <div class="weight-badge" style="margin-top: 4px; font-size: 16px; font-weight: 700; background: linear-gradient(135deg, #0dcaf0 0%, #0aa2c9 100%); color: white; padding: 4px 8px; border-radius: 25px; display: inline-block; min-width: 60px;">
                                                                            <?php echo $template['section1_weight']; ?>%
                                                                        </div>
                                                                        <div class="progress" style="height: 4px; margin-top: 8px; background: #e9ecef; border-radius: 2px;">
                                                                            <div class="progress-bar" style="width: <?php echo $template['section1_weight']; ?>%; background: linear-gradient(90deg, #0dcaf0, #0aa2c9); border-radius: 2px;"></div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-6" style="padding: 0 6px;">
                                                                    <div style="background: #f8f9fa; border-radius: 12px; padding: 10px; text-align: center;">
                                                                        <small class="text-muted" style="font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Section 2</small>
                                                                        <div class="weight-badge" style="margin-top: 4px; font-size: 16px; font-weight: 700; background: linear-gradient(135deg, #e83e8c 0%, #d63384 100%); color: white; padding: 4px 8px; border-radius: 25px; display: inline-block; min-width: 60px;">
                                                                            <?php echo $template['section2_weight']; ?>%
                                                                        </div>
                                                                        <div class="progress" style="height: 4px; margin-top: 8px; background: #e9ecef; border-radius: 2px;">
                                                                            <div class="progress-bar" style="width: <?php echo $template['section2_weight']; ?>%; background: linear-gradient(90deg, #e83e8c, #d63384); border-radius: 2px;"></div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Metadata section -->
                                                        <div class="mt-3" style="padding: 8px 0; border-top: 1px solid #f0f0f0; margin-bottom: 12px;">
                                                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                                                                <small class="text-muted" style="font-size: 11px;">
                                                                    <i class="far fa-clock me-1"></i> Created: <?php echo date('M d, Y', strtotime($template['created_at'])); ?>
                                                                </small>
                                                                <?php if($has_kpi_data): ?>
                                                                    <small class="text-muted" style="font-size: 11px; background: #e7f3ff; padding: 2px 8px; border-radius: 12px;">
                                                                        <i class="fas fa-database me-1" style="color: #0dcaf0;"></i> Has KPI data
                                                                    </small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Action buttons -->
                                                        <div class="action-buttons" style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px;">
                                                            <a href="view_kpi_template.php?id=<?php echo $template['id']; ?>" 
                                                            class="btn" 
                                                            style="border-radius: 10px; padding: 6px 14px; font-size: 11px; font-weight: 600; background: #f8f9fa; color: #6c757d; border: 1px solid #e9ecef; transition: all 0.2s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                                                                <i class="fas fa-eye" style="font-size: 11px;"></i> View
                                                            </a>
                                                            
                                                            <?php if($is_active || $is_draft): ?>
                                                                <a href="edit_kpi_template.php?id=<?php echo $template['id']; ?>" 
                                                                class="btn" 
                                                                style="border-radius: 10px; padding: 6px 14px; font-size: 11px; font-weight: 600; background: #fff; color: #0dcaf0; border: 1px solid #0dcaf0; transition: all 0.2s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                                                                    <i class="fas fa-edit" style="font-size: 11px;"></i> Edit
                                                                </a>
                                                            <?php endif; ?>
                                                            
                                                            <?php if($is_draft): ?>
                                                                <a href="?action=activate&id=<?php echo $template['id']; ?>" 
                                                                class="btn" 
                                                                style="border-radius: 10px; padding: 6px 14px; font-size: 11px; font-weight: 600; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border: none; transition: all 0.2s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(40,167,69,0.2);"
                                                                onclick="return confirm('Activate this template? This will deactivate all others.')">
                                                                    <i class="fas fa-check-circle" style="font-size: 11px;"></i> Activate
                                                                </a>
                                                            <?php endif; ?>
                                                            
                                                            <?php if($is_draft): ?>
                                                                <a href="?action=delete&id=<?php echo $template['id']; ?>" 
                                                                class="btn" 
                                                                style="border-radius: 10px; padding: 6px 14px; font-size: 11px; font-weight: 600; background: #fff; color: #ef476f; border: 1px solid #ef476f; transition: all 0.2s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;"
                                                                onclick="return confirm('Delete this template permanently? This cannot be undone.')">
                                                                    <i class="fas fa-trash" style="font-size: 11px;"></i> Delete
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <!-- Info message -->
                                                        <div class="info-message" style="margin-top: 12px; padding-top: 10px; border-top: 1px solid #f0f0f0;">
                                                            <?php if($is_previous): ?>
                                                                <small class="text-muted" style="font-size: 10px; display: flex; align-items: center; gap: 6px; background: #f8f9fa; padding: 6px 10px; border-radius: 8px;">
                                                                    <i class="fas fa-lock" style="color: #6c757d; font-size: 10px;"></i> 
                                                                    <span>This template has been used and is read-only</span>
                                                                </small>
                                                            <?php endif; ?>
                                                            
                                                            <?php if($is_active): ?>
                                                                <small class="text-success" style="font-size: 10px; display: flex; align-items: center; gap: 6px; background: #d4edda; padding: 6px 10px; border-radius: 8px;">
                                                                    <i class="fas fa-check-circle" style="font-size: 10px;"></i> 
                                                                    <span>Currently active template</span>
                                                                </small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endwhile; ?>
                                        </div>
                                    </div>
                                </div>
                </div>
            <!-- Success/Error Messages -->
            <?php if ($employee_success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $employee_success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($employee_error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $employee_error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <!-- ==================== SECTION 2: EMPLOYEE MANAGEMENT ==================== -->
            <div class="config-card">
                <div class="card-header-custom">
                    <h3><i class="fas fa-users me-2" style="color: #e83e8c;"></i> Employee Management</h3>
                    <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                        <i class="fas fa-user-plus"></i> Add New Employee
                    </button>
                </div>
                <div class="card-body-custom">
                    <div class="table-responsive">
                        <table class="table employee-table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Staff Code</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Department</th>
                                    <th>Position</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($employees_result->num_rows > 0): ?>
                                    <?php while($emp = $employees_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($emp['staff_code']); ?></span></td>
                                            <td><strong><?php echo htmlspecialchars($emp['full_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($emp['email']); ?></td>
                                            <td><?php echo htmlspecialchars($emp['department']); ?></td>
                                            <td><?php echo htmlspecialchars($emp['position']); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-info me-1 edit-employee" 
                                                        style="border-radius: 8px; padding: 4px 10px; font-size: 11px; font-weight: 500; transition: all 0.2s ease;"
                                                        onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 2px 6px rgba(232,62,140,0.2)';"
                                                        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';"
                                                        data-id="<?php echo $emp['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($emp['full_name']); ?>"
                                                        data-email="<?php echo htmlspecialchars($emp['email']); ?>"
                                                        data-code="<?php echo htmlspecialchars($emp['staff_code']); ?>"
                                                        data-dept="<?php echo htmlspecialchars($emp['department']); ?>"
                                                        data-pos="<?php echo htmlspecialchars($emp['position']); ?>"
                                                        data-bs-toggle="modal" data-bs-target="#editEmployeeModal">
                                                    <i class="fas fa-edit" style="font-size: 11px; margin-right: 4px;"></i> Edit
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger delete-employee"
                                                        style="border-radius: 8px; padding: 4px 10px; font-size: 11px; font-weight: 500; transition: all 0.2s ease;"
                                                        onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 2px 6px rgba(239,71,111,0.2)';"
                                                        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';"
                                                        data-id="<?php echo $emp['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($emp['full_name']); ?>"
                                                        data-bs-toggle="modal" data-bs-target="#deleteEmployeeModal">
                                                    <i class="fas fa-trash" style="font-size: 11px; margin-right: 4px;"></i> Delete
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">No employees found. Click "Add New Employee" to get started.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== ADD EMPLOYEE MODAL ==================== -->
<div class="modal fade" id="addEmployeeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 20px 60px rgba(0,0,0,0.15); overflow: hidden;">
            <form method="POST" id="addEmployeeForm">
                <div class="modal-header" style="border-bottom: 1px solid #f0f0f0; background: linear-gradient(135deg, #fff 0%, #fef5f9 100%); padding: 20px 24px;">
                    <h5 class="modal-title" style="font-size: 18px; font-weight: 700; color: #1a1a2e;">
                        <i class="fas fa-user-plus me-2" style="color: #e83e8c; font-size: 18px;"></i> 
                        Add New Employee
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="font-size: 12px; opacity: 0.7;"></button>
                </div>
                
                <div class="modal-body" style="padding: 24px; background: #ffffff;">
                    <input type="hidden" name="employee_action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label" style="font-size: 13px; font-weight: 600; color: #2b2d42; margin-bottom: 8px;">
                            <i class="fas fa-user me-1" style="color: #e83e8c; font-size: 12px;"></i> Full Name <span style="color: #ef476f;">*</span>
                        </label>
                        <input type="text" name="full_name" class="form-control" required
                               style="border-radius: 12px; border: 1.5px solid #e9ecef; padding: 10px 14px; font-size: 14px; transition: all 0.2s ease; background: #fff;">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label" style="font-size: 13px; font-weight: 600; color: #2b2d42; margin-bottom: 8px;">
                            <i class="fas fa-envelope me-1" style="color: #e83e8c; font-size: 12px;"></i> Email <span style="color: #ef476f;">*</span>
                        </label>
                        <input type="email" name="email" class="form-control" required
                               style="border-radius: 12px; border: 1.5px solid #e9ecef; padding: 10px 14px; font-size: 14px; transition: all 0.2s ease; background: #fff;">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label" style="font-size: 13px; font-weight: 600; color: #2b2d42; margin-bottom: 8px;">
                            <i class="fas fa-id-card me-1" style="color: #e83e8c; font-size: 12px;"></i> Staff Code <span style="color: #ef476f;">*</span>
                        </label>
                        <input type="text" name="staff_code" class="form-control" placeholder="e.g., SA-014" required
                               style="border-radius: 12px; border: 1.5px solid #e9ecef; padding: 10px 14px; font-size: 14px; transition: all 0.2s ease; background: #fff;">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label" style="font-size: 13px; font-weight: 600; color: #2b2d42; margin-bottom: 8px;">
                            <i class="fas fa-building me-1" style="color: #e83e8c; font-size: 12px;"></i> Department
                        </label>
                        <input type="text" name="department" class="form-control" list="deptOptions" placeholder="Select existing or type new department"
                               style="border-radius: 12px; border: 1.5px solid #e9ecef; padding: 10px 14px; font-size: 14px; transition: all 0.2s ease; background: #fff;">
                        <datalist id="deptOptions">
                            <?php foreach($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>">
                            <?php endforeach; ?>
                        </datalist>
                        <div class="department-hint" style="margin-top: 8px; padding: 6px 10px; background: #f8f9fa; border-radius: 8px;">
                            <i class="fas fa-info-circle" style="color: #e83e8c; font-size: 11px;"></i> 
                            <span style="font-size: 11px; color: #6c757d;">You can type any department name. New departments will be automatically added to the list.</span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label" style="font-size: 13px; font-weight: 600; color: #2b2d42; margin-bottom: 8px;">
                            <i class="fas fa-briefcase me-1" style="color: #e83e8c; font-size: 12px;"></i> Position
                        </label>
                        <select name="position" class="form-select"
                                style="border-radius: 12px; border: 1.5px solid #e9ecef; padding: 10px 14px; font-size: 14px; background: #fff; cursor: pointer;">
                            <option value="Sales Assistant">Sales Assistant</option>
                            <option value="Senior Sales Assistant">Senior Sales Assistant</option>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer" style="border-top: 1px solid #f0f0f0; padding: 16px 24px; background: #fef5f9; gap: 12px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" 
                            style="border-radius: 12px; padding: 8px 20px; font-size: 13px; font-weight: 500; background: #fff; border: 1.5px solid #e9ecef; color: #6c757d; transition: all 0.2s ease;">
                        <i class="fas fa-times me-1" style="font-size: 12px;"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" 
                            style="border-radius: 12px; padding: 8px 24px; font-size: 13px; font-weight: 600; background: linear-gradient(135deg, #e83e8c 0%, #d63384 100%); border: none; box-shadow: 0 2px 6px rgba(232,62,140,0.3); transition: all 0.2s ease;">
                        <i class="fas fa-user-plus me-1" style="font-size: 12px;"></i> Add Employee
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== EDIT EMPLOYEE MODAL ==================== -->
<div class="modal fade" id="editEmployeeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 20px 60px rgba(0,0,0,0.15); overflow: hidden;">
            <form method="POST">
                <div class="modal-header" style="border-bottom: 1px solid #f0f0f0; background: linear-gradient(135deg, #fff 0%, #fef5f9 100%); padding: 20px 24px;">
                    <h5 class="modal-title" style="font-size: 18px; font-weight: 700; color: #1a1a2e;">
                        <i class="fas fa-user-edit me-2" style="color: #e83e8c; font-size: 18px;"></i> 
                        Edit Employee
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="font-size: 12px; opacity: 0.7;"></button>
                </div>
                
                <div class="modal-body" style="padding: 24px; background: #ffffff;">
                    <input type="hidden" name="employee_action" value="edit">
                    <input type="hidden" name="employee_id" id="edit_employee_id">
                    
                    <div class="mb-3">
                        <label class="form-label" style="font-size: 13px; font-weight: 600; color: #2b2d42; margin-bottom: 8px;">
                            <i class="fas fa-user me-1" style="color: #e83e8c; font-size: 12px;"></i> Full Name <span style="color: #ef476f;">*</span>
                        </label>
                        <input type="text" name="full_name" id="edit_full_name" class="form-control" required
                               style="border-radius: 12px; border: 1.5px solid #e9ecef; padding: 10px 14px; font-size: 14px; transition: all 0.2s ease; background: #fff;">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label" style="font-size: 13px; font-weight: 600; color: #2b2d42; margin-bottom: 8px;">
                            <i class="fas fa-envelope me-1" style="color: #e83e8c; font-size: 12px;"></i> Email <span style="color: #ef476f;">*</span>
                        </label>
                        <input type="email" name="email" id="edit_email" class="form-control" required
                               style="border-radius: 12px; border: 1.5px solid #e9ecef; padding: 10px 14px; font-size: 14px; transition: all 0.2s ease; background: #fff;">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label" style="font-size: 13px; font-weight: 600; color: #2b2d42; margin-bottom: 8px;">
                            <i class="fas fa-id-card me-1" style="color: #e83e8c; font-size: 12px;"></i> Staff Code <span style="color: #ef476f;">*</span>
                        </label>
                        <input type="text" name="staff_code" id="edit_staff_code" class="form-control" required
                               style="border-radius: 12px; border: 1.5px solid #e9ecef; padding: 10px 14px; font-size: 14px; transition: all 0.2s ease; background: #fff;">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label" style="font-size: 13px; font-weight: 600; color: #2b2d42; margin-bottom: 8px;">
                            <i class="fas fa-building me-1" style="color: #e83e8c; font-size: 12px;"></i> Department
                        </label>
                        <input type="text" name="department" id="edit_department" class="form-control" list="deptOptionsEdit" placeholder="Select existing or type new department"
                               style="border-radius: 12px; border: 1.5px solid #e9ecef; padding: 10px 14px; font-size: 14px; transition: all 0.2s ease; background: #fff;">
                        <datalist id="deptOptionsEdit">
                            <?php foreach($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>">
                            <?php endforeach; ?>
                        </datalist>
                        <div class="department-hint" style="margin-top: 8px; padding: 6px 10px; background: #f8f9fa; border-radius: 8px;">
                            <i class="fas fa-info-circle" style="color: #e83e8c; font-size: 11px;"></i> 
                            <span style="font-size: 11px; color: #6c757d;">You can type any department name. New departments will be automatically added to the list.</span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label" style="font-size: 13px; font-weight: 600; color: #2b2d42; margin-bottom: 8px;">
                            <i class="fas fa-briefcase me-1" style="color: #e83e8c; font-size: 12px;"></i> Position
                        </label>
                        <select name="position" id="edit_position" class="form-select"
                                style="border-radius: 12px; border: 1.5px solid #e9ecef; padding: 10px 14px; font-size: 14px; background: #fff; cursor: pointer;">
                            <option value="Sales Assistant">Sales Assistant</option>
                            <option value="Senior Sales Assistant">Senior Sales Assistant</option>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer" style="border-top: 1px solid #f0f0f0; padding: 16px 24px; background: #fef5f9; gap: 12px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" 
                            style="border-radius: 12px; padding: 8px 20px; font-size: 13px; font-weight: 500; background: #fff; border: 1.5px solid #e9ecef; color: #6c757d; transition: all 0.2s ease;">
                        <i class="fas fa-times me-1" style="font-size: 12px;"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" 
                            style="border-radius: 12px; padding: 8px 24px; font-size: 13px; font-weight: 600; background: linear-gradient(135deg, #e83e8c 0%, #d63384 100%); border: none; box-shadow: 0 2px 6px rgba(232,62,140,0.3); transition: all 0.2s ease;">
                        <i class="fas fa-save me-1" style="font-size: 12px;"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

    <!-- ==================== DELETE EMPLOYEE MODAL ==================== -->
    <div class="modal fade" id="deleteEmployeeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.1);">
            <form method="POST">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-soft); background: linear-gradient(135deg, #fafafa 0%, #ffffff 100%); padding: 16px 20px;">
                    <h5 class="modal-title" style="font-size: 16px; font-weight: 600; color: var(--text-main);">
                        <i class="fas fa-exclamation-triangle me-2" style="color: #ef476f; font-size: 16px;"></i> 
                        Remove Employee
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="font-size: 12px;"></button>
                </div>
                
                <div class="modal-body" style="padding: 20px;">
                    <input type="hidden" name="employee_action" value="delete">
                    <input type="hidden" name="employee_id" id="delete_employee_id">
                    
                    <div style="text-align: center; padding: 10px 0;">
                        <i class="fas fa-user-slash" style="font-size: 48px; color: #ef476f; margin-bottom: 15px;"></i>
                        <p style="font-size: 13px; color: var(--text-main); margin-bottom: 0;">
                            Are you sure you want to remove <strong id="delete_employee_name" style="color: #e83e8c;"></strong>?
                        </p>
                        <p style="font-size: 11px; color: #999; margin-top: 8px;">
                            This action cannot be undone.
                        </p>
                    </div>
                </div>
                
                <div class="modal-footer" style="border-top: 1px solid var(--border-soft); padding: 16px 20px; background: #fafafa; border-radius: 0 0 16px 16px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" 
                            style="border-radius: 10px; padding: 6px 16px; font-size: 12px; font-weight: 500; background: #f0f0f0; border: none; color: #666;">
                        <i class="fas fa-times me-1" style="font-size: 11px;"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-danger" 
                            style="border-radius: 10px; padding: 6px 20px; font-size: 12px; font-weight: 600; background: linear-gradient(135deg, #ef476f 0%, #d63384 100%); border: none; box-shadow: 0 2px 6px rgba(239,71,111,0.3); transition: all 0.2s ease;">
                        <i class="fas fa-trash me-1" style="font-size: 11px;"></i> Remove Employee
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Edit Employee Modal - populate fields
        document.querySelectorAll('.edit-employee').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('edit_employee_id').value = this.dataset.id;
                document.getElementById('edit_full_name').value = this.dataset.name;
                document.getElementById('edit_email').value = this.dataset.email;
                document.getElementById('edit_staff_code').value = this.dataset.code;
                document.getElementById('edit_department').value = this.dataset.dept;
                document.getElementById('edit_position').value = this.dataset.pos;
            });
        });
        
        // Delete Employee Modal
        document.querySelectorAll('.delete-employee').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('delete_employee_id').value = this.dataset.id;
                document.getElementById('delete_employee_name').textContent = this.dataset.name;
            });
        });
    </script>
</body>
</html>