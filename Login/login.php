<?php
session_start();
include("../config/db.php");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit();
}

$username = trim($_POST['username']);
$password = trim($_POST['password']);

$sql = "SELECT * FROM users WHERE username='$username'";
$result = $conn->query($sql);

if ($result->num_rows == 1) {
    $user = $result->fetch_assoc();

    if ($password == $user['password']) {
        $_SESSION['username'] = $user['username'];
        header("Location: ../Dashboard/overview.php");
        exit();
    } else {
        header("Location: index.php?error=Incorrect password");
        exit();
    }
} else {
    header("Location: index.php?error=Username not found");
    exit();
}
?>