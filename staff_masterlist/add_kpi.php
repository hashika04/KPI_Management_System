<?php
// staff_masterlist/add_kpi.php
include("../includes/auth.php");
include("../config/db.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staffId = $_POST['staff_id'];
    $staffName = $_POST['staff_name'];
    $kpiCode = $_POST['kpi_code'];
    $score = floatval($_POST['score']);
    $date = $_POST['date'];
    
    // Get the active template ID for the year of the KPI
    $year = date('Y', strtotime($date));
    $templateSql = "SELECT id FROM kpi_templates WHERE year = ? AND status = 'active' LIMIT 1";
    $templateStmt = $conn->prepare($templateSql);
    $templateStmt->bind_param("i", $year);
    $templateStmt->execute();
    $templateResult = $templateStmt->get_result();
    $template = $templateResult->fetch_assoc();
    $templateId = $template ? $template['id'] : null;
    
    // If no active template for the year, get the default/inactive one
    if (!$templateId) {
        $fallbackSql = "SELECT id FROM kpi_templates WHERE year = ? LIMIT 1";
        $fallbackStmt = $conn->prepare($fallbackSql);
        $fallbackStmt->bind_param("i", $year);
        $fallbackStmt->execute();
        $fallbackResult = $fallbackStmt->get_result();
        $fallbackTemplate = $fallbackResult->fetch_assoc();
        $templateId = $fallbackTemplate ? $fallbackTemplate['id'] : null;
    }
    
    // Insert KPI data with template_id
    $sql = "INSERT INTO kpi_data (Date, Name, KPI_Code, Score, template_id) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssdi", $date, $staffName, $kpiCode, $score, $templateId);
    
    if ($stmt->execute()) {
        // Redirect back to the referring page
        $redirect = $_SERVER['HTTP_REFERER'] ?? 'stafflist.php';
        header("Location: $redirect");
    } else {
        echo "Error adding KPI: " . $conn->error;
    }
} else {
    header("Location: stafflist.php");
}
?>