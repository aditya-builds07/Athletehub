<?php
/**
 * AthleteHub — Database Configuration
 * PDO MySQL connection with error handling
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'athletehub');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/* Dynamic base URL detection */
$projectRoot = str_replace('\\', '/', dirname(__DIR__));
$docRoot     = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
$relPath     = '/' . trim(str_replace($docRoot, '', $projectRoot), '/');
define('BASE_URL', $relPath);


$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // In production, log this instead of displaying
    die('Database connection failed: ' . $e->getMessage());
}
