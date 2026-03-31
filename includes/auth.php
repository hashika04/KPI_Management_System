<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: ../Login/index.php");
    exit();
}

include(__DIR__ . "/../config/db.php");

$username = $_SESSION['username'];

$stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();

$result = $stmt->get_result();
$currentUser = $result->fetch_assoc();

$nameParts = explode(" ", $currentUser['full_name']);

$initials = strtoupper(substr($nameParts[0],0,1));

if(isset($nameParts[1])){
    $initials .= strtoupper(substr($nameParts[1],0,1));
}