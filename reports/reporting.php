<?php
include("../includes/auth.php");
$activePage = 'reports';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit();
}

// Get filter parameters
// Get latest year if not selected
if (isset($_GET['year'])) {
    $selected_year = $_GET['year'];
} else {
    $latest_year_query = "SELECT MAX(YEAR(Date)) as latest_year FROM kpi_data";
    $latest_year_result = mysqli_query($conn, $latest_year_query);
    $latest_year_row = mysqli_fetch_assoc($latest_year_result);
    $selected_year = $latest_year_row['latest_year'] ?? date('Y');
}

$selected_dept = isset($_GET['department']) ? $_GET['department'] : '';
$selected_report = isset($_GET['report_type']) ? $_GET['report_type'] : 'overall';

// Get available years from data
$years_query = "SELECT DISTINCT YEAR(Date) as year FROM kpi_data ORDER BY year DESC";
$years_result = mysqli_query($conn, $years_query);

// Get departments
$depts_query = "SELECT DISTINCT department FROM staff WHERE department IS NOT NULL";
$depts_result = mysqli_query($conn, $depts_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - KPI System</title>
    <link rel="stylesheet" href="../asset/universal.css">
    <link rel="stylesheet" href="../asset/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/html2pdf.js@0.10.1/dist/html2pdf.bundle.min.js"></script>
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
            --text-muted: #6c757d;
            --border-soft: #e9ecef;
            --bg-main: #f0f2f5;
        }
        
        body {
            font-family: 'Inter', sans-serif;
        }
        
        /* Main content area matching config page */
        .reports-content {
            padding: 24px 32px;
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
            color: #e83e8c; 
            margin-bottom: 8px;
            font-weight: 700;
            
        }
        
        .reports-subtitle {
            color: var(--text-muted);
            font-size: 14px;
        }
        
        /* Card wrapper styles - matching config page */
        .report-card-wrapper {
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
        
        /* Filter bar styling */
        .filter-bar {
            background: white;
            border-radius: 20px;
            border: 1px solid var(--border-soft);
            padding: 20px 24px;
            margin-bottom: 32px;
        }
        
        .filter-bar .form-label {
            font-weight: 500;
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 6px;
        }
        
        .filter-bar .form-select,
        .filter-bar .form-control {
            border-radius: 10px;
            border: 1px solid var(--border-soft);
            font-size: 14px;
        }
        
        .btn-primary-custom {
            background: #e83e8c; 
            border: none;
            border-radius: 10px;
            padding: 8px 18px;
            font-size: 14px;
            font-weight: 500;
            color: white;
            
        }
        
        .btn-primary-custom:hover {
            background: var(--primary-dark);
        }
        
        /* Export buttons */
        .export-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn-export-pdf {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 6px 14px;
            font-size: 13px;
        }
        
        .btn-export-excel {
            background: #198754;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 6px 14px;
            font-size: 13px;
        }
        
        .btn-print {
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 6px 14px;
            font-size: 13px;
        }
        
        /* Rating badges */
        .rating-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
        }
        
        .rating-top { background: #06d6a0; color: white; }
        .rating-good { background: #118ab2; color: white; }
        .rating-average { background: #ffd166; color: #333; }
        .rating-critical { background: #fb8500; color: white; }
        .rating-risk { background: #ef476f; color: white; }
        
        /* Stat cards */
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
        }
        
        /* Tables */
        .performance-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .performance-table th,
        .performance-table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border-soft);
        }
        
        .performance-table th {
            background: #f8f9fa;
            font-weight: 600;
            font-size: 13px;
            color: var(--text-muted);
        }
        
        .performance-table tr:hover {
            background: #f8f9fa;
        }
        
        /* Chart containers */
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
            margin: 16px 0;
        }
        
        /* Alert styling */
        .alert-custom-success {
            background: #d1e7dd;
            border: none;
            border-radius: 12px;
            padding: 16px 20px;
        }
        
        .alert-custom-warning {
            background: #fff3cd;
            border: none;
            border-radius: 12px;
            padding: 16px 20px;
        }
        
        /* Info box */
        .info-box {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 16px 20px;
            border-left: 4px solid var(--primary);
        }
        
        /* Podium styling */
        .podium-gold {
            background: linear-gradient(135deg, #ffd700, #ffb347);
            color: #333;
            border-radius: 16px;
            padding: 20px;
        }
        
        .podium-silver {
            background: linear-gradient(135deg, #c0c0c0, #a8a8a8);
            color: white;
            border-radius: 16px;
            padding: 20px;
        }
        
        .podium-bronze {
            background: linear-gradient(135deg, #cd7f32, #b87333);
            color: white;
            border-radius: 16px;
            padding: 20px;
        }
        
        /* Print styles */
        @media print {
            .no-print {
                display: none !important;
            }
            
            .report-card-wrapper {
                box-shadow: none;
                border: 1px solid #ddd;
                break-inside: avoid;
                page-break-inside: avoid;
            }
            
            body {
                background: white;
                padding: 0;
                margin: 0;
            }
            
            .reports-content {
                padding: 0;
            }
        }
        
        /* Dropdown for employee select */
        .employee-select {
            border-radius: 10px;
            border: 1px solid var(--border-soft);
            padding: 8px 12px;
            font-size: 14px;
        }
        
        /* Threshold filter */
        .threshold-select {
            border-radius: 10px;
            border: 1px solid var(--border-soft);
            padding: 8px 12px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <?php include("../includes/sidebar.php"); ?>

        <div class="reports-content">
            <!-- Header -->
            <div class="reports-header">
                <div>
                    <h1>KPI Reports</h1>
                    <p class="reports-subtitle">Comprehensive performance analytics and reporting</p>
                </div>
            </div>
            
            <!-- Filter Section -->
            <div class="filter-bar no-print">
                <form method="GET" action="" id="filterForm" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Report Type</label>
                        <select name="report_type" class="form-select" onchange="this.form.submit()">
                            <option value="overall" <?php echo $selected_report == 'overall' ? 'selected' : ''; ?>>📊 Overall Performance Report</option>
                            <option value="individual" <?php echo $selected_report == 'individual' ? 'selected' : ''; ?>>👤 Individual Employee Report</option>
                            <option value="department" <?php echo $selected_report == 'department' ? 'selected' : ''; ?>>📈 Department Performance Report</option>
                            <option value="trend" <?php echo $selected_report == 'trend' ? 'selected' : ''; ?>>📉 Performance Trend Report</option>
                            <option value="low" <?php echo $selected_report == 'low' ? 'selected' : ''; ?>>⚠️ At-Risk Staff Report</option>
                            <option value="top" <?php echo $selected_report == 'top' ? 'selected' : ''; ?>>🏆 Top Performers Report</option>
                            <option value="training" <?php echo $selected_report == 'training' ? 'selected' : ''; ?>>🧠 Training Needs Summary</option>
                            <option value="builder" <?php echo $selected_report == 'builder' ? 'selected' : ''; ?>>🔧 Custom Report Builder</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Year</label>
                        <select name="year" class="form-select" onchange="this.form.submit()">
                            <?php while($row = mysqli_fetch_assoc($years_result)): ?>
                                <option value="<?php echo $row['year']; ?>" <?php echo $selected_year == $row['year'] ? 'selected' : ''; ?>>
                                    <?php echo $row['year']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Department</label>
                        <select name="department" class="form-select" onchange="this.form.submit()">
                            <option value="">All Departments</option>
                            <?php while($row = mysqli_fetch_assoc($depts_result)): ?>
                                <option value="<?php echo $row['department']; ?>" <?php echo $selected_dept == $row['department'] ? 'selected' : ''; ?>>
                                    <?php echo $row['department']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="button" class="btn btn-primary-custom w-100" onclick="generateReport()">
                            <i class="fas fa-sync-alt"></i> Generate Report
                        </button>
                    </div>
                </form>
            </div>

            <!-- Report Content -->
            <div id="reportContent">
                <?php include 'generate_report.php'; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    
    function generateReport() {
        const container = document.getElementById('reportContent');

        container.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary"></div>
                <p class="mt-3">Loading report...</p>
            </div>
        `;
        
        const formData = new FormData(document.getElementById('filterForm'));
        const params = new URLSearchParams(formData);

        // ✅ NEW: build URL
        const url = 'generate_report.php?' + params.toString();

        // ✅ NEW: update browser URL (THIS IS THE FIX)
        history.replaceState(null, '', '?' + params.toString());

        fetch(url)
            .then(response => response.text())
            .then(html => {
                container.innerHTML = html;

                // ✅ IMPORTANT: re-run scripts (fix charts disappearing)
                const scripts = container.querySelectorAll("script");
                scripts.forEach(oldScript => {
                    const newScript = document.createElement("script");
                    newScript.text = oldScript.text;
                    document.body.appendChild(newScript);
                    oldScript.remove();
                });
            })
            .catch(error => {
                container.innerHTML = '<div class="alert alert-danger">Error loading report</div>';
            });
    }

    function exportToPDF() {
        const element = document.getElementById('reportCard');
        const opt = {
            margin: [0.5, 0.5, 0.5, 0.5],
            filename: 'kpi_report.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, letterRendering: true },
            jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
        };
        html2pdf().set(opt).from(element).save();
    }

    function exportToExcel() {
        const params = new URLSearchParams(window.location.search);
        window.location.href = 'export_excel.php?' + params.toString();
    }
    </script>
</body>
</html>