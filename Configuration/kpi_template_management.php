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
    } elseif ($action == 'archive') {
        $sql = "UPDATE kpi_templates SET status = 'archived' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $template_id);
        if ($stmt->execute()) {
            $success_message = "Template archived successfully!";
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
            $error_message = "Cannot delete template as it has $count KPI records associated. Archive it instead.";
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KPI Template Management</title>
    <link rel="stylesheet" href="../asset/universal.css">
    <link rel="stylesheet" href="../asset/dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .template-card {
            transition: transform 0.2s;
            margin-bottom: 20px;
        }
        .template-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .active-template {
            border-left: 4px solid #28a745;
            background-color: #f8fff8;
        }
        .weight-badge {
            font-size: 0.9em;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
        }
    </style>
</head>
<body>
    <div class="dashboard">
    <?php include("../includes/sidebar.php"); ?>

    <div class="reports-content">

        <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2><i class="fas fa-cog"></i> KPI Template Management</h2>
                <p class="text-muted">Create and manage KPI templates for different years</p>
            </div>
            <div class="col-md-4 text-end">
                <a href="create_kpi_template.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create New Template
                </a>
            </div>
        </div>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <?php while($template = $templates_result->fetch_assoc()): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card template-card <?php echo $template['status'] == 'active' ? 'active-template' : ''; ?>">
                        <div class="card-body">
                            <span class="status-badge">
                                <?php if($template['status'] == 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php elseif($template['status'] == 'inactive'): ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php else: ?>
                                    <span class="badge bg-dark">Archived</span>
                                <?php endif; ?>
                            </span>
                            
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
                            </div>
                            
                            <div class="mt-3">
                                <a href="edit_kpi_template.php?id=<?php echo $template['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="view_kpi_template.php?id=<?php echo $template['id']; ?>" class="btn btn-sm btn-outline-info">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php if($template['status'] != 'active' && $template['status'] != 'archived'): ?>
                                    <a href="?action=activate&id=<?php echo $template['id']; ?>" 
                                       class="btn btn-sm btn-outline-success"
                                       onclick="return confirm('Activate this template? This will deactivate all others.')">
                                        <i class="fas fa-check-circle"></i> Activate
                                    </a>
                                <?php endif; ?>
                                <?php if($template['status'] != 'archived'): ?>
                                    <a href="?action=archive&id=<?php echo $template['id']; ?>" 
                                       class="btn btn-sm btn-outline-warning"
                                       onclick="return confirm('Archive this template?')">
                                        <i class="fas fa-archive"></i> Archive
                                    </a>
                                <?php endif; ?>
                                <?php if($template['status'] == 'inactive'): ?>
                                    <a href="?action=delete&id=<?php echo $template['id']; ?>" 
                                       class="btn btn-sm btn-outline-danger"
                                       onclick="return confirm('Delete this template permanently? This cannot be undone.')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</div> 
 
    
</body>
</html>