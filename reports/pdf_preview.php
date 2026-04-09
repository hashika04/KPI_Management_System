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
    <title>PDF Preview - KPI Report</title>
    <link rel="stylesheet" href="../asset/universal.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/html2pdf.js@0.10.1/dist/html2pdf.bundle.min.js"></script>
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
    
    /* Preview Container */
    .preview-container {
        max-width: 1100px;
        margin: 0 auto;
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    
    /* Toolbar for preview */
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
        background: #4361ee;
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
        background: #3a56d4;
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
    
    /* Report Content Area */
    .report-preview-content {
        padding: 24px 28px;
        background: white;
    }
    
    /* Rating badges */
    .rating-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 10px;
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
        border-radius: 10px;
        padding: 12px;
        text-align: center;
    }
    
    .stat-value {
        font-size: 22px;
        font-weight: 700;
    }
    
    .stat-card div:last-child {
        font-size: 10px;
        margin-top: 3px;
    }
    
    /* Tables */
    .performance-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .performance-table th,
    .performance-table td {
        padding: 6px 10px;
        text-align: left;
        border-bottom: 1px solid #e9ecef;
        font-size: 10px;
    }
    
    .performance-table th {
        background: #f8f9fa;
        font-weight: 600;
        font-size: 10px;
        color: #6c757d;
    }
    
    /* Chart containers */
    .chart-container {
        position: relative;
        height: 200px;
        width: 100%;
        margin: 10px 0;
    }
    
    /* Info box */
    .info-box {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 10px 14px;
        border-left: 3px solid #4361ee;
    }
    
    .info-box h5 {
        font-size: 12px;
        margin-bottom: 6px;
    }
    
    .info-box p, .info-box table {
        font-size: 10px;
        margin-bottom: 3px;
    }
    
    /* Loading overlay */
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
        border-top: 3px solid #4361ee;
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
    
    /* Podium styling */
    .podium-gold {
        background: linear-gradient(135deg, #ffd700, #ffb347);
        color: #333;
        border-radius: 10px;
        padding: 12px;
    }
    
    .podium-silver {
        background: linear-gradient(135deg, #c0c0c0, #a8a8a8);
        color: white;
        border-radius: 10px;
        padding: 12px;
    }
    
    .podium-bronze {
        background: linear-gradient(135deg, #cd7f32, #b87333);
        color: white;
        border-radius: 10px;
        padding: 12px;
    }
    
    .podium-gold h3, .podium-silver h4, .podium-bronze h4 {
        font-size: 14px;
        margin-bottom: 3px;
    }
    
    .podium-gold p, .podium-silver p, .podium-bronze p {
        font-size: 10px;
        margin-bottom: 3px;
    }
    
    .podium-gold .display-4, .podium-silver .h2, .podium-bronze .h2 {
        font-size: 18px;
        font-weight: bold;
    }
    
    /* Headings */
    h4 {
        font-size: 14px;
        margin-bottom: 8px;
    }
    
    h5 {
        font-size: 12px;
        margin-bottom: 6px;
        font-weight: 600;
    }
    
    /* Spacing */
    .mb-4 {
        margin-bottom: 16px !important;
    }
    
    .mb-3 {
        margin-bottom: 10px !important;
    }
    
    .mt-3 {
        margin-top: 10px !important;
    }
    
    .mt-4 {
        margin-top: 16px !important;
    }
    
    .mt-5 {
        margin-top: 20px !important;
    }
    
    /* Badges */
    .badge {
        font-size: 9px;
        padding: 2px 6px;
        border-radius: 10px;
    }
    
    /* Alert boxes */
    .alert-custom-success,
    .alert-custom-warning {
        border-radius: 8px;
        padding: 8px 12px;
        font-size: 10px;
        margin-bottom: 12px;
    }
    
    .alert-custom-success h5,
    .alert-custom-warning h5 {
        font-size: 11px;
        margin-bottom: 4px;
    }
    
    /* Card wrapper */
    .report-card-wrapper {
        margin-bottom: 16px;
        break-inside: avoid;
        page-break-inside: avoid;
    }
    
    .card-header-custom {
        padding: 10px 16px;
        margin-bottom: 0;
    }
    
    .card-header-custom h3 {
        font-size: 14px;
    }
    
    .card-body-custom {
        padding: 16px;
    }
    
    /* Table responsive */
    .table-responsive {
        overflow-x: auto;
    }
    
    /* Row and column spacing */
    .row {
        margin-bottom: 0;
    }
    
    .col-md-3, .col-md-4, .col-md-6 {
        margin-bottom: 10px;
    }
    
    /* Hide export buttons in print and PDF */
    .no-print {
        display: block;
    }
    
    /* Print styles - Optimized for A4 */
    @media print {
        .preview-toolbar {
            display: none !important;
        }
        
        .no-print {
            display: none !important;
        }
        
        /* Hide any export buttons inside the report */
        .export-buttons,
        .btn-export-pdf,
        .btn-export-excel,
        .btn-print,
        button[onclick*="exportToPDF"],
        button[onclick*="exportToExcel"],
        button[onclick*="printReport"] {
            display: none !important;
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
            padding: 15px 20px;
        }
        
        /* Ensure proper page breaks */
        .report-card-wrapper {
            break-inside: avoid;
            page-break-inside: avoid;
            margin-bottom: 15px;
        }
        
        .chart-container {
            break-inside: avoid;
            page-break-inside: avoid;
            height: 180px;
        }
        
        .performance-table th,
        .performance-table td {
            padding: 4px 8px;
            font-size: 9px;
        }
        
        .stat-card {
            break-inside: avoid;
            page-break-inside: avoid;
        }
        
        .info-box {
            break-inside: avoid;
            page-break-inside: avoid;
        }
        
        /* Ensure colors print correctly */
        .stat-card,
        .rating-badge,
        .podium-gold,
        .podium-silver,
        .podium-bronze,
        .alert-custom-success,
        .alert-custom-warning {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
    
    /* Small screens */
    @media (max-width: 768px) {
        .report-preview-content {
            padding: 15px;
        }
        
        .chart-container {
            height: 180px;
        }
        
        .stat-value {
            font-size: 18px;
        }
    }
</style>
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner"></div>
            <h4>Generating PDF...</h4>
            <p>Please wait while we prepare your document</p>
        </div>
    </div>
    
    <div class="preview-container">
        <div class="preview-toolbar no-print">
            <div>
                <h4 class="mb-0"><i class="fas fa-file-pdf me-2"></i>Report Preview</h4>
                <small class="text-muted">Review your report before downloading</small>
            </div>
            <div class="toolbar-buttons">
                <button class="btn-back" onclick="goBack()">
                    <i class="fas fa-arrow-left me-2"></i>Back to Reports
                </button>
                <button class="btn-print" onclick="printReport()">
                    <i class="fas fa-print me-2"></i>Print
                </button>
                <button class="btn-download" onclick="downloadPDF()">
                    <i class="fas fa-download me-2"></i>Download PDF
                </button>
            </div>
        </div>
        
        <div class="report-preview-content" id="reportContent">
            <!-- Report content will be loaded here -->
            <div class="text-center py-5">
                <div class="spinner-border text-primary"></div>
                <p class="mt-3">Loading report preview...</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Get all parameters from URL
        const urlParams = new URLSearchParams(window.location.search);
        
        // Load the report content
        async function loadReport() {
            const container = document.getElementById('reportContent');
            
            // Build URL for generate_report.php
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
                preview: 'true' // Flag to indicate preview mode
            });
            
            try {
                const response = await fetch('generate_report.php?' + params.toString());
                const html = await response.text();
                container.innerHTML = html;
                
                // Remove any export buttons from the loaded content
                const exportButtons = container.querySelectorAll('.export-buttons, .btn-export-pdf, .btn-export-excel, .btn-print');
                exportButtons.forEach(btn => btn.remove());
                
                // Re-execute scripts
                const scripts = container.querySelectorAll("script");
                scripts.forEach(oldScript => {
                    const newScript = document.createElement("script");
                    newScript.text = oldScript.text;
                    document.body.appendChild(newScript);
                    oldScript.remove();
                });
            } catch (error) {
                container.innerHTML = '<div class="alert alert-danger">Error loading report. Please try again.</div>';
            }
        }
        
        // Download PDF
        async function downloadPDF() {
            const loadingOverlay = document.getElementById('loadingOverlay');
            loadingOverlay.style.display = 'flex';
            
            const element = document.getElementById('reportContent');
            const originalHeight = element.style.height;
            const originalOverflow = element.style.overflow;
            
            // Ensure proper rendering for PDF
            element.style.height = 'auto';
            element.style.overflow = 'visible';
            
            const opt = {
                margin: [0.5, 0.5, 0.5, 0.5],
                filename: `kpi_report_${urlParams.get('report_type')}_${urlParams.get('year') || '<?php echo $year; ?>'}.pdf`,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { 
                    scale: 2, 
                    letterRendering: true,
                    useCORS: true,
                    logging: false
                },
                jsPDF: { 
                    unit: 'in', 
                    format: 'a4', 
                    orientation: 'portrait'
                }
            };
            
            try {
                await html2pdf().set(opt).from(element).save();
            } catch (error) {
                console.error('PDF generation error:', error);
                alert('Error generating PDF. Please try again.');
            } finally {
                // Restore original styles
                element.style.height = originalHeight;
                element.style.overflow = originalOverflow;
                loadingOverlay.style.display = 'none';
            }
        }
        
        // Print report
        function printReport() {
            window.print();
        }
        
        // Go back to reports page
        function goBack() {
            // Preserve all filters when going back
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