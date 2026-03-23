<?php
session_start();

if (isset($_SESSION['username'])) {
    header("Location: ../Dashboard/dashboard.php");
    exit();
}

$error = $_GET['error'] ?? "";
?>
<!DOCTYPE html>
<html>
<head>
<title>Login</title>
<link rel="stylesheet" href="../asset/login.css">
</head>
<body>

<div class="login-box">
    <h2>KPI Management System</h2>
    <form action="login.php" method="POST">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">LOG IN</button>
    </form>

    <p style="color: red;"><?php echo $error; ?></p>
</div>
</body>
</html>