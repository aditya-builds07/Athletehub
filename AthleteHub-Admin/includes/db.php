<?php
/**
 * AthleteHub Admin — Database Configuration
 * Connects to the SAME athletehub database as the main app.
 * PDO MySQL connection with error handling.
 */

define('DB_HOST',    'localhost');
define('DB_NAME',    'athletehub');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

/* Dynamic base URL detection */
$projectRoot = str_replace('\\', '/', dirname(__DIR__));
$docRoot     = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
$relPath     = '/' . trim(str_replace($docRoot, '', $projectRoot), '/');
define('BASE_URL', $relPath);
define('MAIN_APP_URL', dirname($relPath) . '/AthleteHub V1.3');


$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}
