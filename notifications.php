<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include "database.php";
include "functions.php";

requireLogin();

$userId = $_SESSION['user_id'];

// Fetch all notifications for this user, newest first
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();

// Mark all as read now that the user has viewed this page
$stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
$stmt->execute([$userId]);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Notifications - VERIFY PH</title>
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
    <h2>Notifications</h2>

    <?php if (count($notifications) == 0): ?>
        <div class="card"><p>You have no notifications yet.</p></div>
    <?php else: ?>
        <?php foreach ($notifications as $n): ?>
            <div class="card" style="<?php echo $n['is_read'] ? '' : 'border-left:4px solid #2563eb;'; ?>">
                <p><?php echo clean($n['message']); ?></p>
                <p class="announcement-meta"><?php echo date("M d, Y g:i A", strtotime($n['created_at'])); ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

<footer>&copy; <?php echo date("Y"); ?> VERIFY PH.</footer>
</body>
</html>