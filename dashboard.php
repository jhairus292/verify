<?php
session_start();
include "database.php";
include "functions.php";

requireLogin();

$userId = $_SESSION['user_id'];
$csrfToken = generateCSRFToken();
$searchQuery = trim($_GET['q'] ?? '');

// Handle reaction toggle
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

    header("Location: dashboard.php");
    exit();
}

// Get announcements, filtered by search query if one was typed
if ($searchQuery !== '') {
    $likeTerm = '%' . $searchQuery . '%';
    $stmt = $pdo->prepare(
        "SELECT announcements.*, users.id AS author_id, users.full_name AS author_name,
            (SELECT COUNT(*) FROM reactions WHERE reactions.announcement_id = announcements.id) AS reaction_count,
            (SELECT COUNT(*) FROM reactions WHERE reactions.announcement_id = announcements.id AND reactions.user_id = ?) AS user_reacted
         FROM announcements
         JOIN users ON announcements.user_id = users.id
         WHERE announcements.title LIKE ? OR announcements.content LIKE ?
         ORDER BY announcements.created_at DESC"
    );
    $stmt->execute([$userId, $likeTerm, $likeTerm]);
} else {
    $stmt = $pdo->prepare(
        "SELECT announcements.*, users.id AS author_id, users.full_name AS author_name,
            (SELECT COUNT(*) FROM reactions WHERE reactions.announcement_id = announcements.id) AS reaction_count,
            (SELECT COUNT(*) FROM reactions WHERE reactions.announcement_id = announcements.id AND reactions.user_id = ?) AS user_reacted
         FROM announcements
         JOIN users ON announcements.user_id = users.id
         ORDER BY announcements.created_at DESC"
    );
    $stmt->execute([$userId]);
}
$announcements = $stmt->fetchAll();

$verificationStatus = $_SESSION['verification_status'];
$isAdmin = $_SESSION['role'] == 'admin';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - VERIFY PH</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <div class="brand">
        <img src="images/favicon.ico" alt="VERIFY PH logo" class="brand-logo">
        <h1>VERIFY PH</h1>
    </div>

    <form id="searchForm" method="GET" action="dashboard.php">
        <button type="submit" aria-label="Search">🔍</button>
        <input type="text" name="q" value="<?php echo clean($searchQuery); ?>" placeholder="Search announcements...">
    </form>

    <nav>
        <?php if ($isAdmin): ?>
            <a href="admin.php" style="background:rgba(255,255,255,0.1); font-weight:700;">⚙ Admin Dashboard</a>
        <?php endif; ?>
        <a href="dashboard.php">Feed</a>
        <a href="notifications.php">Notifications</a>
        <a href="profile.php">Profile</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<main>

    <?php if ($isAdmin): ?>
        <div class="card" style="border-left:4px solid #2563eb; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <p style="margin:0; color:#101828; font-weight:600;">
                You're viewing the public feed as an <span class="badge" style="background:#eff4ff; color:#2563eb;">ADMIN</span>
            </p>
            <a href="admin.php" class="btn btn-secondary btn-small">Go to Admin Dashboard</a>
        </div>
    <?php endif; ?>

    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; margin-bottom:20px;">
        <h2 style="margin-bottom:0;">
            <?php echo $searchQuery !== '' ? 'Search Results' : 'Announcements'; ?>
        </h2>

        <?php if ($isAdmin): ?>
            <a href="post.php" class="btn">+ New Announcement</a>
        <?php elseif ($verificationStatus == 'VERIFIED'): ?>
            <a href="post.php" class="btn">+ New Announcement</a>
        <?php elseif ($verificationStatus == 'PENDING'): ?>
            <span class="badge badge-pending">Verification Pending</span>
        <?php elseif ($verificationStatus == 'REJECTED'): ?>
            <a href="verify_request.php" class="btn btn-danger">Verification Rejected — Reapply</a>
        <?php else: ?>
            <a href="verify_request.php" class="btn btn-success">Get Verified to Post</a>
        <?php endif; ?>
    </div>

    <?php if ($searchQuery !== ''): ?>
        <p style="margin-bottom:14px; color:#667085;">
            <?php echo count($announcements); ?> result(s) for "<?php echo clean($searchQuery); ?>" &middot;
            <a href="dashboard.php">Clear search</a>
        </p>
    <?php endif; ?>

    <?php if (count($announcements) == 0): ?>
        <div class="card">
            <p><?php echo $searchQuery !== '' ? 'No announcements match your search.' : 'No announcements have been posted yet.'; ?></p>
        </div>
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

                <form method="POST" action="dashboard.php" style="margin:0; padding:0; background:none; box-shadow:none; max-width:none;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="announcement_id" value="<?php echo $a['id']; ?>">
                    <button type="submit" name="toggle_reaction" value="1" class="react-btn <?php echo $a['user_reacted'] ? 'reacted' : ''; ?>">
                        <?php echo $a['user_reacted'] ? '❤️ Liked' : '🤍 Like'; ?> (<?php echo $a['reaction_count']; ?>)
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</main>

<footer>&copy; <?php echo date("Y"); ?> VERIFY PH.</footer>
</body>
</html>