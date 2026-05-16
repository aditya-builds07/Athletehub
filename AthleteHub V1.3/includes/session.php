<?php
/**
 * AthleteHub — Session Guard
 * Starts session, redirects unauthenticated users, and manages CSRF tokens.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to index (login) if user is not authenticated
if (!isset($_SESSION['user_id'])) {
    $projectRoot = str_replace('\\', '/', dirname(__DIR__));
    $docRoot     = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    $relPath     = '/' . trim(str_replace($docRoot, '', $projectRoot), '/');
    header('Location: ' . $relPath . '/index.php');
    exit();
}


// ── CSRF Token Management ──
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Validate CSRF token from request.
 * Checks both POST body and X-CSRF-Token header (for AJAX JSON requests).
 * @return bool
 */
function validate_csrf_token(): bool {
    $token = $_POST['csrf_token']
          ?? $_SERVER['HTTP_X_CSRF_TOKEN']
          ?? '';
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Output a <meta> tag containing the CSRF token.
 * Call this in the <head> of HTML pages so JS can read it.
 */
function csrf_meta_tag(): string {
    return '<meta name="csrf-token" content="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
}
