<?php
/**
 * AthleteHub — Post API
 * Handles: create, like, comment, get_comments, delete
 * All responses are JSON. All queries use PDO prepared statements.
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

$userId   = $_SESSION['user_id'];
$userName = $_SESSION['name'];

// Determine action from GET or POST
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// For JSON body requests
$input = json_decode(file_get_contents('php://input'), true);

switch ($action) {

    // ── CREATE POST ──
    case 'create':
        $content  = trim($input['content'] ?? '');
        $imageUrl = trim($input['image_url'] ?? '');

        if (empty($content)) {
            echo json_encode(['success' => false, 'message' => 'Post content is required.']);
            exit;
        }

        if (!empty($imageUrl) && preg_match('/^data:image\/(\w+);base64,/', $imageUrl, $type)) {
            $data = substr($imageUrl, strpos($imageUrl, ',') + 1);
            $type = strtolower($type[1]);
            
            // Basic security map
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($type, $allowed)) {
                echo json_encode(['success' => false, 'message' => 'Unsupported image format.']);
                exit;
            }
            
            $dataDecode = base64_decode($data);
            if ($dataDecode === false) {
                echo json_encode(['success' => false, 'message' => 'Failed to process image structure.']);
                exit;
            }
            
            // Initialize storage routing
            if (!is_dir('../uploads/posts')) {
                mkdir('../uploads/posts', 0777, true);
            }
            
            // Build permanent asset map
            $fileName = uniqid('post_') . '.' . $type;
            file_put_contents('../uploads/posts/' . $fileName, $dataDecode);
            
            // Save standardized file link
            $imageUrl = (defined('BASE_URL') ? BASE_URL : '') . '/uploads/posts/' . $fileName;

        }

        $stmt = $pdo->prepare("
            INSERT INTO posts (user_id, content, image_url, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([
            $userId,
            $content,
            $imageUrl ?: null
        ]);

        $postId = $pdo->lastInsertId();

        echo json_encode([
            'success'    => true,
            'post_id'    => (int)$postId,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        break;


    // ── LIKE / UNLIKE POST ──
    case 'like':
        $postId = (int)($input['post_id'] ?? 0);

        if (!$postId) {
            echo json_encode(['success' => false, 'message' => 'Invalid post ID.']);
            exit;
        }

        // Check if already liked
        $check = $pdo->prepare("SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?");
        $check->execute([$postId, $userId]);
        $existing = $check->fetch();

        if ($existing) {
            // Unlike
            $del = $pdo->prepare("DELETE FROM post_likes WHERE post_id = ? AND user_id = ?");
            $del->execute([$postId, $userId]);
            $liked = false;
        } else {
            // Like
            $ins = $pdo->prepare("INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)");
            $ins->execute([$postId, $userId]);
            $liked = true;
        }

        // Get updated like count
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM post_likes WHERE post_id = ?");
        $countStmt->execute([$postId]);
        $likeCount = (int)$countStmt->fetchColumn();

        echo json_encode([
            'success'    => true,
            'liked'      => $liked,
            'like_count' => $likeCount
        ]);
        break;


    // ── ADD COMMENT ──
    case 'comment':
        $postId     = (int)($input['post_id'] ?? 0);
        $commentText = trim($input['comment_text'] ?? '');

        if (!$postId || empty($commentText)) {
            echo json_encode(['success' => false, 'message' => 'Post ID and comment text are required.']);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO post_comments (post_id, user_id, comment_text, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$postId, $userId, $commentText]);

        $commentId = $pdo->lastInsertId();

        echo json_encode([
            'success'    => true,
            'comment_id' => (int)$commentId,
            'user_name'  => $userName,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        break;


    // ── GET COMMENTS FOR A POST ──
    case 'get_comments':
        $postId = (int)($_GET['post_id'] ?? 0);

        if (!$postId) {
            echo json_encode(['success' => false, 'message' => 'Invalid post ID.']);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT pc.id, pc.comment_text, pc.created_at,
                   u.name, u.profile_pic
            FROM post_comments pc
            JOIN users u ON pc.user_id = u.id
            WHERE pc.post_id = ?
            ORDER BY pc.created_at ASC
        ");
        $stmt->execute([$postId]);
        $comments = $stmt->fetchAll();

        echo json_encode([
            'success'  => true,
            'comments' => $comments
        ]);
        break;


    // ── DELETE POST ──
    case 'delete':
        $postId = (int)($input['post_id'] ?? 0);

        if (!$postId) {
            echo json_encode(['success' => false, 'message' => 'Invalid post ID.']);
            exit;
        }

        // Only allow deletion of own posts
        $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ? AND user_id = ?");
        $stmt->execute([$postId, $userId]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Post not found or not authorized.']);
        }
        break;


    // ── UNKNOWN ACTION ──
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        break;
}
