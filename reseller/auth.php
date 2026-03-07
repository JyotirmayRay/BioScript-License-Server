<?php
// /reseller/auth.php
declare(strict_types=1);

// Ensure sessions use secure cookie parameters before starting
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => 86400, // 24 hours
    'path' => '/',
    'domain' => $cookieParams['domain'],
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Strict'
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Check if reseller is logged in
if (!isset($_SESSION['reseller_logged_in']) || $_SESSION['reseller_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Check for session timeout (e.g., 2 hours of inactivity)
$timeout_duration = 7200;
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: login.php?msg=" . urlencode("Session expired. Please log in again."));
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

// Optional: Regenerate session ID periodically to prevent fixation
if (!isset($_SESSION['CREATED'])) {
    $_SESSION['CREATED'] = time();
}
else if (time() - $_SESSION['CREATED'] > 1800) {
    // interval = 30 mins
    session_regenerate_id(true);
    $_SESSION['CREATED'] = time();
}