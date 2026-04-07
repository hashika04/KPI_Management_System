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
    
    // Insert KPI data
    $sql = "INSERT INTO kpi_data (Date, Name, KPI_Code, Score) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssd", $date, $staffName, $kpiCode, $score);
    
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
