<?php
// staff_masterlist/kpi_template_management.php
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

// Handle template actions
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

// Fetch all templates
$templates_query = "SELECT * FROM kpi_templates ORDER BY year DESC";
$templates_result = $conn->query($templates_query);

// Get the currently active template ID
$active_template_query = "SELECT id, year FROM kpi_templates WHERE status = 'active' LIMIT 1";
$active_result = $conn->query($active_template_query);
$active_template = $active_result ? $active_result->fetch_assoc() : null;
$active_template_id = $active_template ? $active_template['id'] : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KPI Template Management</title>
    <link rel="stylesheet" href="../asset/universal.css">
    <link rel="stylesheet" href="../asset/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Match reports.css exactly */
        .reports-content {
            padding: 24px 32px;
            background: var(--bg-main);
            min-height: 100vh;
        }
        
        .reports-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 32px;
        }
        
        .reports-header h1 {
            font-size: 32px;
            color: var(--primary);
            margin-bottom: 8px;
            font-weight: 700;
        }
        
        .reports-subtitle {
            color: var(--text-muted);
            font-size: 14px;
        }
        
        .btn-create {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--bg-card);
            border: 1px solid var(--border-soft);
            border-radius: 12px;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-main);
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .btn-create:hover {
            background: var(--surface-soft);
            border-color: var(--primary);
            color: var(--primary);
        }
        
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
        
        .text-muted {
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <?php include("../includes/sidebar.php"); ?>

        <div class="reports-content">
            <!-- Header - exactly matching reports page structure -->
            <div class="reports-header">
                <div>
                    <h1>KPI Template Management</h1>
                    <p class="reports-subtitle">Create and manage KPI templates for different years</p>
                </div>
                <a href="create_kpi_template.php" class="btn-create">
                    <i class="fas fa-plus"></i> Create New Template
                </a>
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>