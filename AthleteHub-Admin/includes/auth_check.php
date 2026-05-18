<?php
/**
 * AthleteHub Admin — Authentication Guard  v2.0
 * ─────────────────────────────────────────────
 * Include at the VERY TOP of every admin page (before any output).
 *
 * Responsibilities:
 *   1. Start session (if not already running)
 *   2. Load DB config + constants (BASE_URL, ASSETS, e() helper)
 *   3. Generate CSRF token for this session (if absent)
 *   4. Enforce admin-only access — redirect + exit on failure
 */

// ── 1. Session ────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── 2. DB / constants / helpers ───────────────────────────────────
//    db.php defines BASE_URL, ASSETS, PDO $pdo, and e()
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/db.php';
}

// ── 3. CSRF token (one per session) ──────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── 4. Admin guard ────────────────────────────────────────────────
//    Canonical session keys: user_id + role = 'admin'
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role'])    ||
    $_SESSION['role'] !== 'admin'
) {
    header('Location: ' . BASE_URL . '/index.php?error=unauthorized');
    exit;
}
