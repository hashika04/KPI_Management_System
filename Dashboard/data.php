<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include("../config/db.php");

// --- 1. User Info Fetching ---
if (isset($_SESSION['username'])) {
    $session_username = $_SESSION['username'];
    $sqlUser = "SELECT full_name, email, position FROM users WHERE username = ?";
    $stmt = $conn->prepare($sqlUser);
    $stmt->bind_param("s", $session_username);
    $stmt->execute();
    $resUser = $stmt->get_result();
    if ($rowUser = $resUser->fetch_assoc()) {
        $_SESSION['full_name'] = $rowUser['full_name'];
        $_SESSION['email']     = $rowUser['email'];
        $_SESSION['position']  = $rowUser['position'];
    }
    $stmt->close();
}

// --- 2. Yearly Chart Logic ---
$sqlYearlyKpi = "SELECT 
                    YEAR(STR_TO_DATE(Date, '%m/%d/%Y')) AS year_num,
                    ROUND((AVG(Score) / 5) * 100, 2) AS avg_kpi_percent
                FROM kpi_data
                WHERE YEAR(STR_TO_DATE(Date, '%m/%d/%Y')) IN (2022, 2023, 2024, 2025)
                GROUP BY year_num ORDER BY year_num";
$resultYearlyKpi = $conn->query($sqlYearlyKpi);
$yearlyKpiData = [];
while ($row = $resultYearlyKpi->fetch_assoc()) {
    $yearlyKpiData[$row['year_num']] = (float)$row['avg_kpi_percent'];
}

$kpiYears = ['2022', '2023', '2024', '2025'];
$kpiYearPercentages = [
    $yearlyKpiData[2022] ?? 0,
    $yearlyKpiData[2023] ?? 0,
    $yearlyKpiData[2024] ?? 0,
    $yearlyKpiData[2025] ?? 0
];

// --- 3. Performance & Podium Logic (New) ---
$sqlStaffKpi = "SELECT s.id, s.full_name, s.profile_photo, 
                ROUND((AVG(k.Score) / 5) * 100, 1) as currentScore
                FROM staff s
                JOIN kpi_data k ON s.id = k.id 
                GROUP BY s.id";
$resultStaff = $conn->query($sqlStaffKpi);
$allStaff = [];
while($row = $resultStaff->fetch_assoc()) {
    $row['performanceLevel'] = ($row['currentScore'] >= 85) ? 'top' : (($row['currentScore'] < 50) ? 'critical' : 'good');
    $allStaff[] = $row;
}

$totalStaffCount = count($allStaff);
$topCount = count(array_filter($allStaff, fn($s) => $s['performanceLevel'] === 'top'));
$riskCount = count(array_filter($allStaff, fn($s) => $s['performanceLevel'] === 'critical'));

usort($allStaff, fn($a, $b) => $b['currentScore'] <=> $a['currentScore']);
$top3 = array_slice($allStaff, 0, 3);
$podiumOrder = [$top3[1] ?? null, $top3[0] ?? null, $top3[2] ?? null];
?>