<?php
/**
 * Admin Logout Script
 * Destroys the admin session and redirects to login page
 */

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include configuration and functions
require_once '../includes/config.php';
require_once '../includes/db-connection.php';
require_once '../includes/functions.php';

// Log the logout action if admin was logged in
if (isset($_SESSION['admin_id']) && function_exists('logAdminAction')) {
    logAdminAction('logout', 'Admin logged out successfully');
}

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Clear any remember me cookies if they exist
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

if (isset($_COOKIE['remember_user'])) {
    setcookie('remember_user', '', time() - 3600, '/');
}

// Redirect to login page with success message
header('Location: index.php?logout=success');
exit();
?>