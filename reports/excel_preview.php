<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once __DIR__ . '/../config/db.php';

// Get parameters from URL
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$department = isset($_GET['department']) ? $_GET['department'] : '';
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'overall';
$employee = isset($_GET['employee']) ? $_GET['employee'] : '';
$threshold = isset($_GET['threshold']) ? intval($_GET['threshold']) : 60;
$top_count = isset($_GET['top_count']) ? intval($_GET['top_count']) : 10;
$custom_dept = isset($_GET['custom_dept']) ? $_GET['custom_dept'] : '';
$custom_min_score = isset($_GET['custom_min_score']) ? intval($_GET['custom_min_score']) : 0;
$custom_max_score = isset($_GET['custom_max_score']) ? intval($_GET['custom_max_score']) : 100;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Excel Preview - KPI Report</title>
    <link rel="stylesheet" href="../asset/universal.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f5f5f5;
            padding: 15px;
        }
        
        .preview-container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .preview-toolbar {
            background: white;
            border-bottom: 1px solid #e0e0e0;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            position: sticky;
            top: 0;
            z-index: 1000;
            background: white;
        }
        
        .toolbar-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-download {
            background: #1e7e34;
            color: white;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-download:hover {
            background: #155d27;
            transform: translateY(-1px);
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-back:hover {
            background: #5a6268;
        }
        
        .btn-print {
            background: #28a745;
            color: white;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .report-preview-content {
            padding: 20px 24px;
            background: white;
            overflow-x: auto;
        }
        /* Table wrapper for horizontal scroll */
        .table-wrapper {
            overflow-x: auto;
            margin-bottom: 20px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }

        .table-wrapper .excel-table {
            margin-bottom: 0;
            min-width: 800px;
        }
        /* Excel-style table */
        .excel-table {
            width: 100%;
            border-collapse: collapse;
            font-family: 'Segoe UI', 'Inter', sans-serif;
            font-size: 11px;
            margin-bottom: 20px;
            table-layout: fixed;
        }

        .excel-table th,
        .excel-table td {
            border: 1px solid #d0d0d0;
            padding: 8px 10px;
            text-align: left;
            vertical-align: top;
            word-wrap: break-word;
        }

        .excel-table th {
            background: #f3f3f3;
            font-weight: 600;
            font-size: 11px;
            color: #1e293b;
            border-bottom: 2px solid #c0c0c0;
            white-space: nowrap;
        }

        .excel-table td {
            white-space: normal;
        }

        /* Specific column widths */
        .excel-table th:nth-child(1) { width: 10%; }  /* KPI Code */
        .excel-table th:nth-child(2) { width: 15%; }  /* KPI Group */
        .excel-table th:nth-child(3) { width: 35%; }  /* Description */
        .excel-table th:nth-child(4) { width: 10%; }  /* Score */
        .excel-table th:nth-child(5) { width: 10%; }  /* Weight */
        .excel-table th:nth-child(6) { width: 20%; }  /* Weighted Score */

        /* For tables with different column counts */
        .excel-table.cols-5 th:nth-child(1) { width: 15%; }
        .excel-table.cols-5 th:nth-child(2) { width: 20%; }
        .excel-table.cols-5 th:nth-child(3) { width: 30%; }
        .excel-table.cols-5 th:nth-child(4) { width: 15%; }
        .excel-table.cols-5 th:nth-child(5) { width: 20%; }

        .excel-table tr:hover {
            background: #f8f9fa;
        }

        /* Ensure tables are scrollable on small screens */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-bottom: 20px;
        }

        /* Right align numeric columns */
        .excel-table td:nth-child(4),
        .excel-table td:nth-child(5),
        .excel-table td:nth-child(6),
        .excel-table th:nth-child(4),
        .excel-table th:nth-child(5),
        .excel-table th:nth-child(6) {
            text-align: center;
        }

        /* Section total row styling */
        .excel-table tr:last-child td {
            background: #f0f0f0;
            font-weight: bold;
        }
        .excel-header {
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 15px;
        }
        
        .excel-header h2 {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 5px;
        }
        
        .excel-header p {
            font-size: 11px;
            color: #64748b;
            margin-bottom: 3px;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            display: none;
        }
        
        .loading-content {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        
        .loading-content h4 {
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .loading-content p {
            font-size: 11px;
        }
        
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #1e7e34;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media print {
            .preview-toolbar {
                display: none;
            }
            body {
                background: white;
                padding: 0;
                margin: 0;
            }
            .preview-container {
                box-shadow: none;
                margin: 0;
                padding: 0;
                max-width: 100%;
            }
            .report-preview-content {
                padding: 10px 15px;
            }
            .excel-table th,
            .excel-table td {
                padding: 4px 8px;
                font-size: 9px;
            }
            @page {
                size: landscape;
                margin: 0.5in;
            }
        }
        
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: 600;
        }
        
        .status-excellent { background: #06d6a0; color: white; }
        .status-good { background: #118ab2; color: white; }
        .status-average { background: #ffd166; color: #333; }
        .status-critical { background: #fb8500; color: white; }
        .status-risk { background: #ef476f; color: white; }
        
        .summary-stats {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
            padding: 10px 15px;
            min-width: 120px;
            text-align: center;
        }
        
        .stat-box .number {
            font-size: 20px;
            font-weight: 700;
        }
        
        .stat-box .label {
            font-size: 10px;
            margin-top: 3px;
        }
        
        @media (max-width: 768px) {
            .report-preview-content {
                padding: 15px;
            }
            .excel-table {
                font-size: 9px;
            }
            .excel-table th,
            .excel-table td {
                padding: 4px 6px;
            }
        }
    </style>
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner"></div>
            <h4>Preparing Excel Export...</h4>
            <p>Please wait while we generate your Excel file</p>
        </div>
    </div>
    
    <div class="preview-container">
        <div class="preview-toolbar">
            <div>
                <h4 class="mb-0"><i class="fas fa-file-excel me-2" style="color: #1e7e34;"></i>Excel Preview</h4>
                <small class="text-muted">Review your data before exporting to Excel</small>
            </div>
            <div class="toolbar-buttons">
                <button class="btn-back" onclick="goBack()">
                    <i class="fas fa-arrow-left me-2"></i>Back to Reports
                </button>
                <button class="btn-print" onclick="printReport()">
                    <i class="fas fa-print me-2"></i>Print
                </button>
                <button class="btn-download" onclick="downloadExcel()">
                    <i class="fas fa-download me-2"></i>Download Excel
                </button>
            </div>
        </div>
        
        <div class="report-preview-content" id="reportContent">
            <div class="text-center py-5">
                <div class="spinner-border text-success"></div>
                <p class="mt-3">Loading Excel preview...</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Get all parameters from URL
        const urlParams = new URLSearchParams(window.location.search);
        
        // Debug: Log the employee parameter
        console.log('Employee from URL:', urlParams.get('employee'));
        console.log('Report type:', urlParams.get('report_type'));
        
        // Load the report content in Excel format
        async function loadReport() {
            const container = document.getElementById('reportContent');
            
            // Get all parameters from URL - including employee
            const params = new URLSearchParams({
                year: urlParams.get('year') || '<?php echo $year; ?>',
                department: urlParams.get('department') || '',
                report_type: urlParams.get('report_type') || 'overall',
                employee: urlParams.get('employee') || '',
                threshold: urlParams.get('threshold') || 60,
                top_count: urlParams.get('top_count') || 10,
                custom_dept: urlParams.get('custom_dept') || '',
                custom_min_score: urlParams.get('custom_min_score') || 0,
                custom_max_score: urlParams.get('custom_max_score') || 100,
                preview: 'true'
            });
            
            console.log('Loading with params:', params.toString());
            
            try {
                const response = await fetch('export_excel_preview.php?' + params.toString());
                const html = await response.text();
                container.innerHTML = html;
            } catch (error) {
                console.error('Error:', error);
                container.innerHTML = '<div class="alert alert-danger">Error loading Excel preview. Please try again.</div>';
            }
        }
        
        // Download as Excel file
        async function downloadExcel() {
            const loadingOverlay = document.getElementById('loadingOverlay');
            loadingOverlay.style.display = 'flex';
            
            const params = new URLSearchParams({
                year: urlParams.get('year') || '<?php echo $year; ?>',
                department: urlParams.get('department') || '',
                report_type: urlParams.get('report_type') || 'overall',
                employee: urlParams.get('employee') || '',
                threshold: urlParams.get('threshold') || 60,
                top_count: urlParams.get('top_count') || 10,
                custom_dept: urlParams.get('custom_dept') || '',
                custom_min_score: urlParams.get('custom_min_score') || 0,
                custom_max_score: urlParams.get('custom_max_score') || 100,
                download: '1'  // Add flag to indicate download (optional, can be used in export_excel_preview.php)
            });
            
            try {
                const response = await fetch('export_excel_preview.php?' + params.toString());
                const html = await response.text();
                
                // Create a Blob with the HTML content
                const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
                const link = document.createElement('a');
                const url = URL.createObjectURL(blob);
                link.href = url;
                link.download = `kpi_report_${params.get('report_type')}_${params.get('year')}.xls`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            } catch (error) {
                console.error('Download error:', error);
                alert('Error generating Excel file. Please try again.');
            } finally {
                loadingOverlay.style.display = 'none';
            }
        }
        
        function printReport() {
            window.print();
        }
        
        function goBack() {
            const params = new URLSearchParams();
            params.set('report_type', urlParams.get('report_type') || 'overall');
            params.set('year', urlParams.get('year') || '<?php echo $year; ?>');
            
            if (urlParams.get('department')) params.set('department', urlParams.get('department'));
            if (urlParams.get('employee')) params.set('employee', urlParams.get('employee'));
            if (urlParams.get('threshold')) params.set('threshold', urlParams.get('threshold'));
            if (urlParams.get('top_count')) params.set('top_count', urlParams.get('top_count'));
            if (urlParams.get('custom_dept')) params.set('custom_dept', urlParams.get('custom_dept'));
            if (urlParams.get('custom_min_score')) params.set('custom_min_score', urlParams.get('custom_min_score'));
            if (urlParams.get('custom_max_score')) params.set('custom_max_score', urlParams.get('custom_max_score'));
            
            window.location.href = 'reporting.php?' + params.toString();
        }
        
        // Load report on page load
        loadReport();
    </script>
</body>
</html>