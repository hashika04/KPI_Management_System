<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include("../config/db.php");

// Fetch User Info based on the 'users' table structure
if (isset($_SESSION['username'])) {
    $session_username = $_SESSION['username'];
    
    // Select the specific columns visible in your database screenshot
    $sqlUser = "SELECT full_name, email, position FROM users WHERE username = ?";
    $stmt = $conn->prepare($sqlUser);
    $stmt->bind_param("s", $session_username);
    $stmt->execute();
    $resUser = $stmt->get_result();

    if ($rowUser = $resUser->fetch_assoc()) {
        // Store the correct database values into the session
        $_SESSION['full_name'] = $rowUser['full_name'];
        $_SESSION['email']     = $rowUser['email'];
        $_SESSION['position']  = $rowUser['position'];
    }
    $stmt->close();
}

$sqlYearlyKpi = "SELECT 
                    YEAR(STR_TO_DATE(Date, '%m/%d/%Y')) AS year_num,
                    ROUND(AVG(Score), 2) AS avg_kpi_score,
                    ROUND((AVG(Score) / 5) * 100, 2) AS avg_kpi_percent,
                    COUNT(*) AS total_kpi_records,
                    SUM(Score) AS total_kpi_score
                FROM kpi_data
                WHERE YEAR(STR_TO_DATE(Date, '%m/%d/%Y')) IN (2022, 2023, 2024, 2025)
                GROUP BY YEAR(STR_TO_DATE(Date, '%m/%d/%Y'))
                ORDER BY year_num";

$resultYearlyKpi = $conn->query($sqlYearlyKpi);

$yearlyKpiData = [];

while ($row = $resultYearlyKpi->fetch_assoc()) {
    $year = $row['year_num'];

    $yearlyKpiData[$year] = [
        'avg_score' => (float)$row['avg_kpi_score'],
        'avg_percent' => (float)$row['avg_kpi_percent'],
        'total_records' => (int)$row['total_kpi_records'],
        'total_score' => (int)$row['total_kpi_score']
    ];
}

$avg2025 = $yearlyKpiData[2025]['avg_percent'] ?? 0;
$avg2024 = $yearlyKpiData[2024]['avg_percent'] ?? 0;
$avg2023 = $yearlyKpiData[2023]['avg_percent'] ?? 0;
$avg2022 = $yearlyKpiData[2022]['avg_percent'] ?? 0;

$kpiYears = ['2022', '2023', '2024', '2025'];
$kpiYearPercentages = [
    $avg2022,
    $avg2023,
    $avg2024,
    $avg2025
];
?>