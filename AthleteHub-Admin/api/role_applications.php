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

            // Notify user via messages — expanded with rules & conditions
            $rolePerms = [
                'club'      => "✅ Create & manage tournaments\n✅ Recruit athletes and coaches\n✅ Host livestreams\n✅ Post recruitment listings",
                'recruiter' => "✅ Browse and scout athlete profiles\n✅ Post recruitment opportunities\n✅ Send direct recruitment offers\n✅ Access advanced search filters",
                'athlete'   => "✅ Register for tournaments\n✅ Build your sports portfolio\n✅ Connect with coaches and clubs\n✅ Apply for recruitment listings",
                'coach'     => "✅ Create training programs\n✅ Connect with athletes and clubs\n✅ Host livestream training sessions\n✅ Post coaching availability",
            ];
            $permsText = $rolePerms[$app['requested_role']] ?? "✅ Access role-specific features";

            $msg = "🎉 Congratulations! Your application for the '"
                . $app['requested_role']
                . "' role has been approved. Your account has been upgraded.\n\n"
                . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                . "🔑 YOUR NEW PERMISSIONS:\n"
                . $permsText . "\n\n"
                . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                . "📋 RULES & CONDITIONS:\n"
                . "• Your role is on a 30-day probation period. Maintain regular activity to keep your privileges.\n"
                . "• Please maintain professional conduct at all times. Violations may result in role revocation.\n"
                . "• Review our community guidelines before using your new privileges.\n"
                . "• Misuse of role privileges (spam, harassment, fake listings) will lead to immediate suspension.\n\n"
                . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                . "💬 NEED HELP?\n"
                . "If you have any questions, message the Admin directly or visit the Help Center.\n\n"
                . "Welcome aboard! 🚀";
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
