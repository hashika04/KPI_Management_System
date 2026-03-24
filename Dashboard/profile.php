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

    $sql = "UPDATE users 
            SET full_name=?, email=?, username=?, department=?, location=?, bio=? 
            WHERE id=?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssi", $full_name, $email, $username, $department, $location, $bio, $id);

    if ($stmt->execute()) {
        $_SESSION['username'] = $username;
        $success = "Profile updated successfully";
    } else {
        $error = "Failed to update profile";
    }
}

$username = $_SESSION['username'];
$sql = "SELECT * FROM users WHERE username='$username'";
$result = $conn->query($sql);
$user = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Supervisor Profile</title>
    <link rel="stylesheet" href="../asset/profile.css">
</head>     
<body>

<div class="profile-wrapper">

    <div class="profile-sidebar">
        <a href="../Dashboard/dashboard.php" class="back-btn">
            <img src="../asset/images/back.jpg" alt="Back">
        </a>

        <div class="profile-card">
            <div class="profile-avatar">
                <img src="../asset/images/supervisor_profile.jpg" alt="Darlene Robertson">
            </div>
            <h3><?php echo $user['full_name']; ?></h3>
            <p>Supervisor</p>
        </div>

        <div class="profile-menu">
            <a href="#" class="active">Profile</a>
            <a href="../Login/logout.php">Logout</a>
        </div>
    </div>

    <div class="profile-main">
        <div class="profile-header">
            <h1>Profile</h1>
            <div class="profile-progress">Supervisor</div>
        </div>

        <?php if (isset($success)) : ?>
            <p style="color: green; margin-bottom: 15px;"><?php echo $success; ?></p>
        <?php endif; ?>

        <?php if (isset($error)) : ?>
            <p style="color: red; margin-bottom: 15px;"><?php echo $error; ?></p>
        <?php endif; ?>

        <form class="profile-form" method="POST">
            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">

            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" value="<?php echo $user['full_name']; ?>">
            </div>

            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" value="<?php echo $user['username']; ?>">
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?php echo $user['email']; ?>">
            </div>

            <div class="form-group">
                <label>Department</label>
                <input type="text" name="department" value="<?php echo $user['department']; ?>">
            </div>

            <div class="form-group">
                <label>Position</label>
                <input type="text" value="<?php echo $user['position']; ?>" readonly>
            </div>

            <div class="form-group">
                <label>Location</label>
                <input type="text" name="location" value="<?php echo $user['location']; ?>">
            </div>

            <div class="form-group full">
                <label>Bio</label>
                <textarea name="bio" placeholder="Write short supervisor profile..."><?php echo $user['bio']; ?></textarea>
            </div>

            <div class="form-group full">
                <button type="submit" class="save-btn">Save Changes</button>
            </div>
        </form>
    </div>

</div>

</body>
</html>