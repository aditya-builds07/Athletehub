<?php
/**
 * AthleteHub — Role Application API
 * Handles: submit, approve, reject actions.
 * Fixed: CSRF function name, message_text column, error logging.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error to log, never to screen
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/error.log');

require_once '../config/db.php';
require_once '../includes/session.php';

header('Content-Type: application/json');

// ── CSRF validation ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $sessionToken   = $_SESSION['csrf_token'] ?? '';
    if (empty($sessionToken) || !hash_equals($sessionToken, $submittedToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token. Please refresh and try again.']);
        exit;
    }
}

$action   = $_REQUEST['action'] ?? '';
$userId   = $_SESSION['user_id'] ?? 0;
$userRole = $_SESSION['role'] ?? '';

if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated.']);
    exit;
}

/**
 * Sanitize social handles from full URLs or bare handles.
 */
function sanitize_handle(string $input, string $platform): ?string {
    $input = trim($input);
    if ($input === '') return null;

    $patterns = [
        'instagram' => '/(?:https?:\/\/)?(?:www\.)?instagram\.com\/([a-zA-Z0-9._]+)\/?/i',
        'twitter'   => '/(?:https?:\/\/)?(?:www\.)?(?:twitter|x)\.com\/([a-zA-Z0-9_]+)\/?/i',
        'linkedin'  => '/(?:https?:\/\/)?(?:www\.)?linkedin\.com\/in\/([a-zA-Z0-9_-]+)\/?/i',
        'facebook'  => '/(?:https?:\/\/)?(?:www\.)?facebook\.com\/([a-zA-Z0-9._-]+)\/?/i',
        'youtube'   => '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/(?:@|user\/|channel\/)?([a-zA-Z0-9_-]+)\/?/i',
    ];

    if (isset($patterns[$platform]) && preg_match($patterns[$platform], $input, $m)) {
        return $m[1];
    }
    // Strip any leading @ or /
    return ltrim($input, '/@');
}

