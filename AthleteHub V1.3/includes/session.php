<?php
/**
 * AthleteHub — Session Guard
 * Starts session, redirects unauthenticated users, and manages CSRF tokens.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// XSS escape helper
function e($val): string {
    return htmlspecialchars($val ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// CSRF validation helper
function validate_csrf(): void {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die(json_encode(['error' => 'CSRF token mismatch']));
    }
}

// Auth check helper
function require_login(string $redirect = '/'): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . $redirect);
        exit();
    }
}

// Role check helper
function require_role(string $role, string $redirect = '/'): void {
    require_login($redirect);
    if (($_SESSION['role'] ?? '') !== $role) {
        header('Location: ' . $redirect);
        exit();
    }
}

/**
 * Output a <meta> tag containing the CSRF token.
 * Call this in the <head> of HTML pages so JS can read it.
 */
function csrf_meta_tag(): string {
    return '<meta name="csrf-token" content="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
}
