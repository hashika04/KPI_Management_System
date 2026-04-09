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
        --text-muted: #b08090;
        --border-soft: #e9ecef;
        --bg-main: #f0f2f5;
    }
    
    body {
        font-family: 'Inter', sans-serif;
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
    
    .report-card-wrapper {
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
    
    /* Filter bar styling */
    .filter-bar {
        background: white;
        border-radius: 16px;
        border: 1px solid var(--border-soft);
        padding: 16px 20px;
        margin-bottom: 24px;
    }
    
    .filter-bar .form-label {
        font-weight: 500;
        font-size: 12px;
        color: var(--text-muted);
        margin-bottom: 4px;
    }
    
    .filter-bar .form-select,
    .filter-bar .form-control {
        border-radius: 8px;
        border: 1px solid var(--border-soft);
        font-size: 13px;
        padding: 6px 10px;
    }
    
    .btn-primary-custom {
        background: #e83e8c;
        border: none;
        border-radius: 8px;
        padding: 6px 16px;
        font-size: 13px;
        font-weight: 500;
        color: white;
    }
    
    .btn-primary-custom:hover {
        background: var(--primary-dark);
    }
    
    /* Export buttons */
    .export-buttons {
        display: flex;
        gap: 8px;
    }
    
    .btn-export-pdf {
        background: #dc3545;
        color: white;
        border: none;
        border-radius: 6px;
        padding: 4px 12px;
        font-size: 12px;
    }
    
    .btn-export-excel {
        background: #198754;
        color: white;
        border: none;
        border-radius: 6px;
        padding: 4px 12px;
        font-size: 12px;
    }
    
    .btn-print {
        background: #6c757d;
        color: white;
        border: none;
        border-radius: 6px;
        padding: 4px 12px;
        font-size: 12px;
    }
    
    /* Rating badges */
    .rating-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 16px;
        font-size: 11px;
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
        border-radius: 12px;
        padding: 14px;
        text-align: center;
        transition: transform 0.3s;
    }
    
    .stat-card:hover {
        transform: translateY(-2px);
    }
    
    .stat-value {
        font-size: 26px;
        font-weight: 700;
    }
    
    .stat-card div:last-child {
        font-size: 12px;
        margin-top: 4px;
    }
    
    /* Tables */
    .performance-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .performance-table th,
    .performance-table td {
        padding: 8px 12px;
        text-align: left;
        border-bottom: 1px solid var(--border-soft);
        font-size: 12px;
    }
    
    .performance-table th {
        background: #f8f9fa;
        font-weight: 600;
        font-size: 12px;
        color: var(--text-muted);
    }
    
    .performance-table tr:hover {
        background: #f8f9fa;
    }
    
    /* Chart containers */
    .chart-container {
        position: relative;
        height: 250px;
        width: 100%;
        margin: 12px 0;
    }
    
    /* Alert styling */
    .alert-custom-success {
        background: #d1e7dd;
        border: none;
        border-radius: 10px;
        padding: 12px 16px;
        font-size: 12px;
    }
    
    .alert-custom-warning {
        background: #fff3cd;
        border: none;
        border-radius: 10px;
        padding: 12px 16px;
        font-size: 12px;
    }
    
    /* Info box */
    .info-box {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 12px 16px;
        border-left: 3px solid var(--primary);
        font-size: 12px;
    }
    
    .info-box h5 {
        font-size: 13px;
        margin-bottom: 8px;
    }
    
    .info-box p {
        margin-bottom: 4px;
        font-size: 12px;
    }
    
    /* Podium styling */
    .podium-gold {
        background: linear-gradient(135deg, #ffd700, #ffb347);
        color: #333;
        border-radius: 12px;
        padding: 14px;
    }
    
    .podium-silver {
        background: linear-gradient(135deg, #c0c0c0, #a8a8a8);
        color: white;
        border-radius: 12px;
        padding: 14px;
    }
    
    .podium-bronze {
        background: linear-gradient(135deg, #cd7f32, #b87333);
        color: white;
        border-radius: 12px;
        padding: 14px;
    }
    
    .podium-gold h3, .podium-silver h4, .podium-bronze h4 {
        font-size: 16px;
        margin-bottom: 4px;
    }
    
    .podium-gold p, .podium-silver p, .podium-bronze p {
        font-size: 11px;
        margin-bottom: 4px;
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
            margin-bottom: 15px;
        }
        
        body {
            background: white;
            padding: 0;
            margin: 0;
        }
        
        .reports-content {
            padding: 0;
        }
        
        .card-header-custom {
            padding: 10px 15px;
        }
        
        .card-body-custom {
            padding: 15px;
        }
        
        .performance-table th,
        .performance-table td {
            padding: 6px 10px;
            font-size: 10px;
        }
        
        .chart-container {
            height: 200px;
        }
    }
    
    /* Dropdown for employee select */
    .employee-select {
        border-radius: 8px;
        border: 1px solid var(--border-soft);
        padding: 6px 10px;
        font-size: 13px;
    }
    
    /* Threshold filter */
    .threshold-select {
        border-radius: 8px;
        border: 1px solid var(--border-soft);
        padding: 6px 10px;
        font-size: 13px;
    }
    
    /* Additional compact styles for headings */
    h4 {
        font-size: 18px;
        margin-bottom: 12px;
    }
    
    h5 {
        font-size: 14px;
        margin-bottom: 10px;
        font-weight: 600;
    }
    
    h6 {
        font-size: 13px;
        margin-bottom: 8px;
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
        font-size: 11px;
        padding: 4px 8px;
    }
    
    .table-responsive {
        font-size: 12px;
    }
    
    /* Make buttons more compact */
    button, .btn {
        font-size: 12px !important;
        padding: 5px 12px !important;
    }
    
    /* Compact form elements */
    .form-select, .form-control {
        font-size: 13px;
        padding: 5px 10px;
    }
    
    /* Smaller icons */
    .fas, .far {
        font-size: 12px;
    }
    
    /* Compact spacing for rows */
    .row {
        margin-bottom: 0;
    }
    
    .col-md-3, .col-md-4, .col-md-6 {
        margin-bottom: 12px;
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
                    <h1>Reports</h1>
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
        // Get all current filter values
        const formData = new FormData(document.getElementById('filterForm'));
        const params = new URLSearchParams(formData);
        
        // Build the preview URL with all parameters
        const previewUrl = 'pdf_preview.php?' + params.toString();
        
        // Open preview in new tab
        window.open(previewUrl, '_blank');
    }

    function exportToExcel() {
        // Get all current filter values
        const formData = new FormData(document.getElementById('filterForm'));
        const params = new URLSearchParams(formData);
        
        // Build the preview URL with all parameters
        const previewUrl = 'excel_preview.php?' + params.toString();
        
        // Open preview in new tab
        window.open(previewUrl, '_blank');
    }
    </script>
</body>
</html>