switch ($action) {

    // ════════════════════════════════════════════════════
    case 'submit':
    // ════════════════════════════════════════════════════
        if (!in_array($userRole, ['athlete', 'coach'], true)) {
            echo json_encode(['success' => false, 'error' => 'Only athletes and coaches can apply for elevated roles.']);
            exit;
        }

        // ── Collect & sanitize inputs ──
        $requestedRole = $_POST['requested_role'] ?? '';
        $phone         = trim($_POST['phone']              ?? '');
        $city          = trim($_POST['city']               ?? '');
        $country       = trim($_POST['country']            ?? '');
        $orgName       = trim($_POST['organisation_name']  ?? '');
        $description   = trim($_POST['description']        ?? '');
        $website       = trim($_POST['website']            ?? '') ?: null;
        $docType       = trim($_POST['document_type']      ?? '');

        // Numeric optional fields — cast safely
        $yearsExp    = isset($_POST['years_experience'])  && $_POST['years_experience'] !== '' ? (int) $_POST['years_experience']  : null;
        $playerCount = isset($_POST['team_player_count']) && $_POST['team_player_count'] !== '' ? (int) $_POST['team_player_count'] : null;

        // Socials
        $instagram = sanitize_handle($_POST['instagram'] ?? '', 'instagram');
        $twitter   = sanitize_handle($_POST['twitter']   ?? '', 'twitter');
        $linkedin  = sanitize_handle($_POST['linkedin']  ?? '', 'linkedin');
        $facebook  = sanitize_handle($_POST['facebook']  ?? '', 'facebook');
        $youtube   = sanitize_handle($_POST['youtube']   ?? '', 'youtube');

        // ── Validate required fields ──
        if (!in_array($requestedRole, ['club', 'recruiter'], true)
            || empty($orgName) || empty($description)
            || empty($phone)   || empty($docType)
            || empty($city)    || empty($country)
        ) {
            echo json_encode(['success' => false, 'error' => 'All required fields must be filled.']);
            exit;
        }

        // ── Block duplicate pending application ──
        $chk = $pdo->prepare("SELECT id FROM role_applications WHERE user_id = ? AND status = 'pending'");
        $chk->execute([$userId]);
        if ($chk->fetch()) {
            echo json_encode(['success' => false, 'error' => 'You already have a pending application.']);
            exit;
        }

        // ── Profile Photo Upload (optional) ──
        $profilePhotoPath = null;
        if (!empty($_FILES['profile_photo']['tmp_name']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $photo    = $_FILES['profile_photo'];
            $finfo    = new finfo(FILEINFO_MIME_TYPE);
            $mime     = $finfo->file($photo['tmp_name']);
            if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
                echo json_encode(['success' => false, 'error' => 'Invalid profile photo. JPG/PNG only.']);
                exit;
            }
            if ($photo['size'] > 2 * 1024 * 1024) {
                echo json_encode(['success' => false, 'error' => 'Profile photo exceeds 2MB limit.']);
                exit;
            }
            $photoDir = '../assets/uploads/profile_photos/';
            if (!is_dir($photoDir)) mkdir($photoDir, 0755, true);

            // Protect profile photos directory from PHP execution
            $photoHtaccessPath = $photoDir . '.htaccess';
            if (!file_exists($photoHtaccessPath)) {
                file_put_contents($photoHtaccessPath,
                    "php_flag engine off\nOptions -ExecCGI\nRemoveHandler .php .phtml .php3\nAddType text/plain .php .phtml\n");
            }

            $photoExt  = strtolower(pathinfo($photo['name'], PATHINFO_EXTENSION));
            $photoName = 'photo_' . bin2hex(random_bytes(16)) . '.' . $photoExt;
            move_uploaded_file($photo['tmp_name'], $photoDir . $photoName);
            $profilePhotoPath = 'assets/uploads/profile_photos/' . $photoName;
        }

        // ── Document Upload (required) ──
        if (empty($_FILES['document']['tmp_name']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'Please upload a valid document.']);
            exit;
        }
        $file      = $_FILES['document'];
        $finfo     = new finfo(FILEINFO_MIME_TYPE);
        $realMime  = $finfo->file($file['tmp_name']);
        if (!in_array($realMime, ['image/jpeg', 'image/png', 'application/pdf'], true)) {
            echo json_encode(['success' => false, 'error' => 'Invalid document type. JPG, PNG, or PDF only.']);
            exit;
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'Document exceeds 5MB limit.']);
            exit;
        }

        $uploadDir = '../assets/uploads/role_docs/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        // Protect documents directory from PHP execution
        $htaccessPath = $uploadDir . '.htaccess';
        if (!file_exists($htaccessPath)) {
            file_put_contents($htaccessPath,
                "php_flag engine off\nOptions -ExecCGI\nRemoveHandler .php .phtml .php3\nAddType text/plain .php .phtml\n");
        }

        $docExt      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $docFilename = 'doc_' . bin2hex(random_bytes(16)) . '.' . $docExt;
        move_uploaded_file($file['tmp_name'], $uploadDir . $docFilename);
        $dbDocPath = 'assets/uploads/role_docs/' . $docFilename;

        // ── INSERT ──
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO role_applications (
                    user_id, requested_role, organisation_name, description,
                    website, phone, document_path, document_type,
                    profile_photo, years_experience, team_player_count,
                    city, country,
                    instagram, twitter, linkedin, facebook, youtube,
                    status, submitted_at
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?,
                    ?, ?, ?, ?, ?,
                    'pending', NOW()
                )
            ");

            $stmt->execute([
                $userId, $requestedRole, $orgName, $description,
                $website, $phone, $dbDocPath, $docType,
                $profilePhotoPath, $yearsExp, $playerCount,
                $city, $country,
                $instagram, $twitter, $linkedin, $facebook, $youtube,
            ]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Application submitted successfully.']);

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[role_application submit] PDO Error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Submission failed. Please try again later.']);
        }
        break;

    // ════════════════════════════════════════════════════
    case 'approve':
    // ════════════════════════════════════════════════════
        if ($userRole !== 'admin') {
            echo json_encode(['success' => false, 'error' => 'Admin access required.']);
            exit;
        }
        $appId     = (int) ($_POST['application_id'] ?? 0);
        $adminNote = trim($_POST['admin_note'] ?? '');
        try {
            $stmt = $pdo->prepare("SELECT user_id, requested_role, status FROM role_applications WHERE id = ?");
            $stmt->execute([$appId]);
            $app = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$app || $app['status'] !== 'pending') {
                echo json_encode(['success' => false, 'error' => 'Invalid or already processed application.']);
                exit;
            }
            $applicantId = $app['user_id'];
            $reqRole     = $app['requested_role'];

            $pdo->beginTransaction();
            $pdo->prepare("UPDATE role_applications SET status='approved', admin_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?")
                ->execute([$adminNote, $userId, $appId]);
            $pdo->prepare("UPDATE users SET role=?, is_verified=1 WHERE id=?")
                ->execute([$reqRole, $applicantId]);

            // Notification via messages table (message_text column)
            $msgContent = "🎉 Your application for the " . strtoupper($reqRole) . " role has been approved! Your account has been upgraded.";
            $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text) VALUES (?, ?, ?)")
                ->execute([$userId, $applicantId, $msgContent]);

            // Notifications table
            $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'role_approved', ?)")
                ->execute([$applicantId, $msgContent]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Application approved.']);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[role_application approve] PDO Error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Database error. Please try again.']);
        }
        break;

    // ════════════════════════════════════════════════════
    case 'reject':
    // ════════════════════════════════════════════════════
        if ($userRole !== 'admin') {
            echo json_encode(['success' => false, 'error' => 'Admin access required.']);
            exit;
        }
        $appId     = (int) ($_POST['application_id'] ?? 0);
        $adminNote = trim($_POST['admin_note'] ?? '');
        if (empty($adminNote)) {
            echo json_encode(['success' => false, 'error' => 'Rejection requires an admin note.']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("SELECT user_id, requested_role, status FROM role_applications WHERE id = ?");
            $stmt->execute([$appId]);
            $app = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$app || $app['status'] !== 'pending') {
                echo json_encode(['success' => false, 'error' => 'Invalid or already processed application.']);
                exit;
            }
            $applicantId = $app['user_id'];
            $reqRole     = $app['requested_role'];

            $pdo->beginTransaction();
            $pdo->prepare("UPDATE role_applications SET status='rejected', admin_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?")
                ->execute([$adminNote, $userId, $appId]);

            $msgContent = "Your application for the " . strtoupper($reqRole) . " role was not approved. Reason: " . $adminNote;
            $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text) VALUES (?, ?, ?)")
                ->execute([$userId, $applicantId, $msgContent]);
            $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'role_rejected', ?)")
                ->execute([$applicantId, $msgContent]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Application rejected.']);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[role_application reject] PDO Error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Database error. Please try again.']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action.']);
        break;
}
