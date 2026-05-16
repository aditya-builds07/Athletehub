<?php
/**
 * AthleteHub — Role Application API
 * Enhanced to handle multi-step applications with social media and profile photos.
 */
require_once '../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../includes/session.php';

header('Content-Type: application/json');

// CSRF validation for all POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validate_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$action = $_REQUEST['action'] ?? '';
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

/**
 * Helper to sanitize social handles from full URLs
 */
function sanitize_handle($url, $platform) {
    if (empty($url)) return null;
    $url = trim($url);
    // Regex to extract handle from common URL patterns
    $patterns = [
        'instagram' => '/(?:https?:\/\/)?(?:www\.)?instagram\.com\/([a-zA-Z0-9._]+)\/?/i',
        'twitter'   => '/(?:https?:\/\/)?(?:www\.)?(?:twitter|x)\.com\/([a-zA-Z0-9_]+)\/?/i',
        'linkedin'  => '/(?:https?:\/\/)?(?:www\.)?linkedin\.com\/in\/([a-zA-Z0-9_-]+)\/?/i',
        'facebook'  => '/(?:https?:\/\/)?(?:www\.)?facebook\.com\/([a-zA-Z0-9._-]+)\/?/i',
        'youtube'   => '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/(?:@|user\/|channel\/)?([a-zA-Z0-9_-]+)\/?/i'
    ];
    
    if (isset($patterns[$platform]) && preg_match($patterns[$platform], $url, $matches)) {
        return $matches[1];
    }
    return ltrim(parse_url($url, PHP_URL_PATH) ?? $url, '/@');
}

