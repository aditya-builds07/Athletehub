<?php
/**
 * AthleteHub Admin — Database Configuration  v2.0
 * ─────────────────────────────────────────────────
 * Loaded by auth_check.php (which is included first on every page).
 * Do NOT call session_start() here — auth_check.php owns the session.
 *
 * Defines:
 *   - DB_* constants
 *   - BASE_URL, ASSETS, MAIN_APP_URL constants
 *   - PDO $pdo instance
 *   - e() XSS-escape helper
 */

// ── DB constants ──────────────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'athletehub');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

// ── URL constants (dynamic — works in any subdirectory depth) ──────
if (!defined('BASE_URL')) {
    $projectRoot = str_replace('\\', '/', dirname(__DIR__));
    $docRoot     = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    $relPath     = '/' . trim(str_replace($docRoot, '', $projectRoot), '/');
    define('BASE_URL',     $relPath);
    define('ASSETS',       $relPath . '/assets');
    define('MAIN_APP_URL', dirname($relPath) . '/AthleteHub V1.3');
}

// ── PDO Connection ────────────────────────────────────────────────
$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Never expose connection details to the browser
    error_log('[db.php] PDO connection failed: ' . $e->getMessage());
    // For API callers: return JSON; for page callers: plain message
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    http_response_code(503);
    die(json_encode(['success' => false, 'error' => 'Database unavailable. Please try again later.']));
}

// ── XSS Escape Helper ─────────────────────────────────────────────
if (!function_exists('e')) {
    function e($val): string {
        return htmlspecialchars((string)($val ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
