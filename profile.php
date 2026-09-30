<?php
session_start();
include "database.php";
include "functions.php";

requireLogin();

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if ($user) {
    if (empty($user['display_organization']) || empty($user['display_position'])) {
        $reqStmt = $pdo->prepare(
            "SELECT organization_name, position
             FROM verification_requests
             WHERE user_id = ? AND status = 'APPROVED'
             ORDER BY reviewed_at DESC, created_at DESC
             LIMIT 1"
        );
        $reqStmt->execute([$userId]);
        $verificationData = $reqStmt->fetch();

        if ($verificationData) {
            $user['display_organization'] = $user['display_organization'] ?: $verificationData['organization_name'];
            $user['display_position'] = $user['display_position'] ?: $verificationData['position'];
        }
    }
}

$statusClass = [
    'VERIFIED' => 'badge-verified',
    'PENDING' => 'badge-pending',
    'UNVERIFIED' => 'badge-unverified',
    'REJECTED' => 'badge-rejected'
];

$defaultAvatar = 'images/favicon.ico';
$profileImage = !empty($user['profile_picture']) ? 'uploads/profile/' . clean($user['profile_picture']) : $defaultAvatar;
$displayRole = ucfirst($user['role']);
$verificationLabel = $user['verification_status'] ?? 'UNVERIFIED';
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Profile - VERIFY PH</title>
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
    <h2>My Profile</h2>

    <div class="profile-shell">
        <section class="profile-hero">
            <img src="<?php echo $profileImage; ?>" alt="Profile picture" class="profile-avatar">

            <div class="profile-identity">
                <div class="profile-header-row">
                    <h2><?php echo clean($user['full_name']); ?></h2>
                    <a href="edit_profile.php" class="profile-edit-btn">Edit Profile</a>
                </div>

                <div class="profile-badges">
                    <?php if ($user['role'] === 'admin'): ?>
                        <span class="badge badge-verified">Administrator</span>
                    <?php endif; ?>
                    <span class="badge <?php echo $statusClass[$user['verification_status']]; ?>"><?php echo clean($verificationLabel); ?></span>
                </div>

                <p class="profile-bio">
                    <?php echo !empty($user['bio'] ?? '') ? nl2br(clean($user['bio'])) : 'No bio yet'; ?>
                </p>
            </div>
        </section>

        <section class="profile-grid">
            <div class="card">
                <h3>Account Details</h3>
                <div class="profile-credentials">
                    <div class="credential-item">
                        <span class="credential-label">Full Name</span>
                        <span class="credential-value"><?php echo clean($user['full_name']); ?></span>
                    </div>
                    <div class="credential-item">
                        <span class="credential-label">Username</span>
                        <span class="credential-value"><?php echo clean($user['username']); ?></span>
                    </div>
                    <div class="credential-item">
                        <span class="credential-label">Email</span>
                        <span class="credential-value"><?php echo clean($user['email']); ?></span>
                    </div>
                    <div class="credential-item">
                        <span class="credential-label">Role</span>
                        <span class="credential-value"><?php echo clean($displayRole); ?></span>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3>Verification</h3>
                <?php if ($user['verification_status'] == 'VERIFIED' || $user['role'] == 'admin'): ?>
                    <div class="profile-credentials">
                        <?php if (!empty($user['display_organization'])): ?>
                            <div class="credential-item">
                                <span class="credential-label">Organization</span>
                                <span class="credential-value"><?php echo clean($user['display_organization']); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($user['display_position'])): ?>
                            <div class="credential-item">
                                <span class="credential-label">Position</span>
                                <span class="credential-value"><?php echo clean($user['display_position']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p class="inline-note">Your public credentials will appear here once verified.</p>
                <?php endif; ?>

                <?php if ($user['verification_status'] != 'VERIFIED' && $user['role'] != 'admin'): ?>
                    <a href="verify_request.php" class="btn" style="margin-top:16px; width:100%; text-align:center;">
                        <?php echo $user['verification_status'] == 'PENDING' ? 'View Status' : 'Get Verified'; ?>
                    </a>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>

<footer>&copy; <?php echo date("Y"); ?> VERIFY PH.</footer>
</body>
</html>