<?php
/**
 * AthleteHub — Follow / Unfollow API
 * Handles: follow toggle, follower/following list queries.
 * Returns updated counts for live UI updates.
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

// ── GET: List followers or following for a user ──
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['list'])) {
    $listType = $_GET['list']; // 'followers' or 'following'
    $targetUserId = (int)($_GET['user_id'] ?? $userId);

    if ($targetUserId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user.']);
        exit;
    }

    if ($listType === 'followers') {
        $stmt = $pdo->prepare("
            SELECT u.id, u.name, u.role, u.sport, u.profile_pic
            FROM follows f
            JOIN users u ON f.follower_id = u.id
            WHERE f.following_id = ?
            ORDER BY u.name ASC
            LIMIT 50
        ");
    } elseif ($listType === 'following') {
        $stmt = $pdo->prepare("
            SELECT u.id, u.name, u.role, u.sport, u.profile_pic
            FROM follows f
            JOIN users u ON f.following_id = u.id
            WHERE f.follower_id = ?
            ORDER BY u.name ASC
            LIMIT 50
        ");
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid list type.']);
        exit;
    }

    $stmt->execute([$targetUserId]);
    $users = $stmt->fetchAll();

    echo json_encode(['success' => true, 'users' => $users]);
    exit;
}

// ── POST: Follow / Unfollow toggle ──
$input = json_decode(file_get_contents('php://input'), true);
$followingId = (int)($input['following_id'] ?? 0);

if (!$followingId || $followingId === $userId) {
    echo json_encode(['success' => false, 'message' => 'Invalid user.']);
    exit;
}

// Check if already following
$check = $pdo->prepare("SELECT id FROM follows WHERE follower_id = ? AND following_id = ?");
$check->execute([$userId, $followingId]);
$existing = $check->fetch();

if ($existing) {
    // Unfollow
    $del = $pdo->prepare("DELETE FROM follows WHERE follower_id = ? AND following_id = ?");
    $del->execute([$userId, $followingId]);

    // Update cached counts
    $pdo->prepare("UPDATE users SET following_count = GREATEST(following_count - 1, 0) WHERE id = ?")->execute([$userId]);
    $pdo->prepare("UPDATE users SET followers_count = GREATEST(followers_count - 1, 0) WHERE id = ?")->execute([$followingId]);

    $isFollowing = false;
} else {
    // Follow
    $ins = $pdo->prepare("INSERT INTO follows (follower_id, following_id) VALUES (?, ?)");
    $ins->execute([$userId, $followingId]);

    // Update cached counts
    $pdo->prepare("UPDATE users SET following_count = following_count + 1 WHERE id = ?")->execute([$userId]);
    $pdo->prepare("UPDATE users SET followers_count = followers_count + 1 WHERE id = ?")->execute([$followingId]);

    $isFollowing = true;
}

// Return live counts for the target user
$followerStmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE following_id = ?");
$followerStmt->execute([$followingId]);
$newFollowerCount = (int) $followerStmt->fetchColumn();

$followingStmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = ?");
$followingStmt->execute([$followingId]);
$newFollowingCount = (int) $followingStmt->fetchColumn();

echo json_encode([
    'success'         => true,
    'following'        => $isFollowing,
    'followers_count' => $newFollowerCount,
    'following_count' => $newFollowingCount
]);
