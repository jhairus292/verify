<?php
session_start();
include "database.php";
include "functions.php";

requireAdmin();

$userId = $_SESSION['user_id'];
$csrfToken = generateCSRFToken();

// Handle reaction toggle (so liking still works from the Feed view on this page)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_reaction'])) {

    verifyCSRFToken($_POST['csrf_token'] ?? '');

    $announcementId = intval($_POST['announcement_id']);

    $stmt = $pdo->prepare("SELECT id FROM reactions WHERE announcement_id = ? AND user_id = ?");
    $stmt->execute([$announcementId, $userId]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $pdo->prepare("DELETE FROM reactions WHERE id = ?");
        $stmt->execute([$existing['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO reactions (announcement_id, user_id) VALUES (?, ?)");
        $stmt->execute([$announcementId, $userId]);
    }

    header("Location: admin.php#feed");
    exit();
}

// Stats
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'");
$totalUsers = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE verification_status = 'VERIFIED'");
$verifiedUsers = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM verification_requests WHERE status = 'PENDING'");
$pendingRequests = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM announcements");
$totalAnnouncements = $stmt->fetchColumn();

// Announcements for the Feed view
$stmt = $pdo->prepare(
    "SELECT announcements.*, users.id AS author_id, users.full_name AS author_name,
        (SELECT COUNT(*) FROM reactions WHERE reactions.announcement_id = announcements.id) AS reaction_count,
        (SELECT COUNT(*) FROM reactions WHERE reactions.announcement_id = announcements.id AND reactions.user_id = ?) AS user_reacted
     FROM announcements
     JOIN users ON announcements.user_id = users.id
     ORDER BY announcements.created_at DESC"
);
$stmt->execute([$userId]);
$announcements = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - VERIFY PH</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }
    </style>
</head>
<body>

<header>
    <div class="brand">
        <img src="images/favicon.ico" alt="VERIFY PH logo" class="brand-logo">
        <h1>VERIFY PH</h1>
    </div>

    <nav>
        <button type="button" id="feedToggleBtn" onclick="toggleFeed()" style="all:unset; cursor:pointer; color:#d0d5dd; margin-left:4px; padding:8px 14px; border-radius:6px; font-weight:500; font-size:14px;">View Feed</button>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<main>

    <!-- ADMIN DASHBOARD VIEW -->
    <div id="panelAdmin" class="tab-panel active">
        <h2>Admin Dashboard</h2>

        <div class="card-grid">
            <div class="stat-card"><h3><?php echo $totalUsers; ?></h3><p>Total Users</p></div>
            <div class="stat-card"><h3><?php echo $verifiedUsers; ?></h3><p>Verified Users</p></div>
            <div class="stat-card"><h3><?php echo $pendingRequests; ?></h3><p>Pending Requests</p></div>
            <div class="stat-card"><h3><?php echo $totalAnnouncements; ?></h3><p>Announcements</p></div>
        </div>

        <div style="margin:20px 0;">
            <a href="admin_verify.php" class="btn">Review Verification Requests</a>
            <a href="admin_users.php" class="btn btn-secondary">Manage Users</a>
        </div>
    </div>

    <!-- FEED VIEW -->
    <div id="panelFeed" class="tab-panel">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; margin-bottom:20px;">
            <h2 style="margin-bottom:0;">Announcements</h2>
            <a href="post.php" class="btn">+ New Announcement</a>
        </div>

        <?php if (count($announcements) == 0): ?>
            <div class="card"><p>No announcements have been posted yet.</p></div>
        <?php else: ?>
            <?php foreach ($announcements as $a): ?>
                <div class="card">
                    <div class="announcement-header">
                        <h3><?php echo clean($a['title']); ?></h3>
                        <span class="badge badge-verified">Verified Poster</span>
                    </div>

                    <p class="announcement-meta">
                        By <a href="view_profile.php?user_id=<?php echo (int)$a['author_id']; ?>"><?php echo clean($a['author_name']); ?></a> &middot;
                        <?php echo date("M d, Y g:i A", strtotime($a['created_at'])); ?>
                    </p>

                    <?php if (!empty($a['image_path'])): ?>
                        <img src="uploads/posts/<?php echo clean($a['image_path']); ?>" class="announcement-image" alt="Announcement image">
                    <?php endif; ?>

                    <p style="margin-top:10px;"><?php echo nl2br(clean($a['content'])); ?></p>

                    <form method="POST" action="admin.php" style="margin:0; padding:0; background:none; box-shadow:none; max-width:none;">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="announcement_id" value="<?php echo $a['id']; ?>">
                        <button type="submit" name="toggle_reaction" value="1" class="react-btn <?php echo $a['user_reacted'] ? 'reacted' : ''; ?>">
                            <?php echo $a['user_reacted'] ? '❤️ Liked' : '🤍 Like'; ?> (<?php echo $a['reaction_count']; ?>)
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</main>

<footer>&copy; <?php echo date("Y"); ?> VERIFY PH.</footer>

<script>
    function toggleFeed() {
        const adminPanel = document.getElementById('panelAdmin');
        const feedPanel = document.getElementById('panelFeed');
        const btn = document.getElementById('feedToggleBtn');

        const showingFeed = feedPanel.classList.contains('active');

        adminPanel.classList.toggle('active', showingFeed);
        feedPanel.classList.toggle('active', !showingFeed);
        btn.textContent = showingFeed ? 'View Feed' : 'Back to Dashboard';
    }

    // If a reaction was just submitted, land back on the Feed view instead of Admin
    if (window.location.hash === '#feed') {
        toggleFeed();
    }
</script>

</body>
</html>