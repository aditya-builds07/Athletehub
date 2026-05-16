<?php
/**
 * AthleteHub Admin — Live Streams API
 */
session_start();
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'force_end':
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE live_streams SET status = 'ended' WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Stream ended']);
        break;

    case 'delete':
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM live_streams WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Stream deleted']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
