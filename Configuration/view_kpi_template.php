<?php
// staff_masterlist/view_kpi_template.php
include("../includes/auth.php");
include("../config/db.php");
$activePage = 'config';

$template_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$template_id) {
    header("Location: configuration.php");
    exit();
}

// Fetch template data
$sql = "SELECT * FROM kpi_templates WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $template_id);
$stmt->execute();
$template = $stmt->get_result()->fetch_assoc();

if (!$template) {
    header("Location: configuration.php");
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
$is_previous = (!$is_active && $has_kpi_data);

// Fetch template items
$items_sql = "SELECT * FROM kpi_template_items WHERE template_id = ? ORDER BY section, display_order";
$items_stmt = $conn->prepare($items_sql);
$items_stmt->bind_param("i", $template_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();

$section1_items = [];
$section2_items = [];

while($item = $items_result->fetch_assoc()) {
    if ($item['section'] == 'Section 1') {
        $section1_items[] = $item;
    } else {
        $section2_items[] = $item;
    }
}

// Group Section 2 items by kpi_group and calculate group total weight
$section2_groups = [];
$group_totals = [];
foreach ($section2_items as $item) {
    if (!isset($section2_groups[$item['kpi_group']])) {
        $section2_groups[$item['kpi_group']] = [];
        $group_totals[$item['kpi_group']] = 0;
    }
    $section2_groups[$item['kpi_group']][] = $item;
    $group_totals[$item['kpi_group']] += $item['weight'];
}

// Calculate totals
$section1_total_weight = array_sum(array_column($section1_items, 'weight'));
$section2_total_weight = array_sum(array_column($section2_items, 'weight'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View KPI Template - <?php echo htmlspecialchars($template['template_name']); ?></title>
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
        background: #fcf2fa;
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
        letter-spacing: -0.3px;
    }

    .reports-subtitle {
        font-size: 12px;
        color: var(--text-muted);
        margin-bottom: 15px;
    }
    
    /* Top bar container */
    .top-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    /* Back button pill style */
    .btn-back {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 999px;
        background: white;
        color: #e83e8c;
        font-size: 12px;
        font-weight: 500;
        text-decoration: none;
        border: 1px solid #f3e5f5;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        transition: all 0.2s ease;
    }
    
    .btn-back:hover {
        background: #fdf2f8;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        color: #c2185b;
    }
    
    /* Action buttons group */
    .action-buttons {
        display: flex;
        gap: 10px;
    }
    
    .btn-edit {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 16px;
        border-radius: 999px;
        background: linear-gradient(135deg, #c070e0 0%, #e83e8c 100%);
        color: white;
        font-size: 12px;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.2s ease;
        border: none;
    }
    
    .btn-edit:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(232, 62, 140, 0.3);
        color: white;
    }
    
    .btn-print {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 16px;
        border-radius: 999px;
        background: white;
        color: #475569;
        font-size: 12px;
        font-weight: 500;
        text-decoration: none;
        border: 1px solid #e2e8f0;
        transition: all 0.2s ease;
        cursor: pointer;
    }
    
    .btn-print:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
        transform: translateY(-1px);
    }
    
    /* Modern Hero Header */
    .template-hero {
        background: linear-gradient(135deg, #c070e0 0%, #e83e8c 100%);
        border-radius: 20px;
        padding: 20px 24px;
        color: white;
        display: flex;
        align-items: center;
        gap: 16px;
        box-shadow: 0 8px 20px rgba(232, 62, 140, 0.18);
        margin-bottom: 24px;
    }
    
    .template-icon {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: rgba(255,255,255,0.15);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        backdrop-filter: blur(6px);
    }
    
    .template-info h2 {
        font-size: 20px;
        font-weight: 700;
        margin-bottom: 4px;
    }
    
    .template-meta {
        font-size: 12px;
        opacity: 0.9;
        margin-bottom: 8px;
    }
    
    .template-tags {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .tag {
        padding: 4px 12px;
        border-radius: 16px;
        font-size: 11px;
        font-weight: 500;
        background: rgba(255,255,255,0.2);
    }
    
    .tag.active {
        background: #e8f5e9;
        color: #2e7d32;
    }
    
    .tag.inactive {
        background: #f1f5f9;
        color: #475569;
    }
    
    .tag.config {
        background: rgba(255,255,255,0.25);
        color: white;
    }
    
    .tag.readonly {
        background: #fee2e2;
        color: #dc2626;
    }
    
    /* Card wrapper styles */
    .info-card {
        border-radius: 16px;
        border: 1px solid var(--border-soft);
        margin-bottom: 20px;
        background: white;
    }
    
    .info-card .card-header {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-bottom: 1px solid var(--border-soft);
        padding: 12px 16px;
        font-weight: 600;
        font-size: 14px;
        color: #1e293b;
        border-radius: 16px 16px 0 0;
    }
    
    .info-card .card-body {
        padding: 16px;
        font-size: 13px;
    }
    
    /* Section Card */
    .section-card {
        margin-bottom: 20px;
        border: 1px solid var(--border-soft);
        border-radius: 16px;
        background-color: white;
        overflow: hidden;
    }
    
    .section-header {
        padding: 12px 20px;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-bottom: 1px solid var(--border-soft);
    }
    
    .section-header h4 {
        font-weight: 700;
        font-size: 15px;
        margin-bottom: 2px;
        color: #1e293b;
    }
    
    .section-header small {
        color: #64748b;
        font-size: 11px;
    }
    
    .section-badge {
        font-size: 11px;
        padding: 3px 10px;
        border-radius: 16px;
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
    
    /* Table Styles */
    .table-kpi {
        margin-bottom: 0;
    }
    
    .table-kpi thead th {
        background: #f1f5f9;
        font-weight: 600;
        font-size: 12px;
        color: #1e293b;
        border-bottom: 2px solid #e2e8f0;
        padding: 10px 12px;
    }
    
    .table-kpi tbody td {
        padding: 8px 12px;
        vertical-align: middle;
        font-size: 12px;
    }
    
    .group-header-row td {
        background: #fefce8;
        font-weight: 600;
        color: #854d0e;
        padding: 8px 12px;
    }
    
    .group-header-row td strong {
        font-size: 13px;
    }
    
    .group-weight-badge {
        background: #e2e8f0;
        color: #1e293b;
        padding: 3px 10px;
        border-radius: 16px;
        font-size: 11px;
        font-weight: 500;
        margin-left: 10px;
    }
    
    .badge-weight {
        font-size: 11px;
        padding: 3px 8px;
        border-radius: 16px;
    }
    
    .badge-weight-section1 {
        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        color: white;
    }
    
    .badge-weight-section2 {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        color: white;
    }
    
    .total-row {
        background: #f1f5f9;
        font-weight: 600;
    }
    
    .total-row td {
        font-size: 12px;
        padding: 8px 12px;
    }
    
    .grand-total-row {
        background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
        font-weight: 700;
    }
    
    .grand-total-row td {
        font-size: 13px;
        padding: 10px 12px;
    }
    
    .target-indent {
        padding-left: 28px !important;
        color: #475569;
    }
    
    .target-code {
        color: #64748b;
        font-size: 11px;
    }
    
    /* Readonly Alert */
    .alert-readonly {
        background: linear-gradient(135deg, #fff3e0 0%, #ffe8cc 100%);
        border-left: 3px solid #ff9800;
        border-radius: 12px;
        padding: 10px 16px;
        font-size: 12px;
        margin-bottom: 20px;
    }
    
    /* Additional compact styles */
    .form-label {
        font-size: 12px;
        margin-bottom: 4px;
        font-weight: 500;
    }
    
    .btn {
        font-size: 12px;
        padding: 5px 12px;
        border-radius: 8px;
    }
    
    .fas, .far {
        font-size: 12px;
    }
    
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
    
    .badge {
        font-size: 10px;
        padding: 3px 8px;
        border-radius: 12px;
    }
    
    @media (max-width: 768px) {
        .dashboard {
            margin-left: 0;
            padding: 20px 15px;
        }
        
        .template-hero {
            flex-direction: column;
            text-align: center;
            padding: 16px 20px;
        }
        
        .top-bar {
            flex-direction: column;
            gap: 10px;
            align-items: stretch;
        }
        
        .action-buttons {
            justify-content: center;
        }
        
        .reports-header h1 {
            font-size: 20px;
        }
        
        .section-header {
            padding: 10px 16px;
        }
        
        .table-kpi thead th,
        .table-kpi tbody td {
            padding: 6px 10px;
        }
    }
    
    @media print {
        .no-print {
            display: none !important;
        }
        .dashboard {
            margin: 0;
            padding: 0;
        }
        .reports-content {
            padding: 15px;
        }
        .template-hero {
            background: #e83e8c;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .badge-weight-section1, .badge-weight-section2,
        .section-badge, .tag, .total-row, .grand-total-row,
        .group-header-row {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .info-card .card-header,
        .section-header {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>
</head>
<body>
    <div class="dashboard">
        <?php include("../includes/sidebar.php"); ?>

        <div class="reports-content">
            <!-- Top Bar with Back Button and Action Buttons -->
            <div class="top-bar no-print">
                <a href="configuration.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i>
                    Back to Templates
                </a>
                <div class="action-buttons">
                    <a href="edit_kpi_template.php?id=<?php echo $template_id; ?>" class="btn-edit">
                        <i class="fas fa-edit"></i>
                        Edit Template
                    </a>
                    <button onclick="window.print()" class="btn-print">
                        <i class="fas fa-print"></i>
                        Print
                    </button>
                </div>
            </div>

            <!-- Modern Hero Header -->
            <div class="template-hero">
                <div class="template-icon">
                    <i class="fas fa-eye"></i>
                </div>
                <div class="template-info">
                    <h2><?php echo htmlspecialchars($template['template_name']); ?></h2>
                    <div class="template-meta">
                        KPI Template • Year <?php echo $template['year']; ?>
                        &nbsp; | &nbsp;
                        <i class="far fa-calendar-alt me-1"></i>
                        Created: <?php echo date('M d, Y', strtotime($template['created_at'])); ?>
                        <?php if($template['created_by']): ?>
                            &nbsp; | &nbsp;
                            <i class="fas fa-user me-1"></i>
                            by <?php echo htmlspecialchars($template['created_by']); ?>
                        <?php endif; ?>
                    </div>
                    <div class="template-tags">
                        <?php if($template['status'] == 'active'): ?>
                            <span class="tag active">
                                <i class="fas fa-check-circle me-1"></i> Active Template
                            </span>
                        <?php elseif($template['status'] == 'inactive'): ?>
                            <span class="tag inactive">
                                <i class="fas fa-clock me-1"></i> Inactive Template
                            </span>
                        <?php endif; ?>
                        <span class="tag config">
                            <i class="fas fa-chart-line me-1"></i> KPI Configuration
                        </span>
                        <?php if($is_previous): ?>
                            <span class="tag readonly">
                                <i class="fas fa-lock me-1"></i> Read Only (Has KPI Data)
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Readonly Alert -->
            <?php if($is_previous): ?>
                <div class="alert alert-readonly alert-dismissible fade show mb-4 no-print" role="alert">
                    <i class="fas fa-lock me-2"></i>
                    <strong>Read-Only Mode:</strong> This template has associated KPI data and cannot be edited to preserve historical records.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Template Info Card -->
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-info-circle me-2" style="color: #e83e8c;"></i> Template Information
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="text-muted small mb-1">Section 1 Weight (Competency)</div>
                            <h4 class="mb-0">
                                <span class="badge badge-weight-section1"><?php echo $template['section1_weight']; ?>%</span>
                            </h4>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="text-muted small mb-1">Section 2 Weight (KPIs)</div>
                            <h4 class="mb-0">
                                <span class="badge badge-weight-section2"><?php echo $template['section2_weight']; ?>%</span>
                            </h4>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="text-muted small mb-1">Total Weight</div>
                            <h4 class="mb-0 text-success">
                                <?php echo $template['section1_weight'] + $template['section2_weight']; ?>%
                            </h4>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Section 1: Competency -->
            <div class="section-card">
                <div class="section-header">
                    <h4>
                        <i class="fas fa-star text-info me-2"></i> Section 1: Core Competencies
                        <span class="section-badge badge-section1">Individual weights</span>
                    </h4>
                    <small>Total Weight: <strong><?php echo $section1_total_weight; ?>%</strong> (Target: <?php echo $template['section1_weight']; ?>%)</small>
                </div>
                <div class="table-responsive">
                    <table class="table table-kpi">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th width="12%">Code</th>
                                <th width="23%">Competency Group</th>
                                <th width="50%">Description / Measurable Indicator</th>
                                <th width="10%">Weight</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($section1_items)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No competency items defined</td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $counter = 1; 
                                $current_group = '';
                                foreach($section1_items as $item): 
                                    if ($current_group != $item['kpi_group']):
                                        $current_group = $item['kpi_group'];
                                ?>
                                    <tr class="group-header-row">
                                        <td colspan="5">
                                            <i class="fas fa-folder-open me-2"></i>
                                            <strong><?php echo htmlspecialchars($current_group); ?></strong>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><code><?php echo htmlspecialchars($item['kpi_code']); ?></code></td>
                                    <td><?php echo htmlspecialchars($item['kpi_group']); ?></td>
                                    <td><?php echo htmlspecialchars($item['kpi_description']); ?></td>
                                    <td class="text-center">
                                        <span class="badge badge-weight-section1"><?php echo $item['weight']; ?>%</span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="4" class="text-end"><strong>Section 1 Total:</strong></td>
                                <td class="text-center"><strong><?php echo $section1_total_weight; ?>%</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
            <!-- Section 2: KPIs - Showing Group Totals -->
            <div class="section-card">
                <div class="section-header">
                    <h4>
                        <i class="fas fa-chart-line text-primary me-2"></i> Section 2: Key Performance Indicators
                        <span class="section-badge badge-section2">Auto-distributed weights</span>
                    </h4>
                    <small>Total Weight: <strong><?php echo $section2_total_weight; ?>%</strong> (Target: <?php echo $template['section2_weight']; ?>%)</small>
                </div>
                <div class="table-responsive">
                    <table class="table table-kpi">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th width="12%">Code</th>
                                <th width="23%">KPI Group</th>
                                <th width="45%">Description / Target</th>
                                <th width="8%">Weight</th>
                                <th width="7%">Group Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($section2_items)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No KPI items defined</td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $counter = 1; 
                                $current_group = '';
                                foreach($section2_items as $item): 
                                    if ($current_group != $item['kpi_group']):
                                        $current_group = $item['kpi_group'];
                                        $group_total = $group_totals[$current_group];
                                ?>
                                    <tr class="group-header-row">
                                        <td colspan="6">
                                            <i class="fas fa-folder-open me-2"></i>
                                            <strong><?php echo htmlspecialchars($current_group); ?></strong>
                                            <span class="group-weight-badge">Group Total: <?php echo $group_total; ?>%</span>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><code class="target-code"><?php echo htmlspecialchars($item['kpi_code']); ?></code></td>
                                    <td><?php echo htmlspecialchars($item['kpi_group']); ?></td>
                                    <td class="target-indent">
                                        <i class="fas fa-angle-right me-2 text-muted"></i>
                                        <?php echo htmlspecialchars($item['kpi_description']); ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-weight-section2"><?php echo $item['weight']; ?>%</span>
                                    </td>
                                    <td class="text-center text-muted">
                                        <?php if ($item == $section2_groups[$current_group][0]): ?>
                                            <strong><?php echo $group_total; ?>%</strong>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="5" class="text-end"><strong>Section 2 Total:</strong></td>
                                <td class="text-center"><strong><?php echo $section2_total_weight; ?>%</strong></td>
                            </tr>
                            <tr class="grand-total-row">
                                <td colspan="5" class="text-end"><strong>GRAND TOTAL:</strong></td>
                                <td class="text-center"><strong><?php echo $section1_total_weight + $section2_total_weight; ?>%</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
            <!-- Summary Card for Group Totals -->
            <div class="info-card no-print">
                <div class="card-header">
                    <i class="fas fa-chart-pie me-2" style="color: #e83e8c;"></i> KPI Group Summary
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($group_totals as $group_name => $group_total): ?>
                            <div class="col-md-4 mb-3">
                                <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded-3">
                                    <div>
                                        <div class="text-muted small mb-1"><?php echo htmlspecialchars($group_name); ?></div>
                                        <h5 class="mb-0"><?php echo $group_total; ?>%</h5>
                                    </div>
                                    <div class="progress" style="width: 100px; height: 6px;">
                                        <div class="progress-bar bg-primary" style="width: <?php echo ($group_total / $section2_total_weight) * 100; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>