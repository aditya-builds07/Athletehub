<?php
session_start();
require_once '../includes/session.php';
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    $query = trim($_GET['q'] ?? '');

    if (strlen($query) < 2) {
        echo json_encode(['results' => []]);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, name, role, profile_pic FROM users WHERE name LIKE ? AND role != 'admin' LIMIT 6");
        $stmt->execute(['%' . $query . '%']);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['results' => $results]);
    } catch (Exception $e) {
        echo json_encode(['results' => []]);
    }
}
