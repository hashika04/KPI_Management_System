<?php
/*
 * save_kpi.php
 */

include("../includes/auth.php");
include("../config/db.php");

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$staffId   = intval($_POST['staff_id']   ?? 0);
$staffName = trim($_POST['staff_name']   ?? '');
$year      = intval($_POST['year']       ?? date('Y'));
$templateId = intval($_POST['template_id'] ?? 0);  // ADD THIS LINE
$scores    = $_POST['score']             ?? [];
$svComment = trim($_POST['supervisor_comments']        ?? '');
$trainRec  = trim($_POST['training_recommendations']   ?? '');

if (!$staffId || !$staffName || empty($scores)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

if (!$templateId) {
    echo json_encode(['success' => false, 'message' => 'Template ID is required']);
    exit;
}

/* ── Use today's date as evaluation date ── */
$evalDate = date('Y-m-d');

/* ── Save / update each KPI score ── */
foreach ($scores as $kpiCode => $score) {
    $kpiCode = $conn->real_escape_string($kpiCode);
    $score   = max(1, min(5, intval($score)));

    /* Check if record exists for this staff + kpi_code + date + template_id */
    $checkStmt = $conn->prepare("
        SELECT id FROM kpi_data
        WHERE Name = ? AND KPI_Code = ? AND Date = ? AND template_id = ?
        LIMIT 1
    ");
    $checkStmt->bind_param("sssi", $staffName, $kpiCode, $evalDate, $templateId);
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if ($existing) {
        /* UPDATE existing */
        $updStmt = $conn->prepare("
            UPDATE kpi_data SET Score = ?
            WHERE Name = ? AND KPI_Code = ? AND Date = ? AND template_id = ?
        ");
        $updStmt->bind_param("isssi", $score, $staffName, $kpiCode, $evalDate, $templateId);
        $updStmt->execute();
        $updStmt->close();
    } else {
        /* INSERT new with template_id */
        $insStmt = $conn->prepare("
            INSERT INTO kpi_data (Date, Name, KPI_Code, Score, template_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        $insStmt->bind_param("sssii", $evalDate, $staffName, $kpiCode, $score, $templateId);
        $insStmt->execute();
        $insStmt->close();
    }
}

/* ── Save / update comment (optional: add template_id if needed) ── */
$checkComment = $conn->prepare("
    SELECT id FROM kpi_comment WHERE Name = ? AND Year = ? LIMIT 1
");
$checkComment->bind_param("si", $staffName, $year);
$checkComment->execute();
$existingComment = $checkComment->get_result()->fetch_assoc();
$checkComment->close();

if ($existingComment) {
    $updComment = $conn->prepare("
        UPDATE kpi_comment
        SET `Supervisor Comments` = ?,
            `Training/Development Recommendations` = ?
        WHERE Name = ? AND Year = ?
    ");
    $updComment->bind_param("sssi", $svComment, $trainRec, $staffName, $year);
    $updComment->execute();
    $updComment->close();
} else {
    $insComment = $conn->prepare("
        INSERT INTO kpi_comment (Year, Name, `Supervisor Comments`, `Training/Development Recommendations`)
        VALUES (?, ?, ?, ?)
    ");
    $insComment->bind_param("isss", $year, $staffName, $svComment, $trainRec);
    $insComment->execute();
    $insComment->close();
}

echo json_encode(['success' => true, 'message' => 'KPI scores saved successfully']);