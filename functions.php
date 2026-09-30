<?php
// Check if the user is logged in
function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

// Force login before viewing a page
function requireLogin()
{
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

// Force admin role before viewing a page
function requireAdmin()
{
    requireLogin();
    if ($_SESSION['role'] != 'admin') {
        header("Location: dashboard.php");
        exit();
    }
}

// Force verified status before posting
function requireVerified()
{
    requireLogin();
    if ($_SESSION['role'] != 'admin' && $_SESSION['verification_status'] != 'VERIFIED') {
        header("Location: verify_request.php");
        exit();
    }
}

// Clean text before displaying it (prevents XSS)
function clean($text)
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

// CSRF token functions
function generateCSRFToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token)
{
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        die("Invalid request. Please go back and try again.");
    }
}

// Handle an uploaded image file. Returns the saved filename or null on failure.
function uploadImage($fileInputName, $destinationFolder)
{
    if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    $fileType = $_FILES[$fileInputName]['type'];
    $fileSize = $_FILES[$fileInputName]['size'];

    // Max 5MB
    if (!in_array($fileType, $allowedTypes) || $fileSize > 5 * 1024 * 1024) {
        return false;
    }

    if (!is_dir($destinationFolder)) {
        mkdir($destinationFolder, 0755, true);
    }

    $extension = pathinfo($_FILES[$fileInputName]['name'], PATHINFO_EXTENSION);
    $newFileName = uniqid('img_', true) . '.' . $extension;
    $destinationPath = $destinationFolder . '/' . $newFileName;

    if (move_uploaded_file($_FILES[$fileInputName]['tmp_name'], $destinationPath)) {
        return $newFileName;
    }

    return false;
}