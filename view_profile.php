<?php
session_start();
include "database.php";
include "functions.php";

requireLogin();

$viewerId = (int) ($_SESSION['user_id'] ?? 0);
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($userId === 0) {
    $targetUser = null;
} else {
    if ($userId === $viewerId) {
        header("Location: profile.php");
        exit();
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $targetUser = $stmt->fetch();
}

$defaultAvatar = 'images/favicon.ico';
$announcementCount = 0;
$recentAnnouncements = [];
$showVerifiedBadge = false;
$hasCredentials = false;

if ($targetUser) {
    $displayOrganization = $targetUser['display_organization'] ?? '';
    $displayPosition = $targetUser['display_position'] ?? '';

    if (empty($displayOrganization) || empty($displayPosition)) {
        $reqStmt = $pdo->prepare(
            "SELECT organization_name, position
             FROM verification_requests
             WHERE user_id = ? AND status = 'APPROVED'
             ORDER BY reviewed_at DESC, created_at DESC
             LIMIT 1"
        );
        $reqStmt->execute([$targetUser['id']]);
        $verificationData = $reqStmt->fetch();

        if ($verificationData) {
            $displayOrganization = $displayOrganization ?: ($verificationData['organization_name'] ?? '');
            $displayPosition = $displayPosition ?: ($verificationData['position'] ?? '');
        }
    }

    $targetUser['display_organization'] = $displayOrganization;
    $targetUser['display_position'] = $displayPosition;

    $showVerifiedBadge = ($targetUser['status'] ?? '') === 'active' && (($targetUser['verification_status'] ?? '') === 'VERIFIED' || ($targetUser['role'] ?? '') === 'admin');
    $hasCredentials = $showVerifiedBadge && (!empty($displayOrganization) || !empty($displayPosition));

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM announcements WHERE user_id = ?");
    $stmt->execute([$targetUser['id']]);
    $announcementCount = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT id, title, content, image_path, created_at
         FROM announcements
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 5"
    );
    $stmt->execute([$targetUser['id']]);
    $recentAnnouncements = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Public Profile - VERIFY PH</title>
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
    <?php if (!$targetUser): ?>
        <div class="card error-box">
            <p><strong>User not found.</strong></p>
        </div>
    <?php else: ?>
        <div class="profile-shell">
            <section class="profile-hero">
                <img src="<?php echo !empty($targetUser['profile_picture']) ? 'uploads/profile/' . clean($targetUser['profile_picture']) : $defaultAvatar; ?>" alt="<?php echo clean($targetUser['full_name']); ?> profile picture" class="profile-avatar">

                <div class="profile-identity">
                    <h2><?php echo clean($targetUser['full_name']); ?></h2>

                    <div class="profile-badges">
                        <?php if ($targetUser['role'] === 'admin'): ?>
                            <span class="badge badge-verified">Administrator</span>
                        <?php endif; ?>

                        <?php if ($showVerifiedBadge): ?>
                            <span class="badge badge-verified">Verified</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($targetUser['status'] !== 'active'): ?>
                        <p class="inline-note">This account is currently inactive.</p>
                    <?php endif; ?>

                    <p class="profile-bio">
                        <?php echo !empty($targetUser['bio'] ?? '') ? nl2br(clean($targetUser['bio'])) : 'No bio yet'; ?>
                    </p>
                </div>
            </section>

            <section class="profile-grid">
                <div class="card">
                    <h3>Public Information</h3>

                    <?php if ($hasCredentials): ?>
                        <div class="profile-credentials">
                            <?php if (!empty($displayOrganization)): ?>
                                <div class="credential-item">
                                    <span class="credential-label">Organization</span>
                                    <span class="credential-value"><?php echo clean($displayOrganization); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($displayPosition)): ?>
                                <div class="credential-item">
                                    <span class="credential-label">Position</span>
                                    <span class="credential-value"><?php echo clean($displayPosition); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p class="inline-note">No public credentials available yet.</p>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h3>Activity</h3>
                    <p style="font-size:28px; font-weight:800; margin-bottom:6px; color:#101828;">
                        <?php echo $announcementCount; ?>
                    </p>
                    <p class="inline-note">Announcements posted</p>
                </div>
            </section>

            <section class="card">
                <h3>Recent announcements</h3>

                <?php if (count($recentAnnouncements) === 0): ?>
                    <p class="inline-note">This user has not posted any announcements yet.</p>
                <?php else: ?>
                    <div class="profile-feed">
                        <?php foreach ($recentAnnouncements as $announcement): ?>
                            <div class="card" style="margin-bottom:0;">
                                <div class="announcement-header">
                                    <h3><?php echo clean($announcement['title']); ?></h3>
                                </div>
                                <p class="announcement-meta">
                                    <?php echo date("M d, Y g:i A", strtotime($announcement['created_at'])); ?>
                                </p>

                                <?php if (!empty($announcement['image_path'])): ?>
                                    <img src="uploads/posts/<?php echo clean($announcement['image_path']); ?>" class="announcement-image" alt="Announcement image">
                                <?php endif; ?>

                                <p style="margin-top:10px;">
                                    <?php echo nl2br(clean($announcement['content'])); ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    <?php endif; ?>
</main>

<footer>&copy; <?php echo date("Y"); ?> VERIFY PH.</footer>
</body>
</html>
