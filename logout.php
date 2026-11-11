<?php
/**
 * CommitTrack - Secure Logout Script
 * Clears session data, unsets session cookie, destroys server session,
 * and safely redirects to login page.
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Unset all session variables
$_SESSION = [];

// Delete the session cookie from client browser if present
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy session on server
session_destroy();

// Redirect back to login page with logged-out flash status
header('Location: login.php?logged_out=1');
exit;
