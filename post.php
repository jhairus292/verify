<?php
session_start();
include "database.php";
include "functions.php";

requireVerified();

$userId = $_SESSION['user_id'];
$csrfToken = generateCSRFToken();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    verifyCSRFToken($_POST['csrf_token'] ?? '');

    $title = trim($_POST['title']);
    $content = trim($_POST['content']);

    if (empty($title) || empty($content)) {
        $errors[] = "Title and content are required.";
    }

    $imageFileName = null;

    if (!empty($_FILES['image']['name'])) {
        $imageFileName = uploadImage('image', 'uploads/posts');
        if ($imageFileName === false) {
            $errors[] = "Image must be JPG or PNG and under 5MB.";
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            "INSERT INTO announcements (user_id, title, content, image_path) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $title, $content, $imageFileName]);

        header("Location: dashboard.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>New Announcement - VERIFY PH</title>
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
    <h2 style="text-align:center;">Post an Announcement</h2>

    <form method="POST" action="post.php" enctype="multipart/form-data" class="form-wide">
        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

        <?php if (!empty($errors)): ?>
            <div class="error-box">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo clean($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <label>Title</label>
        <input type="text" name="title" required>

        <label>Content</label>
        <textarea name="content" required></textarea>

        <label>Image (optional)</label>
        <input type="file" name="image" accept="image/jpeg,image/png">

        <button type="submit" class="btn" style="margin-top:15px;">Publish</button>
    </form>
</main>

<footer>&copy; <?php echo date("Y"); ?> VERIFY PH.</footer>
</body>
</html>