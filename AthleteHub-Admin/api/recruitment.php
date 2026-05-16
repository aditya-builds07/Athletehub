<?php
/**
 * AthleteHub Admin — Recruitment API
 * Handles: deactivate, delete
 */
session_start();
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'deactivate':
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE recruitment SET is_active = 0 WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Listing deactivated']);
        break;

    case 'delete':
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM recruitment WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Listing deleted']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
