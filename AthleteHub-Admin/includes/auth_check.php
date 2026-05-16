<?php
/**
 * AthleteHub Admin — Authentication Guard
 * Include at the TOP of every admin page.
 * Verifies admin session; redirects to login if invalid.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (
    !isset($_SESSION['admin_id']) ||
    !isset($_SESSION['admin_role']) ||
    $_SESSION['admin_role'] !== 'admin'
) {
    header('Location: ' . '/dashboard/AthleteHub-Admin/index.php');
    exit;
}
