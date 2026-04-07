<?php
// staff_masterlist/get_staff_kpis.php
include("../config/db.php");

if (isset($_GET['staff_name']) && isset($_GET['date'])) {
    $staff_name = $_GET['staff_name'];
    $date = $_GET['date'];
    $year = date('Y', strtotime($date));
    
    // Get active template for the year
    $template_query = "SELECT id, section1_weight, section2_weight FROM kpi_templates WHERE year = ? AND status = 'active'";
    $template_stmt = $conn->prepare($template_query);
    $template_stmt->bind_param("i", $year);
    $template_stmt->execute();
    $template = $template_stmt->get_result()->fetch_assoc();
    
    if ($template) {
        // Get all KPI items from the active template
        $items_query = "SELECT * FROM kpi_template_items WHERE template_id = ? ORDER BY section, display_order";
        $items_stmt = $conn->prepare($items_query);
        $items_stmt->bind_param("i", $template['id']);
        $items_stmt->execute();
        $items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Get existing scores for this staff and year
        $scores_query = "SELECT KPI_Code, Score FROM kpi_data WHERE Name = ? AND YEAR(Date) = ? AND template_id = ?";
        $scores_stmt = $conn->prepare($scores_query);
        $scores_stmt->bind_param("sii", $staff_name, $year, $template['id']);
        $scores_stmt->execute();
        $scores_result = $scores_stmt->get_result();
        
        $existing_scores = [];
        while($score = $scores_result->fetch_assoc()) {
            $existing_scores[$score['KPI_Code']] = $score['Score'];
        }
        
        echo json_encode([
            'success' => true,
            'template' => $template,
            'items' => $items,
            'scores' => $existing_scores,
            'year' => $year
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => "No active template found for year $year. Please create and activate a template for $year."
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
}
?>