<?php
/**
 * AthleteHub Admin — Tournaments API
 */
session_start();
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'change_status':
        $id     = (int) ($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['open','closed','upcoming'])) {
            echo json_encode(['success'=>false,'message'=>'Invalid status']); break;
        }
        $stmt = $pdo->prepare("UPDATE tournaments SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        echo json_encode(['success'=>true,'message'=>'Status updated']);
        break;

    case 'participants':
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT u.id, u.name, u.email, u.role, u.sport, tr.registered_at
                               FROM tournament_registrations tr JOIN users u ON tr.user_id = u.id
                               WHERE tr.tournament_id = ? ORDER BY tr.registered_at DESC");
        $stmt->execute([$id]);
        echo json_encode(['success'=>true,'participants'=>$stmt->fetchAll()]);
        break;

    case 'delete':
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM tournaments WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success'=>true,'message'=>'Tournament deleted']);
        break;

    default:
        echo json_encode(['success'=>false,'message'=>'Invalid action']);
}
