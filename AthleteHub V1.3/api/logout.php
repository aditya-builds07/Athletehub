<?php
/**
 * AthleteHub — Secure Logout
 * Clears session data, destroys session cookie, and redirects to login.
 */
session_start();

// Clear all session data
session_unset();

// Destroy the session
session_destroy();

// ── Security: Clear the session cookie to fully invalidate the session ──
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

header('Location: ../index.php');
exit();
