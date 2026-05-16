<?php
/**
 * AthleteHub — Recruitment API
 * Handles: apply, delete listing
 */

session_start();
require_once '../includes/session.php';
require_once '../config/db.php';

header('Content-Type: application/json');

// CSRF validation for all POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validate_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true);

switch ($action) {

    // ── APPLY TO A LISTING ──
    case 'apply':
        $jobId   = (int)($input['job_id'] ?? 0);
        $message = trim($input['message'] ?? '');

        if (!$jobId) {
            echo json_encode(['success' => false, 'error' => 'Invalid listing.']);
            exit;
        }

        // Check not already applied
        $check = $pdo->prepare("SELECT id FROM recruitment_applications WHERE recruitment_id = ? AND user_id = ?");
        $check->execute([$jobId, $userId]);
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'error' => 'You have already applied.']);
            exit;
        }

        // Check deadline not passed
        $deadlineStmt = $pdo->prepare("SELECT deadline, is_active, posted_by, title FROM recruitment WHERE id = ?");
        $deadlineStmt->execute([$jobId]);
        $listing = $deadlineStmt->fetch();

        if (!$listing) {
            echo json_encode(['success' => false, 'error' => 'Listing not found.']);
            exit;
        }
        if (!$listing['is_active']) {
            echo json_encode(['success' => false, 'error' => 'This listing is no longer active.']);
            exit;
        }
        if ($listing['deadline'] && strtotime($listing['deadline']) < strtotime('today')) {
            echo json_encode(['success' => false, 'error' => 'Application deadline has passed.']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            // Insert application
            $ins = $pdo->prepare("INSERT INTO recruitment_applications (recruitment_id, user_id) VALUES (?, ?)");
            $ins->execute([$jobId, $userId]);

            // Auto-insert system notification message
            $applicantName = $_SESSION['name'] ?? 'A user';
            $applicantRole = ucfirst($_SESSION['role'] ?? 'Athlete');
            $applicantSport = $_SESSION['sport'] ?? 'General';
            
            $msgText = "📢 New Application Received\n\n" . 
                       "Hello,\n\n" .
                       "I am interested in the position: \"{$listing['title']}\".\n" .
                       "My profile as a {$applicantRole} (" . ($applicantSport ? $applicantSport : "Multi-Sport") . ") aligns with your requirements.\n\n" .
                       "Please review my profile to evaluate my qualifications.\n\n" .
                       "Best regards,\n" .
                       "{$applicantName}";

            $msgStmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
            $msgStmt->execute([$userId, $listing['posted_by'], $msgText]);

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Could not submit application due to a database error.']);
            exit;
        }

        // Get updated applicant count
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM recruitment_applications WHERE recruitment_id = ?");
        $countStmt->execute([$jobId]);
        $applicantCount = (int)$countStmt->fetchColumn();

        echo json_encode([
            'success'         => true,
            'message'         => 'Application submitted!',
            'applicant_count' => $applicantCount
        ]);
        break;


    // ── DELETE A LISTING (own only) ──
    case 'delete':
        $jobId = (int)($input['job_id'] ?? 0);

        if (!$jobId) {
            echo json_encode(['success' => false, 'error' => 'Invalid listing.']);
            exit;
        }

        $del = $pdo->prepare("DELETE FROM recruitment WHERE id = ? AND posted_by = ?");
        $del->execute([$jobId, $userId]);

        if ($del->rowCount() > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Listing not found or not authorized.']);
        }
        break;


    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action.']);
        break;
}
