<?php
/**
 * AthleteHub — Database Configuration
 * PDO MySQL connection with secure error handling and global constants
 */

// ── Error Reporting Setup (Issue 9) ──
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// ── Database Credentials ──
define('DB_HOST', 'localhost');
define('DB_NAME', 'athletehub');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ── Global Paths (Issue 1) ──
/* Dynamic base URL detection */
$projectRoot = str_replace('\\', '/', dirname(__DIR__));
$docRoot     = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
$relPath     = '/' . trim(str_replace($docRoot, '', $projectRoot), '/');

define('BASE_URL', $relPath);
define('ASSETS',   BASE_URL . '/assets');
define('UPLOADS',  BASE_URL . '/uploads');

// ── PDO Connection (Issue 6) ──
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Create PDO instance
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Log error internally, do not leak to screen
    error_log("Database connection failed: " . $e->getMessage());
    die('A critical error occurred while connecting to the database. Please try again later.');
}
