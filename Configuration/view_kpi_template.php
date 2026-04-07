<?php
// staff_masterlist/view_kpi_template.php
include("../includes/auth.php");
include("../config/db.php");

$template_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

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

// Calculate weighted score example (if needed)
$section1_total_weight = array_sum(array_column($section1_items, 'weight'));
$section2_total_weight = array_sum(array_column($section2_items, 'weight'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View KPI Template</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .table-kpi {
            margin-bottom: 30px;
        }
        .table-kpi thead {
            background-color: #f8f9fa;
        }
        .badge-weight {
            font-size: 0.85em;
            padding: 3px 8px;
        }
        .group-header {
            background-color: #e9ecef;
            font-weight: bold;
        }
        @media print {
            .no-print {
                display: none;
            }
            .container {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include("../includes/sidebar.php"); ?>
    
    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2>
                    <i class="fas fa-file-alt"></i> 
                    <?php echo htmlspecialchars($template['template_name']); ?>
                </h2>
                <p class="text-muted">
                    Year: <?php echo $template['year']; ?> | 
                    Status: 
                    <?php if($template['status'] == 'active'): ?>
                        <span class="badge bg-success">Active</span>
                    <?php elseif($template['status'] == 'inactive'): ?>
                        <span class="badge bg-secondary">Inactive</span>
                    <?php else: ?>
                        <span class="badge bg-dark">Archived</span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="col-md-4 text-end no-print">
                <a href="edit_kpi_template.php?id=<?php echo $template_id; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Edit Template
                </a>
                <a href="kpi_template_management.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <button onclick="window.print()" class="btn btn-info">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Template Configuration</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <strong>Section 1 Weight:</strong><br>
                        <span class="badge bg-info badge-weight"><?php echo $template['section1_weight']; ?>%</span>
                        <small class="text-muted">(Competency)</small>
                    </div>
                    <div class="col-md-4">
                        <strong>Section 2 Weight:</strong><br>
                        <span class="badge bg-primary badge-weight"><?php echo $template['section2_weight']; ?>%</span>
                        <small class="text-muted">(KPIs)</small>
                    </div>
                    <div class="col-md-4">
                        <strong>Created:</strong><br>
                        <?php echo date('F d, Y', strtotime($template['created_at'])); ?>
                        <?php if($template['created_by']): ?>
                            <br><small>by <?php echo htmlspecialchars($template['created_by']); ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Section 1: Competency -->
        <div class="card mb-4">
            <div class="card-header">
                <h4>Section 1: Core Competencies</h4>
                <small>Total Weight: <?php echo $section1_total_weight; ?>% (Target: <?php echo $template['section1_weight']; ?>%)</small>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-kpi">
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th width="15%">Code</th>
                            <th width="25%">Competency</th>
                            <th width="45%">Description</th>
                            <th width="10%">Weight</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($section1_items)): ?>
                            <tr>
                                <td colspan="5" class="text-center">No competency items defined</td>
                            </tr>
                        <?php else: ?>
                            <?php $counter = 1; foreach($section1_items as $item): ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo htmlspecialchars($item['kpi_code']); ?></td>
                                    <td><?php echo htmlspecialchars($item['kpi_group']); ?></td>
                                    <td><?php echo htmlspecialchars($item['kpi_description']); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-info"><?php echo $item['weight']; ?>%</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-info">
                            <td colspan="4" class="text-end"><strong>Section 1 Total:</strong></td>
                            <td class="text-center"><strong><?php echo $section1_total_weight; ?>%</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        
        <!-- Section 2: KPIs -->
        <div class="card mb-4">
            <div class="card-header">
                <h4>Section 2: Key Performance Indicators</h4>
                <small>Total Weight: <?php echo $section2_total_weight; ?>% (Target: <?php echo $template['section2_weight']; ?>%)</small>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-kpi">
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th width="15%">Code</th>
                            <th width="25%">KPI Group</th>
                            <th width="45%">Description / Target</th>
                            <th width="10%">Weight</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($section2_items)): ?>
                            <tr>
                                <td colspan="5" class="text-center">No KPI items defined</td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            $counter = 1;
                            $current_group = '';
                            foreach($section2_items as $item): 
                                if ($current_group != $item['kpi_group']) {
                                    $current_group = $item['kpi_group'];
                                    echo '<tr class="group-header">';
                                    echo '<td colspan="5"><strong>' . htmlspecialchars($current_group) . '</strong></td>';
                                    echo '</tr>';
                                }
                            ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo htmlspecialchars($item['kpi_code']); ?></td>
                                    <td><?php echo htmlspecialchars($item['kpi_group']); ?></td>
                                    <td><?php echo htmlspecialchars($item['kpi_description']); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-primary"><?php echo $item['weight']; ?>%</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-info">
                            <td colspan="4" class="text-end"><strong>Section 2 Total:</strong></td>
                            <td class="text-center"><strong><?php echo $section2_total_weight; ?>%</strong></td>
                        </tr>
                        <tr class="table-success">
                            <td colspan="4" class="text-end"><strong>GRAND TOTAL:</strong></td>
                            <td class="text-center"><strong><?php echo $section1_total_weight + $section2_total_weight; ?>%</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</body>
</html>