switch ($action) {
    case 'submit':
        if ($userRole !== 'athlete' && $userRole !== 'coach') {
            echo json_encode(['success' => false, 'error' => 'Only athletes and coaches can apply for elevated roles.']);
            exit;
        }

        // Basic Info
        $requestedRole = $_POST['requested_role'] ?? '';
        $phone = trim($_POST['phone'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $country = trim($_POST['country'] ?? '');
        
        // Org Details
        $orgName = trim($_POST['organisation_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $website = trim($_POST['website'] ?? null);
        $yearsExp = isset($_POST['years_experience']) ? (int)$_POST['years_experience'] : null;
        $playerCount = isset($_POST['team_player_count']) ? (int)$_POST['team_player_count'] : null;
        
        // Documents
        $docType = trim($_POST['document_type'] ?? '');
        
        // Social Handles
        $instagram = sanitize_handle($_POST['instagram'] ?? '', 'instagram');
        $twitter = sanitize_handle($_POST['twitter'] ?? '', 'twitter');
        $linkedin = sanitize_handle($_POST['linkedin'] ?? '', 'linkedin');
        $facebook = sanitize_handle($_POST['facebook'] ?? '', 'facebook');
        $youtube = sanitize_handle($_POST['youtube'] ?? '', 'youtube');

        if (!in_array($requestedRole, ['club', 'recruiter']) || empty($orgName) || empty($description) || empty($phone) || empty($docType) || empty($city) || empty($country)) {
            echo json_encode(['success' => false, 'error' => 'All required fields must be filled.']);
            exit;
        }

        // Check pending
        $checkStmt = $pdo->prepare("SELECT id FROM role_applications WHERE user_id = ? AND status = 'pending'");
        $checkStmt->execute([$userId]);
        if ($checkStmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'You already have a pending application.']);
            exit;
        }

        // ── Handle Profile Photo Upload ──
        $profilePhotoPath = null;
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $photo = $_FILES['profile_photo'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($photo['tmp_name']);
            if (!in_array($mime, ['image/jpeg', 'image/png'])) {
                echo json_encode(['success' => false, 'error' => 'Invalid profile photo. JPG/PNG only.']);
                exit;
            }
            if ($photo['size'] > 2 * 1024 * 1024) {
                echo json_encode(['success' => false, 'error' => 'Profile photo exceeds 2MB limit.']);
                exit;
            }
            $photoDir = '../assets/uploads/profile_photos/';
            if (!is_dir($photoDir)) mkdir($photoDir, 0755, true);
            $photoExt = pathinfo($photo['name'], PATHINFO_EXTENSION);
            $photoName = 'photo_' . bin2hex(random_bytes(8)) . '.' . $photoExt;
            move_uploaded_file($photo['tmp_name'], $photoDir . $photoName);
            $profilePhotoPath = 'assets/uploads/profile_photos/' . $photoName;
        }

        // ── Handle Document Upload ──
        if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'Please upload a valid document.']);
            exit;
        }
        $file = $_FILES['document'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($file['tmp_name']);
        $allowedMime = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($realMime, $allowedMime)) {
            echo json_encode(['success' => false, 'error' => 'Invalid document type. JPG, PNG, and PDF only.']);
            exit;
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'Document exceeds 5MB limit.']);
            exit;
        }
        $uploadDir = '../assets/uploads/role_docs/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        $htaccessPath = $uploadDir . '.htaccess';
        if (!file_exists($htaccessPath)) {
            file_put_contents($htaccessPath, "php_flag engine off\nOptions -ExecCGI\nRemoveHandler .php .phtml .php3 .php4 .php5\nAddType text/plain .php .phtml .php3 .php4 .php5\n");
        }

        $docExt = pathinfo($file['name'], PATHINFO_EXTENSION);
        $docFilename = 'doc_' . bin2hex(random_bytes(12)) . '.' . $docExt;
        move_uploaded_file($file['tmp_name'], $uploadDir . $docFilename);
        $dbDocPath = 'assets/uploads/role_docs/' . $docFilename;

        try {
            $sql = "INSERT INTO role_applications 
                    (user_id, requested_role, organisation_name, description, website, phone, city, country, 
                     profile_photo, years_experience, team_player_count, instagram, twitter, linkedin, facebook, youtube, 
                     document_path, document_type, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $userId, $requestedRole, $orgName, $description, $website, $phone, $city, $country,
                $profilePhotoPath, $yearsExp, $playerCount, $instagram, $twitter, $linkedin, $facebook, $youtube,
                $dbDocPath, $docType
            ]);
            echo json_encode(['success' => true, 'message' => 'Application submitted']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'approve':
        if ($userRole !== 'admin') {
            echo json_encode(['success' => false, 'error' => 'Admin access required.']);
            exit;
        }
        $appId = (int)($_POST['application_id'] ?? 0);
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
            $reqRole = $app['requested_role'];
            $pdo->beginTransaction();
            $updApp = $pdo->prepare("UPDATE role_applications SET status='approved', admin_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?");
            $updApp->execute([$adminNote, $userId, $appId]);
            $updUser = $pdo->prepare("UPDATE users SET role=?, is_verified=1 WHERE id=?");
            $updUser->execute([$reqRole, $applicantId]);
            $msgContent = "🎉 Your application for the " . strtoupper($reqRole) . " role has been approved! Your account has been upgraded.";
            $insMsg = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
            $insMsg->execute([$userId, $applicantId, $msgContent]);
            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Database error.']);
        }
        break;

    case 'reject':
        if ($userRole !== 'admin') {
            echo json_encode(['success' => false, 'error' => 'Admin access required.']);
            exit;
        }
        $appId = (int)($_POST['application_id'] ?? 0);
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
            $reqRole = $app['requested_role'];
            $pdo->beginTransaction();
            $updApp = $pdo->prepare("UPDATE role_applications SET status='rejected', admin_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?");
            $updApp->execute([$adminNote, $userId, $appId]);
            $msgContent = "Your application for the " . strtoupper($reqRole) . " role was not approved. Reason: " . $adminNote;
            $insMsg = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
            $insMsg->execute([$userId, $applicantId, $msgContent]);
            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Database error.']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
