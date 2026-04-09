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

$success_message = '';
$error_message = '';

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
            $success_message = "Template activated successfully!";
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
            $error_message = "Cannot delete template as it has $count KPI records associated.";
        } else {
            $sql = "DELETE FROM kpi_templates WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $template_id);
            if ($stmt->execute()) {
                $success_message = "Template deleted successfully!";
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
                $success_message = "Employee added successfully!";
                $_SESSION['departments_updated'] = true;
            } else {
                $error_message = "Error adding employee: " . $conn->error;
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
            $success_message = "Employee updated successfully!";
            $_SESSION['departments_updated'] = true;
        } else {
            $error_message = "Error updating employee: " . $conn->error;
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
                $success_message = "Employee removed successfully!";
                $_SESSION['departments_updated'] = true;
            } else {
                $error_message = "Error deleting employee: " . $conn->error;
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
            --text-muted: #6c757d;
            --border-soft: #e9ecef;
            --bg-main: #f0f2f5;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #fcf2fa;
        }
        
        .dashboard {
            margin-left: 200px;
            background: #fcf2fa;        /* ← PINK BACKGROUND (matching reporting) */
            padding: 76px 20px 40px;
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
        
        /* Card wrapper styles - matching reporting page */
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
        
        /* Button styling - matching reporting page */
        .btn-primary-custom {
            background: #e83e8c;
            border: none;
            border-radius: 10px;
            padding: 8px 18px;
            font-size: 14px;
            font-weight: 500;
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary-custom:hover {
            background: var(--primary-dark);
            color: white;
        }
        
        /* Templates Grid - preserved but with refined styling */
        .templates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .template-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border-radius: 20px;
            border: 1px solid var(--border-soft);
            position: relative;
        }
        
        .template-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(75, 21, 53, 0.08);
        }
        
        .active-template {
            border-left: 4px solid #28a745;
            background-color: #f8fff8;
        }
        
        .draft-template {
            border-left: 4px solid #ffc107;
            background-color: #fffbf0;
        }
        
        .previous-template {
            border-left: 4px solid #6c757d;
            background-color: #f8f9fa;
        }
        
        .status-badge {
            position: absolute;
            top: 16px;
            right: 16px;
        }
        
        .weight-badge {
            font-size: 0.9em;
            padding: 5px 10px;
            border-radius: 20px;
            display: inline-block;
        }
        
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 16px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 14px;
            border-radius: 20px;
        }
        
        .info-message {
            margin-top: 12px;
            padding-top: 8px;
            border-top: 1px solid var(--border-soft);
            font-size: 14px;
        }
        
        .alert {
            border-radius: 12px;
            margin-bottom: 20px;
            padding: 12px 20px;
        }
        
        .card-title {
            font-weight: 600;
            font-size: 18px;
            color: var(--text-main);
            margin-bottom: 8px;
        }
        
        .card-subtitle {
            font-size: 14px;
        }
        
        /* Employee table styling */
        .employee-table th, .employee-table td {
            vertical-align: middle;
            padding: 12px 16px;
        }
        
        .department-hint {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .department-hint i {
            margin-right: 4px;
        }
        
        /* Modal styling */
        .modal-content {
            border-radius: 16px;
        }
        
        .modal-header {
            border-bottom: 1px solid var(--border-soft);
            background: #fafafa;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .dashboard {
                margin-left: 0;
                padding: 20px 15px;
            }
            
            .templates-grid {
                grid-template-columns: 1fr;
            }
            
            .card-header-custom {
                flex-direction: column;
                align-items: flex-start;
            }
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
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
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
                                <?php echo $is_active ? 'active-template' : ($is_draft ? 'draft-template' : 'previous-template'); ?>">
                                <div class="card-body">
                                    <div class="status-badge">
                                        <?php if($is_active): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php elseif($is_draft): ?>
                                            <span class="badge bg-warning text-dark">Draft</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <h5 class="card-title">
                                        <?php echo htmlspecialchars($template['template_name']); ?>
                                    </h5>
                                    <h6 class="card-subtitle mb-2 text-muted">
                                        Year: <?php echo $template['year']; ?>
                                    </h6>
                                    
                                    <div class="mt-3">
                                        <div class="row mb-2">
                                            <div class="col-6">
                                                <small class="text-muted">Section 1 Weight:</small>
                                                <div class="weight-badge bg-info text-white">
                                                    <?php echo $template['section1_weight']; ?>%
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Section 2 Weight:</small>
                                                <div class="weight-badge bg-primary text-white">
                                                    <?php echo $template['section2_weight']; ?>%
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <small class="text-muted">Created: <?php echo date('M d, Y', strtotime($template['created_at'])); ?></small>
                                        <?php if($has_kpi_data): ?>
                                            <br><small class="text-muted"><i class="fas fa-database"></i> Has KPI data</small>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="action-buttons">
                                        <a href="view_kpi_template.php?id=<?php echo $template['id']; ?>" class="btn btn-sm btn-outline-info">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        
                                        <?php if($is_active || $is_draft): ?>
                                            <a href="edit_kpi_template.php?id=<?php echo $template['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if($is_draft): ?>
                                            <a href="?action=activate&id=<?php echo $template['id']; ?>" 
                                               class="btn btn-sm btn-outline-success"
                                               onclick="return confirm('Activate this template? This will deactivate all others.')">
                                                <i class="fas fa-check-circle"></i> Activate
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if($is_draft): ?>
                                            <a href="?action=delete&id=<?php echo $template['id']; ?>" 
                                               class="btn btn-sm btn-outline-danger"
                                               onclick="return confirm('Delete this template permanently? This cannot be undone.')">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="info-message">
                                        <?php if($is_previous): ?>
                                            <small class="text-muted">
                                                <i class="fas fa-lock"></i> This template has been used and is read-only
                                            </small>
                                        <?php endif; ?>
                                        
                                        <?php if($is_active): ?>
                                            <small class="text-success">
                                                <i class="fas fa-check-circle"></i> Currently active template
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

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
                                                <button class="btn btn-sm btn-outline-primary me-1 edit-employee" 
                                                        data-id="<?php echo $emp['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($emp['full_name']); ?>"
                                                        data-email="<?php echo htmlspecialchars($emp['email']); ?>"
                                                        data-code="<?php echo htmlspecialchars($emp['staff_code']); ?>"
                                                        data-dept="<?php echo htmlspecialchars($emp['department']); ?>"
                                                        data-pos="<?php echo htmlspecialchars($emp['position']); ?>"
                                                        data-bs-toggle="modal" data-bs-target="#editEmployeeModal">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger delete-employee"
                                                        data-id="<?php echo $emp['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($emp['full_name']); ?>"
                                                        data-bs-toggle="modal" data-bs-target="#deleteEmployeeModal">
                                                    <i class="fas fa-trash"></i>
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
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="addEmployeeForm">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-user-plus"></i> Add New Employee</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="employee_action" value="add">
                        <div class="mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Staff Code *</label>
                            <input type="text" name="staff_code" class="form-control" placeholder="e.g., SA-014" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department</label>
                            <input type="text" name="department" class="form-control" list="deptOptions" placeholder="Select existing or type new department">
                            <datalist id="deptOptions">
                                <?php foreach($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>">
                                <?php endforeach; ?>
                            </datalist>
                            <div class="department-hint">
                                <i class="fas fa-info-circle"></i> You can type any department name. New departments will be automatically added to the list.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Position</label>
                            <select name="position" class="form-select">
                                <option value="Sales Assistant">Sales Assistant</option>
                                <option value="Senior Sales Assistant">Senior Sales Assistant</option>
                                <option value="Supervisor">Supervisor</option>
                                <option value="Manager">Manager</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Employee</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ==================== EDIT EMPLOYEE MODAL ==================== -->
    <div class="modal fade" id="editEmployeeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-user-edit"></i> Edit Employee</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="employee_action" value="edit">
                        <input type="hidden" name="employee_id" id="edit_employee_id">
                        <div class="mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" id="edit_email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Staff Code *</label>
                            <input type="text" name="staff_code" id="edit_staff_code" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department</label>
                            <input type="text" name="department" id="edit_department" class="form-control" list="deptOptionsEdit" placeholder="Select existing or type new department">
                            <datalist id="deptOptionsEdit">
                                <?php foreach($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>">
                                <?php endforeach; ?>
                            </datalist>
                            <div class="department-hint">
                                <i class="fas fa-info-circle"></i> You can type any department name. New departments will be automatically added to the list.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Position</label>
                            <select name="position" id="edit_position" class="form-select">
                                <option value="Sales Assistant">Sales Assistant</option>
                                <option value="Senior Sales Assistant">Senior Sales Assistant</option>
                                <option value="Supervisor">Supervisor</option>
                                <option value="Manager">Manager</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ==================== DELETE EMPLOYEE MODAL ==================== -->
    <div class="modal fade" id="deleteEmployeeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Remove Employee</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="employee_action" value="delete">
                        <input type="hidden" name="employee_id" id="delete_employee_id">
                        <p>Are you sure you want to remove <strong id="delete_employee_name"></strong>?</p>
                        <p class="text-danger small">This action cannot be undone. Employees with existing KPI records cannot be deleted.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Remove Employee</button>
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