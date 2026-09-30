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

    $requestId = intval($_POST['request_id']);
    $action = $_POST['action'];
    $notes = trim($_POST['admin_notes'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM verification_requests WHERE id = ?");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();

    if ($request && $request['status'] == 'PENDING') {

        $newStatus = ($action == 'approve') ? 'APPROVED' : 'REJECTED';

        $stmt = $pdo->prepare(
            "UPDATE verification_requests SET status = ?, admin_notes = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?"
        );
        $stmt->execute([$newStatus, $notes, $adminId, $requestId]);

        $userVerificationStatus = ($action == 'approve') ? 'VERIFIED' : 'REJECTED';

        if ($action == 'approve') {
            $stmt = $pdo->prepare(
                "UPDATE users
                 SET verification_status = ?, display_organization = ?, display_position = ?
                 WHERE id = ?"
            );
            $stmt->execute([
                $userVerificationStatus,
                $request['organization_name'],
                $request['position'],
                $request['user_id']
            ]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET verification_status = ? WHERE id = ?");
            $stmt->execute([$userVerificationStatus, $request['user_id']]);
        }

        // Notify the user of the decision
        $notifMessage = ($action == 'approve')
            ? "Your verification request has been approved. You can now post announcements."
            : "Your verification request was rejected." . ($notes ? " Reason: " . $notes : "");

        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $stmt->execute([$request['user_id'], $notifMessage]);

        $message = "Request " . strtolower($newStatus) . ".";
    }
}

$stmt = $pdo->query(
    "SELECT verification_requests.*, users.full_name, users.username, users.email
     FROM verification_requests
     JOIN users ON verification_requests.user_id = users.id
     ORDER BY
        CASE WHEN verification_requests.status = 'PENDING' THEN 0 ELSE 1 END,
        verification_requests.created_at DESC"
);
$requests = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Verification Requests - VERIFY PH</title>
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
    <h2>Verification Requests</h2>

    <?php if (!empty($message)): ?>
        <div class="success-box"><?php echo clean($message); ?></div>
    <?php endif; ?>

    <?php if (count($requests) == 0): ?>
        <div class="card"><p>No verification requests yet.</p></div>
    <?php else: ?>
        <?php foreach ($requests as $r): ?>
            <div class="card">
                <div class="announcement-header">
                    <h3><?php echo clean($r['full_name']); ?> (<?php echo clean($r['username']); ?>)</h3>
                    <span class="badge <?php echo $r['status'] == 'PENDING' ? 'badge-pending' : ($r['status'] == 'APPROVED' ? 'badge-verified' : 'badge-rejected'); ?>">
                        <?php echo $r['status']; ?>
                    </span>
                </div>

                <p><strong>Email:</strong> <?php echo clean($r['email']); ?></p>
                <p><strong>Organization Type:</strong> <?php echo clean($r['organization_type']); ?></p>
                <p><strong>Organization:</strong> <?php echo clean($r['organization_name']); ?></p>
                <p><strong>Position:</strong> <?php echo clean($r['position']); ?></p>
                <p><strong>ID Type:</strong> <?php echo clean($r['id_type'] ?? 'Not provided'); ?></p>
                <p><strong>Organization Contact:</strong> <?php echo clean($r['org_contact'] ?? 'Not provided'); ?></p>
                <p><strong>Reason:</strong> <?php echo clean($r['reason'] ?? 'Not provided'); ?></p>
                <p><strong>Submitted:</strong> <?php echo date("M d, Y", strtotime($r['created_at'])); ?></p>

                <p style="margin-top:10px;"><strong>ID Photo:</strong></p>
                <img src="uploads/verification/<?php echo clean($r['id_image_path']); ?>" class="id-preview" alt="ID photo">

                <?php if (!empty($r['proof_image_path'])): ?>
                    <p style="margin-top:12px;"><strong>Proof of Affiliation:</strong></p>
                    <img src="uploads/verification_proof/<?php echo clean($r['proof_image_path']); ?>" class="id-preview" alt="Proof of affiliation">
                <?php else: ?>
                    <p class="inline-note">No proof of affiliation provided.</p>
                <?php endif; ?>

                <?php if ($r['status'] == 'PENDING'): ?>
                    <form method="POST" action="admin_verify.php" style="margin-top:15px; padding:0; background:none; box-shadow:none; max-width:none;">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">

                        <label>Admin Notes (optional)</label>
                        <textarea name="admin_notes" style="max-width:500px;"></textarea>

                        <div style="margin-top:10px;">
                            <button type="submit" name="action" value="approve" class="btn btn-success btn-small">Approve</button>
                            <button type="submit" name="action" value="reject" class="btn btn-danger btn-small">Reject</button>
                        </div>
                    </form>
                <?php elseif (!empty($r['admin_notes'])): ?>
                    <p style="margin-top:10px;"><strong>Admin Notes:</strong> <?php echo clean($r['admin_notes']); ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</main>

<footer>&copy; <?php echo date("Y"); ?> VERIFY PH.</footer>
</body>
</html>