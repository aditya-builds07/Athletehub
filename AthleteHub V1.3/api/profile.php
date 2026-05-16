<?php
session_start();
require_once '../includes/session.php';
require_once '../config/db.php';

$userId = $_SESSION['user_id'];

// CSRF validation for all POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validate_csrf_token()) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// ── GET: Fetch profile data ──
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $targetId = isset($_GET['id']) ? (int)$_GET['id'] : $userId;
    
    $stmt = $pdo->prepare("
        SELECT id, name, email, role, sport, location, bio, profile_pic, is_verified, followers_count, following_count, created_at
        FROM users WHERE id = ?
    ");
    $stmt->execute([$targetId]);
    $user = $stmt->fetch();

    if ($user) {
        // Add total posts count
        $postCountStmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ?");
        $postCountStmt->execute([$targetId]);
        $user['post_count'] = (int)$postCountStmt->fetchColumn();
        
        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);

    if (($input['action'] ?? '') === 'update_profile') {
        $name = trim($input['name'] ?? '');
        $sport = trim($input['sport'] ?? '');
        $location = trim($input['location'] ?? '');
        $bio = trim($input['bio'] ?? '');
        $profilePicData = $input['profile_pic'] ?? null;

        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Name cannot be empty.']);
            exit;
        }

        // Handle avatar base64
        $avatarQueryAddon = "";
        $queryParams = [$name, $sport, $location, $bio];

        if (!empty($profilePicData) && preg_match('/^data:image\/([\w+]+);base64,/', $profilePicData, $type)) {
            $data = substr($profilePicData, strpos($profilePicData, ',') + 1);
            $type = strtolower($type[1]);
            
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($type, $allowedTypes)) {
                $dataDecode = base64_decode($data);
                if ($dataDecode !== false) {
                    // ── Security: File size limit (2MB max) ──
                    if (strlen($dataDecode) > 2 * 1024 * 1024) {
                        echo json_encode(['success' => false, 'message' => 'Image too large (max 2MB).']);
                        exit;
                    }

                    // ── Security: Validate & re-process through GD to strip embedded code ──
                    $srcImg = @imagecreatefromstring($dataDecode);
                    if ($srcImg === false) {
                        echo json_encode(['success' => false, 'message' => 'Invalid image data.']);
                        exit;
                    }

                    if (!is_dir('../uploads/profile_pics')) {
                        mkdir('../uploads/profile_pics', 0755, true);
                    }

                    // Secure random filename to prevent prediction
                    $fileName = 'avatar_' . $userId . '_' . bin2hex(random_bytes(8)) . '.jpg';
                    $savePath = '../uploads/profile_pics/' . $fileName;

                    // Always save as JPEG after GD re-processing (strips any embedded payloads)
                    imagejpeg($srcImg, $savePath, 85);
                    imagedestroy($srcImg);
                    
                    $avatarQueryAddon = ", profile_pic = ?";
                    $queryParams[] = $fileName;
                }
            }
        }

        $queryParams[] = $userId;

        $stmt = $pdo->prepare("
            UPDATE users 
            SET name = ?, sport = ?, location = ?, bio = ? {$avatarQueryAddon}
            WHERE id = ?
        ");
        
        if ($stmt->execute($queryParams)) {
            // Update the user's active session data so headers and sidebars reflect changes instantly!
            $_SESSION['name'] = $name;
            if (!empty($avatarQueryAddon)) {
                $_SESSION['profile_pic'] = $fileName;
            }
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error.']);
        }
        exit;
    }
}
echo json_encode(['success' => false, 'message' => 'Invalid request.']);
