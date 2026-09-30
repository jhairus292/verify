<?php
session_start();
include "database.php";
include "functions.php";

$errors = [];
$csrfToken = generateCSRFToken();
$loginAs = $_POST['login_as'] ?? 'user';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    verifyCSRFToken($_POST['csrf_token'] ?? '');

    $usernameInput = trim($_POST['username']);
    $passwordInput = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$usernameInput, $usernameInput]);
    $user = $stmt->fetch();

    if ($user && password_verify($passwordInput, $user['password'])) {

        if ($user['status'] == 'suspended') {
            $errors[] = "This account has been suspended.";
        } elseif ($loginAs == 'admin' && $user['role'] != 'admin') {
            $errors[] = "This account is not an admin. Use the User tab instead.";
        } elseif ($loginAs == 'user' && $user['role'] == 'admin') {
            $errors[] = "This is an admin account. Use the Admin tab instead.";
        } else {
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['verification_status'] = $user['verification_status'];

            if ($user['role'] == 'admin') {
                header("Location: admin.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        }
    } else {
        $errors[] = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - VERIFY PH</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Single unified card: tabs + form share ONE shadow/border so nothing overlaps */
        .login-card {
            max-width: 460px;
            margin: 24px auto;
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e4e7ec;
            box-shadow: 0 1px 3px rgba(16,24,40,0.08);
            overflow: hidden;
        }
        .login-tabs {
            display: flex;
        }
        .login-tab {
            all: unset;
            box-sizing: border-box;
            flex: 1;
            text-align: center;
            padding: 14px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            background: #f2f4f7;
            color: #667085;
        }
        .login-tab.active {
            background: #fff;
            color: #101828;
        }
        /* Override the global <form> card styling since this form now lives inside .login-card */
        .login-card form {
            background: none;
            border: none;
            box-shadow: none;
            border-radius: 0;
            max-width: none;
            margin: 0;
            padding: 32px;
        }
    </style>
</head>
<body>

<header>
    <div class="brand">
        <img src="images/favicon.ico" alt="VERIFY PH logo" class="brand-logo">
        <h1>VERIFY PH</h1>
    </div>
    <nav><a href="index.php">Home</a><a href="register.php">Register</a></nav>
</header>

<main>
    <h2 style="text-align:center;">Login</h2>

    <div class="login-card">
        <div class="login-tabs">
            <button type="button" class="login-tab <?php echo $loginAs == 'user' ? 'active' : ''; ?>" onclick="setLoginAs('user', this)">User</button>
            <button type="button" class="login-tab <?php echo $loginAs == 'admin' ? 'active' : ''; ?>" onclick="setLoginAs('admin', this)">Admin</button>
        </div>

        <form method="POST" action="login.php" id="loginForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="login_as" id="login_as" value="<?php echo clean($loginAs); ?>">

            <?php if (!empty($errors)): ?>
                <div class="error-box">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo clean($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['registered'])): ?>
                <div class="success-box">Account created. You may now log in.</div>
                <?php unset($_SESSION['registered']); ?>
            <?php endif; ?>

            <label>Username or Email</label>
            <input type="text" name="username" required>

            <label>Password</label>
            <div class="password-field">
                <input type="password" name="password" id="loginPassword" required>
                <button type="button" class="password-toggle" data-target="loginPassword" aria-label="Show password">👁</button>
            </div>

            <button type="submit" class="btn" style="margin-top:15px; width:100%;" id="loginBtn">Login</button>

            <p style="text-align:center; margin-top:18px;">
                Don't have an account? <a href="register.php"><strong>Register here</strong></a>
            </p>
        </form>
    </div>
</main>

<footer>&copy; <?php echo date("Y"); ?> VERIFY PH.</footer>

<script>
    function setLoginAs(role, btn) {
        document.getElementById('login_as').value = role;
        document.querySelectorAll('.login-tab').forEach(t => t.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('loginBtn').textContent = role == 'admin' ? 'Login as Admin' : 'Login';
    }

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