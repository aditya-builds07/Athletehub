<?php
/**
 * AthleteHub Admin — News API
 */
session_start();
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'create':
        $title    = trim($_POST['title'] ?? '');
        $content  = trim($_POST['content'] ?? '');
        $imageUrl = trim($_POST['image_url'] ?? '') ?: null;

        if (!$title || !$content) {
            echo json_encode(['success'=>false,'message'=>'Title and content are required']); break;
        }

        $stmt = $pdo->prepare("INSERT INTO news (title, content, image_url, posted_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $content, $imageUrl, $_SESSION['admin_id']]);
        echo json_encode(['success'=>true,'message'=>'News article published']);
        break;

    case 'update':
        $id       = (int) ($_POST['id'] ?? 0);
        $title    = trim($_POST['title'] ?? '');
        $content  = trim($_POST['content'] ?? '');
        $imageUrl = trim($_POST['image_url'] ?? '') ?: null;

        $stmt = $pdo->prepare("UPDATE news SET title=?, content=?, image_url=? WHERE id=?");
        $stmt->execute([$title, $content, $imageUrl, $id]);
        echo json_encode(['success'=>true,'message'=>'News article updated']);
        break;

    case 'delete':
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM news WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success'=>true,'message'=>'News article deleted']);
        break;

    default:
        echo json_encode(['success'=>false,'message'=>'Invalid action']);
}
