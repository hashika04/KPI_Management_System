<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: ../Login/index.php");
    exit();
}

include("../config/db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $department = trim($_POST['department']);
    $location = trim($_POST['location']);
    $bio = trim($_POST['bio']);

    $sql = "UPDATE users SET full_name=?, email=?, username=?, department=?, location=?, bio=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssi", $full_name, $email, $username, $department, $location, $bio, $id);

    if ($stmt->execute()) {
        $_SESSION['username'] = $username;
        $_SESSION['full_name'] = $full_name; // Sync session with new name
        $success = "Profile updated successfully";
    } else {
        $error = "Failed to update profile";
    }
}

$username = $_SESSION['username'];
$sql = "SELECT * FROM users WHERE username='$username'";
$result = $conn->query($sql);
$user = $result->fetch_assoc();

// Initials for Avatar
$initials = strtoupper(substr($user['full_name'], 0, 1) . substr(explode(' ', $user['full_name'])[1] ?? '', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Supervisor Profile | KPI Monitor</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css" />
    <link rel="stylesheet" href="../asset/universal.css">
    <link rel="stylesheet" href="../asset/profile.css">
</head>     
<body>

<div class="profile-wrapper">
    <div class="profile-sidebar">
        <a href="../Dashboard/overview.php" class="back-btn" title="Back to Dashboard">
            <i class="ph ph-arrow-left"></i>
        </a>

        <div class="profile-card">
            <div class="profile-avatar">
                <img src="../asset/images/supervisor_profile.jpg" alt="<?= htmlspecialchars($user['full_name']) ?>">
            </div>
            <h3><?php echo htmlspecialchars($user['full_name']); ?></h3>
            <p><?php echo htmlspecialchars($user['position']); ?></p>
        </div>

        <div class="profile-menu">
            <a href="#" class="active"><i class="ph ph-user-circle"></i> Profile</a>
            <a href="../Login/logout.php"><i class="ph ph-sign-out"></i> Logout</a>
        </div>
    </div>

    <div class="profile-main">
        <div class="profile-header">
            <h1>Account Settings</h1>
            <div class="status-badge"> <?= htmlspecialchars($user['position']) ?></div>
        </div>

        <?php if (isset($success)) : ?>
            <div class="msg success"><i class="ph ph-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>

        <form class="profile-form" method="POST">
            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">

            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>">
            </div>

            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>">
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>">
            </div>

            <div class="form-group">
                <label>Department</label>
                <input type="text" name="department" value="<?php echo htmlspecialchars($user['department']); ?>">
            </div>

            <div class="form-group">
                <label>Location</label>
                <input type="text" name="location" value="<?php echo htmlspecialchars($user['location']); ?>">
            </div>

            <div class="form-group">
                <label>Position (Read-only)</label>
                <input type="text" value="<?php echo htmlspecialchars($user['position']); ?>" readonly class="readonly-input">
            </div>

            <div class="form-group full">
                <label>Bio</label>
                <textarea name="bio"><?php echo htmlspecialchars($user['bio']); ?></textarea>
            </div>

            <div class="form-group full">
                <button type="submit" class="save-btn">Update Profile</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>