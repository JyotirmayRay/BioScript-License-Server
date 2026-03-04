<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function check_auth()
{
    if (empty($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        header('Location: index.php');
        exit;
    }
}

function login($username, $password)
{
    global $pdo;

    // Authenticate against platform_settings table
    $stmt = $pdo->query("SELECT admin_user, admin_pass FROM platform_settings WHERE id = 1");
    $admin = $stmt->fetch();

    if ($admin && $username === $admin['admin_user'] && password_verify($password, $admin['admin_pass'])) {
        $_SESSION['is_admin'] = true;
        return true;
    }
    return false;
}

function logout()
{
    $_SESSION = [];
    session_destroy();
    header('Location: index.php');
    exit;
}