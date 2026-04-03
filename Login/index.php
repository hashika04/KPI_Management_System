<?php
session_start();

if (isset($_SESSION['username'])) {
    header("Location: ../Dashboard/dashboard.php");
    exit();
}

$error = $_GET['error'] ?? "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KPI Monitor – Sign In</title>
    <link rel="stylesheet" href="../asset/login.css">
    <!-- Phosphor Icons -->
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css" />
    <!-- Sora font -->
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

    <!-- Decorative background blobs -->
    <div class="bg-blob blob-1"></div>
    <div class="bg-blob blob-2"></div>
    <div class="bg-blob blob-3"></div>

    <div class="login-wrapper">
        <div class="login-card">

            <!-- Logo -->
            <div class="login-logo">
                <i class="ph ph-chart-bar"></i>
            </div>

            <!-- Title -->
            <h1 class="login-title">Sales Assistant KPI<br>Monitoring System</h1>
            <p class="login-subtitle">Supervisor Dashboard</p>

            <!-- Form -->
            <form action="login.php" method="POST" class="login-form">

                <div class="field-group">
                    <label class="field-label">Username</label>
                    <div class="field-wrap">
                        <i class="ph ph-user field-icon"></i>
                        <input
                            type="text"
                            name="username"
                            placeholder="supervisor@store.com"
                            required
                            autocomplete="username"
                        >
                    </div>
                </div>

                <div class="field-group">
                    <label class="field-label">Password</label>
                    <div class="field-wrap">
                        <i class="ph ph-lock field-icon"></i>
                        <input
                            type="password"
                            name="password"
                            placeholder="••••••••"
                            required
                            autocomplete="current-password"
                        >
                        <button type="button" class="toggle-pw" onclick="togglePassword(this)" tabindex="-1">
                            <i class="ph ph-eye"></i>
                        </button>
                    </div>
                </div>

                <?php if ($error): ?>
                <div class="error-msg">
                    <i class="ph ph-warning-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>

                <button type="submit" class="btn-signin">Sign In</button>

            </form>

            <p class="login-footer">Authorized personnel only</p>

        </div>
    </div>

<script>
function togglePassword(btn) {
    const input = btn.closest('.field-wrap').querySelector('input');
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'ph ph-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'ph ph-eye';
    }
}
</script>

</body>
</html>