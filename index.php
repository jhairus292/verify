<?php
session_start();
include "database.php";
include "functions.php";
?>
<!DOCTYPE html>
<html>
<head>
    <title>VERIFY PH</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <div class="brand">
        <img src="images/favicon.ico" alt="VERIFY PH logo" class="brand-logo">
        <h1>VERIFY PH</h1>
    </div>
    <nav>
        <?php if (isLoggedIn()): ?>
            <a href="dashboard.php">Dashboard</a>
            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
        <?php endif; ?>
    </nav>
</header>

<main>
    <section class="hero">
        <h2>Verified Information. Trusted Community.</h2>
        <p>Only ID-verified school personnel, government, and company representatives can post announcements here.</p>
        <?php if (isLoggedIn()): ?>
            <a href="dashboard.php" class="btn">Go to Dashboard</a>
        <?php else: ?>
            <a href="login.php" class="btn">Login</a>
            <a href="register.php" class="btn btn-secondary">Register</a>
        <?php endif; ?>

        <div class="hero-badges">
            <span>Verified users</span>
            <span>Community updates</span>
            <span>Public trust</span>
        </div>
    </section>

    <section class="feature-grid">
        <article class="feature-card">
            <div class="feature-icon">✓</div>
            <h3>Identity-checked</h3>
            <p>Every post originates from verified officials and recognized institutions.</p>
        </article>

        <article class="feature-card">
            <div class="feature-icon">🔒</div>
            <h3>Trusted access</h3>
            <p>Only authorized users can publish, update, and manage community announcements.</p>
        </article>

        <article class="feature-card">
            <div class="feature-icon">📣</div>
            <h3>Clear communication</h3>
            <p>Keep schools, agencies, and residents aligned through organized updates.</p>
        </article>
    </section>

    <section class="info-panel">
        <div>
            <span class="eyebrow">Why it matters</span>
            <h3>Reliable information for the people who need it most.</h3>
            <p>VERIFY PH helps public-facing organizations share important updates in a secure, readable, and accountable way.</p>
        </div>

        <ul class="trust-list">
            <li>Reduce misinformation from unverified sources.</li>
            <li>Give institutions a clean way to publish updates.</li>
            <li>Build confidence through accountable verification.</li>
        </ul>
    </section>
</main>

<footer>&copy; <?php echo date("Y"); ?> VERIFY PH.</footer>

</body>
</html>