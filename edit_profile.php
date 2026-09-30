<?php
session_start();
include "database.php";
include "functions.php";

requireLogin();

$userId = $_SESSION['user_id'];
$csrfToken = generateCSRFToken();
$errors = [];

$bioColumnExists = (bool) $pdo->query("SHOW COLUMNS FROM users LIKE 'bio'")->fetch();
$profilePictureColumnExists = (bool) $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_picture'")->fetch();

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: profile.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRFToken($_POST['csrf_token'] ?? '');

    $fullName = trim($_POST['full_name'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $profilePicture = $user['profile_picture'] ?? null;

    if (empty($fullName)) {
        $errors[] = "Full name is required.";
    }

    if (!empty($_FILES['profile_picture']['name'])) {
        $uploadedPicture = uploadImage('profile_picture', 'uploads/profile');

        if ($uploadedPicture === false) {
            $errors[] = "Profile picture must be a JPG or PNG under 5MB.";
        } elseif ($uploadedPicture === null) {
            $errors[] = "Profile picture upload failed. Please try again.";
        } else {
            $profilePicture = $uploadedPicture;
        }
    }

    if (empty($errors)) {
        if ($bioColumnExists && $profilePictureColumnExists) {
            $stmt = $pdo->prepare(
                "UPDATE users
                 SET full_name = ?, bio = ?, profile_picture = ?
                 WHERE id = ?"
            );
            $stmt->execute([$fullName, $bio, $profilePicture, $userId]);
        } elseif ($bioColumnExists) {
            $stmt = $pdo->prepare(
                "UPDATE users
                 SET full_name = ?, bio = ?
                 WHERE id = ?"
            );
            $stmt->execute([$fullName, $bio, $userId]);
        } elseif ($profilePictureColumnExists) {
            $stmt = $pdo->prepare(
                "UPDATE users
                 SET full_name = ?, profile_picture = ?
                 WHERE id = ?"
            );
            $stmt->execute([$fullName, $profilePicture, $userId]);
        } else {
            $stmt = $pdo->prepare(
                "UPDATE users
                 SET full_name = ?
                 WHERE id = ?"
            );
            $stmt->execute([$fullName, $userId]);
        }

        $_SESSION['full_name'] = $fullName;

        header("Location: profile.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Profile - VERIFY PH</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <div class="brand">
        <img src="images/favicon.ico" alt="VERIFY PH logo" class="brand-logo">
        <h1>VERIFY PH</h1>
    </div>
    <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="notifications.php">Notifications</a>
        <a href="profile.php">Profile</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<main>
    <h2>Edit Profile</h2>

    <form method="POST" action="edit_profile.php" class="form-wide" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

        <?php if (!empty($errors)): ?>
            <div class="error-box">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo clean($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <label>Full Name</label>
        <input type="text" name="full_name" value="<?php echo clean($user['full_name']); ?>" required>

        <label>Username</label>
        <input type="text" value="<?php echo clean($user['username']); ?>" disabled>

        <label>Profile Picture</label>
        <div class="upload-btn-wrapper">
            <label for="profile_picture" id="profile_upload_label" class="upload-btn">Upload</label>
            <input id="profile_picture" type="file" name="profile_picture" accept="image/jpeg,image/png,image/jpg">
        </div>
        <p style="font-size:12px; color:#6b7280; margin-top:4px;">Optional. JPG or PNG, max 5MB.</p>

        <label>Bio</label>
        <textarea name="bio" placeholder="Tell people a little about yourself"><?php echo clean($user['bio'] ?? ''); ?></textarea>

        <button type="submit" class="btn" style="margin-top:15px; width:100%;">Save Profile</button>
    </form>
</main>

<footer>&copy; <?php echo date("Y"); ?> VERIFY PH.</footer>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const fileInput = document.getElementById('profile_picture');
        const uploadLabel = document.getElementById('profile_upload_label');

        if (fileInput && uploadLabel) {
            fileInput.addEventListener('change', function () {
                const file = this.files && this.files[0];
                uploadLabel.textContent = file ? file.name : 'Upload';
            });
        }
    });
</script>
</body>
</html>
