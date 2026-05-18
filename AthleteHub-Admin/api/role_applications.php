<?php
/**
 * AthleteHub Admin — Role Applications API
 * Handles: approve, reject actions
 */
session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $sessionToken   = $_SESSION['csrf_token'] ?? '';
    if (empty($sessionToken) || !hash_equals($sessionToken, $submittedToken)) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
}

$action = $_POST['action'] ?? '';

switch ($action) {

    case 'approve':
        $appId = (int) ($_POST['app_id'] ?? 0);
        $adminId = $_SESSION['user_id'];

        // Get application details
        $stmt = $pdo->prepare("SELECT * FROM role_applications WHERE id = ?");
        $stmt->execute([$appId]);
        $app = $stmt->fetch();

        if (!$app) {
            echo json_encode(['success' => false, 'message' => 'Application not found']);
            break;
        }

        $pdo->beginTransaction();
        try {
            // Update application status
            $stmt = $pdo->prepare("UPDATE role_applications SET status='approved', reviewed_by=?, reviewed_at=NOW() WHERE id=?");
            $stmt->execute([$adminId, $appId]);

            // Update user role and verify
            $stmt = $pdo->prepare("UPDATE users SET role=?, is_verified=1 WHERE id=?");
            $stmt->execute([$app['requested_role'], $app['user_id']]);

            // Notify user via messages
            $msg = "🎉 Congratulations! Your application for the '{$app['requested_role']}' role has been approved. Your account has been upgraded.";
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text) VALUES (?, ?, ?)");
            $stmt->execute([$adminId, $app['user_id'], $msg]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Application approved']);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;

    case 'reject':
        $appId     = (int) ($_POST['app_id'] ?? 0);
        $adminNote = trim($_POST['admin_note'] ?? '');
        $adminId   = $_SESSION['user_id'];

        if (empty($adminNote)) {
            echo json_encode(['success' => false, 'message' => 'Admin note is required for rejection']);
            break;
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE role_applications SET status='rejected', admin_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?");
            $stmt->execute([$adminNote, $adminId, $appId]);

            // Get user_id
            $stmt = $pdo->prepare("SELECT user_id FROM role_applications WHERE id=?");
            $stmt->execute([$appId]);
            $userId = $stmt->fetchColumn();

            // Notify user
            $msg = "Your role application has been rejected. Reason: {$adminNote}";
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text) VALUES (?, ?, ?)");
            $stmt->execute([$adminId, $userId, $msg]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Application rejected']);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
