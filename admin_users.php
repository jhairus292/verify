<?php
session_start();
include "database.php";
include "functions.php";

requireAdmin();

$csrfToken = generateCSRFToken();
$adminId = $_SESSION['user_id'];
$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    verifyCSRFToken($_POST['csrf_token'] ?? '');

    $targetUserId = intval($_POST['user_id']);
    $action = $_POST['action'];

    if ($targetUserId != $adminId) {
        if ($action == 'suspend') {
            $stmt = $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
            $stmt->execute([$targetUserId]);
            $message = "User suspended.";
        } elseif ($action == 'reactivate') {
            $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            $stmt->execute([$targetUserId]);
            $message = "User reactivated.";
        }
    } else {
        $message = "You cannot change your own status.";
    }
}

$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Users - VERIFY PH</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <div class="brand">
        <img src="images/favicon.ico" alt="VERIFY PH logo" class="brand-logo">
        <h1>VERIFY PH</h1>
    </div>
    <nav>
        <a href="admin.php">Admin Dashboard</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<main>
    <h2>Manage Users</h2>

    <?php if (!empty($message)): ?>
        <div class="success-box"><?php echo clean($message); ?></div>
    <?php endif; ?>

    <table>
        <tr>
            <th>Name</th>
            <th>Username</th>
            <th>Role</th>
            <th>Verification</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?php echo clean($u['full_name']); ?></td>
                <td><?php echo clean($u['username']); ?></td>
                <td><?php echo clean(ucfirst($u['role'])); ?></td>
                <td><?php echo clean($u['verification_status']); ?></td>
                <td><?php echo $u['status'] == 'active' ? 'Active' : 'Suspended'; ?></td>
                <td>
                    <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
                        <a href="view_profile.php?user_id=<?php echo (int)$u['id']; ?>" class="btn btn-secondary btn-small">View Profile</a>
                        <?php if ($u['id'] != $adminId): ?>
                            <form method="POST" action="admin_users.php" class="inline-action-form">
                             <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                             <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                             <?php if ($u['status'] == 'active'): ?>
                             <button type="submit" name="action" value="suspend">Suspend</button>
                             <?php else: ?>
                             <button type="submit" name="action" value="reactivate">Reactivate</button>
                              <?php endif; ?>
                            </form>
                        <?php else: ?>
                            <span style="color:#667085; font-size:12px;">(you)</span>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</main>

<footer>&copy; <?php echo date("Y"); ?> VERIFY PH.</footer>
</body>
</html>