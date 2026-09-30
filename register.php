<?php
session_start();
include "database.php";
include "functions.php";

$errors = [];
$csrfToken = generateCSRFToken();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    verifyCSRFToken($_POST['csrf_token'] ?? '');

    $fullName = trim($_POST['full_name']);
    $usernameInput = trim($_POST['username']);
    $email = trim($_POST['email']);
    $passwordInput = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    if (empty($fullName) || empty($usernameInput) || empty($email) || empty($passwordInput)) {
        $errors[] = "All fields are required.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    if (strlen($passwordInput) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }

    if ($passwordInput !== $confirmPassword) {
        $errors[] = "Passwords do not match.";
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$usernameInput, $email]);
        if ($stmt->fetch()) {
            $errors[] = "Username or email is already registered.";
        }
    }

    if (empty($errors)) {
        $hashedPassword = password_hash($passwordInput, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            "INSERT INTO users (full_name, username, email, password, role, verification_status)
             VALUES (?, ?, ?, ?, 'user', 'UNVERIFIED')"
        );
        $stmt->execute([$fullName, $usernameInput, $email, $hashedPassword]);

        $_SESSION['registered'] = true;
        header("Location: login.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register - VERIFY PH</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <div class="brand">
        <img src="images/favicon.ico" alt="VERIFY PH logo" class="brand-logo">
        <h1>VERIFY PH</h1>
    </div>
    <nav><a href="index.php">Home</a><a href="login.php">Login</a></nav>
</header>

<main>
    <h2 style="text-align:center;">Create an Account</h2>

    <form method="POST" action="register.php">
        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

        <?php if (!empty($errors)): ?>
            <div class="error-box">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo clean($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <label>Full Name</label>
        <input type="text" name="full_name" required>

        <label>Username</label>
        <input type="text" name="username" required>

        <label>Email</label>
        <input type="email" name="email" required>

        <label>Password</label>
        <div class="password-field">
            <input type="password" name="password" id="registerPassword" required>
            <button type="button" class="password-toggle" data-target="registerPassword" aria-label="Show password">👁</button>
        </div>

        <label>Confirm Password</label>
        <div class="password-field">
            <input type="password" name="confirm_password" id="confirmPassword" required>
            <button type="button" class="password-toggle" data-target="confirmPassword" aria-label="Show password">👁</button>
        </div>

        <button type="submit" class="btn" style="margin-top:15px; width:100%;">Register</button>
    </form>

    <p style="text-align:center;">Already have an account? <a href="login.php">Login here</a></p>
</main>

<footer>&copy; <?php echo date("Y"); ?> VERIFY PH.</footer>

<script>
    document.querySelectorAll('.password-toggle').forEach(button => {
        button.addEventListener('click', function () {
            const input = document.getElementById(this.dataset.target);
            const isPassword = input.type === 'password';

            input.type = isPassword ? 'text' : 'password';
            this.textContent = isPassword ? '🙈' : '👁';
            this.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        });
    });
</script>
</body>
</html>