<?php
/**
 * AthleteHub — Livestream API
 * Handles creation, termination, and fetching of YouTube live streams.
 */

require_once '../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../includes/session.php';

header('Content-Type: application/json');

// CSRF validation for all POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validate_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'fetch':
        try {
            $stmt = $pdo->prepare("
                SELECT ls.*, u.name as host_name, t.name as tournament_name 
                FROM live_streams ls
                LEFT JOIN users u ON ls.host_user_id = u.id
                LEFT JOIN tournaments t ON ls.tournament_id = t.id
                WHERE ls.status = 'live'
                ORDER BY ls.created_at DESC
            ");
            $stmt->execute();
            $streams = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $sessionUserId = $_SESSION['user_id'] ?? null;
            foreach ($streams as &$s) {
                $s['is_host'] = ($sessionUserId && $s['host_user_id'] == $sessionUserId);
            }
            
            echo json_encode(['success' => true, 'streams' => $streams]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Database error occurred.']);
        }
        break;

    case 'create':
        // Only clubs can create streams
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'club') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unauthorized. Only clubs can go live.']);
            exit;
        }

        // Get JSON body
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            $data = $_POST; // fallback to form-data
        }

        $title = trim($data['title'] ?? '');
        $youtube_url = trim($data['youtube_url'] ?? '');
        $tournament_id = !empty($data['tournament_id']) ? (int)$data['tournament_id'] : null;
        $host_user_id = $_SESSION['user_id'];

        if (!$title || !$youtube_url) {
            echo json_encode(['success' => false, 'error' => 'Title and YouTube URL are required.']);
            exit;
        }

        // Extract YouTube Video ID
        $video_id = null;
        // Regex patterns for various YouTube formats
        $patterns = [
            '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/watch\?.*v=([^&\s]+)/i',
            '/(?:https?:\/\/)?(?:www\.)?youtu\.be\/([^?\s]+)/i',
            '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/live\/([^?\s]+)/i',
            '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/embed\/([^?\s]+)/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $youtube_url, $matches)) {
                $video_id = $matches[1];
                break;
            }
        }

        if (!$video_id) {
            echo json_encode(['success' => false, 'error' => 'Invalid YouTube URL format.']);
            exit;
        }

        try {
            // Verify tournament if provided
            if ($tournament_id) {
                $checkT = $pdo->prepare("SELECT id FROM tournaments WHERE id = ? AND created_by = ?");
                $checkT->execute([$tournament_id, $host_user_id]);
                if (!$checkT->fetch()) {
                    echo json_encode(['success' => false, 'error' => 'Invalid tournament selection.']);
                    exit;
                }
            }

            $stmt = $pdo->prepare("
                INSERT INTO live_streams (host_user_id, title, youtube_url, tournament_id, status, created_at)
                VALUES (?, ?, ?, ?, 'live', NOW())
            ");
            $stmt->execute([$host_user_id, $title, $youtube_url, $tournament_id]);
            
            echo json_encode([
                'success' => true, 
                'stream_id' => $pdo->lastInsertId(),
                'video_id' => $video_id
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Database error while creating stream.']);
        }
        break;

    case 'end':
        // Only clubs can end streams
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'club') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
            exit;
        }

        // Get JSON body
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            $data = $_POST; 
        }

        $stream_id = (int)($data['stream_id'] ?? 0);
        $host_user_id = $_SESSION['user_id'];

        if (!$stream_id) {
            echo json_encode(['success' => false, 'error' => 'Stream ID is required.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("UPDATE live_streams SET status = 'ended' WHERE id = ? AND host_user_id = ?");
            $stmt->execute([$stream_id, $host_user_id]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Stream not found or you are not the host.']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Database error while ending stream.']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action.']);
        break;
}
