<?php
session_start();
include "database.php";
include "functions.php";

requireLogin();

$userId = $_SESSION['user_id'];
$csrfToken = generateCSRFToken();
$errors = [];
$success = false;

// Check for an existing pending or approved request
$stmt = $pdo->prepare(
    "SELECT * FROM verification_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 1"
);
$stmt->execute([$userId]);
$existingRequest = $stmt->fetch();

$canApply = (!$existingRequest || $existingRequest['status'] == 'REJECTED')
    && $_SESSION['verification_status'] != 'VERIFIED';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $canApply) {

    verifyCSRFToken($_POST['csrf_token'] ?? '');

    $organizationType = trim($_POST['organization_type'] ?? '');
    $organizationName = trim($_POST['organization_name'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $idType = trim($_POST['id_type'] ?? '');
    $orgContact = trim($_POST['org_contact'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $proofImagePath = null;

    $validTypes = ['School Personnel', 'Government', 'Company', 'Other'];
    $validIdTypes = [
        'Government ID',
        'School ID / Employee ID',
        'Company ID',
        'Professional License (e.g. PRC)',
        'Other'
    ];

    if (!in_array($organizationType, $validTypes, true)) {
        $errors[] = "Please select a valid organization type.";
    }

    if (empty($organizationName) || empty($position)) {
        $errors[] = "Organization name and position are required.";
    }

    if (empty($idType) || !in_array($idType, $validIdTypes, true)) {
        $errors[] = "Please select your ID type.";
    }

    if (empty($orgContact)) {
        $errors[] = "Organization contact is required.";
    }

    if (empty($reason)) {
        $errors[] = "Please tell us why you need verification.";
    } elseif (strlen($reason) > 300) {
        $errors[] = "Reason must be 300 characters or fewer.";
    }

    if (!isset($_FILES['id_image']) || $_FILES['id_image']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Please upload a photo of your ID with your face visible.";
    }

    if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['proof_image']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Proof of affiliation upload failed. Please try again.";
        }
    }

    if (empty($errors)) {
        $uploadedFileName = uploadImage('id_image', 'uploads/verification');

        if ($uploadedFileName === false) {
            $errors[] = "ID image must be JPG or PNG and under 5MB.";
        } elseif ($uploadedFileName === null) {
            $errors[] = "ID image upload failed. Please try again.";
        } else {
            if (!empty($_FILES['proof_image']['name'])) {
                $proofImage = uploadImage('proof_image', 'uploads/verification_proof');
                if ($proofImage === false) {
                    $errors[] = "Proof of affiliation must be JPG or PNG and under 5MB.";
                } elseif ($proofImage === null) {
                    $errors[] = "Proof of affiliation upload failed. Please try again.";
                } else {
                    $proofImagePath = $proofImage;
                }
            }

            if (empty($errors)) {
                $stmt = $pdo->prepare(
                    "INSERT INTO verification_requests
                     (user_id, organization_type, organization_name, position, id_type, org_contact, id_image_path, proof_image_path, reason, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING')"
                );
                $stmt->execute([
                    $userId,
                    $organizationType,
                    $organizationName,
                    $position,
                    $idType,
                    $orgContact,
                    $uploadedFileName,
                    $proofImagePath,
                    $reason
                ]);

                $stmt = $pdo->prepare("UPDATE users SET verification_status = 'PENDING' WHERE id = ?");
                $stmt->execute([$userId]);

                $_SESSION['verification_status'] = 'PENDING';

                $success = true;

                $stmt = $pdo->prepare(
                    "SELECT * FROM verification_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 1"
                );
                $stmt->execute([$userId]);
                $existingRequest = $stmt->fetch();
                $canApply = false;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Get Verified - VERIFY PH</title>
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

    <h2 style="text-align:center;">Verify Your Identity</h2>
    <p style="text-align:center; margin-bottom:20px;">
        Verification lets you post announcements. Upload a clear photo of a valid ID with your face visible.
    </p>

    <?php if ($_SESSION['verification_status'] == 'VERIFIED'): ?>
        <div class="success-box" style="max-width:600px; margin:0 auto; text-align:center;">
            You are already verified.
        </div>

    <?php elseif ($existingRequest && $existingRequest['status'] == 'PENDING'): ?>
        <div class="card" style="max-width:600px; margin:0 auto;">
            <p><strong>Status:</strong> <span class="badge badge-pending">Pending Review</span></p>
            <p style="margin-top:8px;">Your verification request is being reviewed by an administrator.</p>
        </div>

    <?php else: ?>

        <?php if ($success): ?>
            <div class="success-box" style="max-width:600px; margin:0 auto 20px auto; text-align:center;">
                Verification request submitted. Please wait for admin review.<br>
                Once approved, you'll also need to complete your profile (photo and bio) before you can post — we'll notify you.
            </div>
        <?php endif; ?>

        <?php if ($existingRequest && $existingRequest['status'] == 'REJECTED'): ?>
            <div class="error-box" style="max-width:600px; margin:0 auto 20px auto;">
                Your previous request was rejected<?php echo $existingRequest['admin_notes'] ? ': ' . clean($existingRequest['admin_notes']) : '.'; ?>
                You may submit a new request below.
            </div>
        <?php endif; ?>

        <form method="POST" action="verify_request.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

            <?php if (!empty($errors)): ?>
                <div class="error-box">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo clean($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <label>Organization Type</label>
            <select name="organization_type" required>
                <option value="">-- Select --</option>
                <option value="School Personnel">School Personnel</option>
                <option value="Government">Government</option>
                <option value="Company">Company</option>
                <option value="Other">Other</option>
            </select>

            <label>Organization / School Name</label>
            <input type="text" name="organization_name" required>

            <label>Position / Role</label>
            <input type="text" name="position" required>

            <label>ID Type</label>
            <select name="id_type" required>
                <option value="">-- Select --</option>
                <option value="Government ID">Government ID</option>
                <option value="School ID / Employee ID">School ID / Employee ID</option>
                <option value="Company ID">Company ID</option>
                <option value="Professional License (e.g. PRC)">Professional License (e.g. PRC)</option>
                <option value="Other">Other</option>
            </select>

            <label>Organization Contact (email domain, phone, or website)</label>
            <input type="text" name="org_contact" required>
            <p class="helper-text">This helps us confirm you're affiliated with a real organization.</p>

            <label>Upload ID (with your face visible)</label>
            <div class="file-upload-block">
                <div class="file-upload-row">
                    <input type="file" id="id_image" name="id_image" accept="image/jpeg,image/png" required>
                    <span class="file-name" id="id_image_name">No file chosen</span>
                </div>
                <img id="id_image_preview" class="image-preview hidden" alt="ID preview">
                <div id="id_image_error" class="field-error hidden"></div>
            </div>
            <p style="font-size:12px; color:#6b7280; margin-top:4px;">JPG or PNG, max 5MB.</p>

            <label>Proof of Affiliation (optional but recommended)</label>
            <div class="file-upload-block">
                <div class="file-upload-row">
                    <input type="file" id="proof_image" name="proof_image" accept="image/jpeg,image/png">
                    <span class="file-name" id="proof_image_name">No file chosen</span>
                </div>
                <img id="proof_image_preview" class="image-preview hidden" alt="Proof preview">
                <div id="proof_image_error" class="field-error hidden"></div>
            </div>
            <p class="helper-text">For example, an employee ID, staff directory listing, or official email header showing your name and organization.</p>

            <label>Reason for Verification</label>
            <textarea name="reason" maxlength="300" placeholder="In a sentence or two, tell us why you need to post announcements." required></textarea>

            <p class="muted-note">Your ID and documents are only visible to administrators and are used solely to verify your identity and affiliation.</p>

            <button type="submit" class="btn" style="margin-top:15px; width:100%;">Submit for Verification</button>
            <p class="expectation-note">Verification requests are usually reviewed within 1–2 business days.</p>
        </form>

    <?php endif; ?>

</main>

<footer>&copy; <?php echo date("Y"); ?> VERIFY PH.</footer>

<script>
    function setupImagePreview(inputId, previewId, nameId, errorId) {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        const nameTag = document.getElementById(nameId);
        const errorTag = document.getElementById(errorId);

        if (!input || !preview || !nameTag || !errorTag) {
            return;
        }

        input.addEventListener('change', function () {
            const file = this.files && this.files[0];
            preview.classList.add('hidden');
            preview.src = '';
            errorTag.classList.add('hidden');
            errorTag.textContent = '';

            if (!file) {
                nameTag.textContent = 'No file chosen';
                return;
            }

            nameTag.textContent = file.name;

            const isValidType = ['image/jpeg', 'image/png'].includes(file.type);
            const isValidSize = file.size <= 5 * 1024 * 1024;

            if (!isValidType || !isValidSize) {
                errorTag.textContent = 'Please choose a JPG or PNG image under 5MB.';
                errorTag.classList.remove('hidden');
                return;
            }

            const reader = new FileReader();
            reader.onload = function (event) {
                preview.src = event.target.result;
                preview.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        });
    }

    setupImagePreview('id_image', 'id_image_preview', 'id_image_name', 'id_image_error');
    setupImagePreview('proof_image', 'proof_image_preview', 'proof_image_name', 'proof_image_error');
</script>
</body>
</html>