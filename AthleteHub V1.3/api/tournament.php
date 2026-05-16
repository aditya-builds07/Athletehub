<?php
/**
 * AthleteHub — Tournament API
 * Handles: register, details
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
$userRole = $_SESSION['role'];
$action   = $_GET['action'] ?? '';
$input    = json_decode(file_get_contents('php://input'), true);

switch ($action) {

    // ── CREATE TOURNAMENT (Club Only) ──
    case 'create':
        if ($userRole !== 'club') {
            echo json_encode(['success' => false, 'error' => 'Unauthorized. Only clubs can create tournaments.']);
            exit;
        }

        $name      = trim($input['name'] ?? '');
        $sport     = trim($input['sport'] ?? '');
        $location  = trim($input['location'] ?? '');
        $startDate = trim($input['start_date'] ?? '');
        $endDate   = trim($input['end_date'] ?? '');
        $prizeInfo = trim($input['prize_info'] ?? '');

        if (!$name || !$sport || !$location || !$startDate || !$endDate) {
            echo json_encode(['success' => false, 'error' => 'All required fields must be filled.']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO tournaments (name, sport, location, start_date, end_date, prize_info, status, created_by, host_user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, 'upcoming', ?, ?, NOW())");
        $stmt->execute([$name, $sport, $location, $startDate, $endDate, $prizeInfo, $userId, $userId]);

        echo json_encode([
            'success'       => true,
            'tournament_id' => (int)$pdo->lastInsertId(),
            'message'       => 'Tournament created successfully!'
        ]);
        break;


    // ── DETAILS (For Modal) ──
    case 'details':
        $tournamentId = (int)($_GET['tournament_id'] ?? 0);

        if (!$tournamentId) {
            echo json_encode(['success' => false, 'error' => 'Invalid tournament ID.']);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT name, location, start_date, end_date, prize_info, sport,
                   (SELECT COUNT(*) FROM tournament_registrations WHERE tournament_id = t.id) AS reg_count
            FROM tournaments t 
            WHERE id = ?
        ");
        $stmt->execute([$tournamentId]);
        $details = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($details) {
            echo json_encode(['success' => true, 'data' => $details]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Tournament not found.']);
        }
        break;


    // ── REGISTER ──
    case 'register':
        // Expecting POST data
        if ($input && isset($input['action']) && $input['action'] === 'register') {
            $tournamentId = (int)($input['tournament_id'] ?? 0);
        } else {
            // Fallback for form data if sent via normal POST
            $tournamentId = (int)($_POST['tournament_id'] ?? 0);
            if (!$tournamentId) {
                 $tournamentId = (int)($input['tournament_id'] ?? 0);
            }
        }

        if (!$tournamentId) {
            echo json_encode(['success' => false, 'error' => 'Invalid tournament.']);
            exit;
        }

        // Only athletes can register
        if ($userRole !== 'athlete') {
            echo json_encode(['success' => false, 'error' => 'Only athletes can register for tournaments.']);
            exit;
        }

        // Check tournament status
        $tStmt = $pdo->prepare("SELECT status FROM tournaments WHERE id = ?");
        $tStmt->execute([$tournamentId]);
        $tStatus = $tStmt->fetchColumn();

        if (!$tStatus) {
            echo json_encode(['success' => false, 'error' => 'Tournament not found.']);
            exit;
        }
        if ($tStatus !== 'open') {
            echo json_encode(['success' => false, 'error' => 'Registration is currently closed for this tournament.']);
            exit;
        }

        // Check if already registered
        $checkStmt = $pdo->prepare("SELECT id FROM tournament_registrations WHERE tournament_id = ? AND user_id = ?");
        $checkStmt->execute([$tournamentId, $userId]);
        if ($checkStmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'You are already registered for this tournament.']);
            exit;
        }

        // Insert registration
        $insStmt = $pdo->prepare("INSERT INTO tournament_registrations (tournament_id, user_id) VALUES (?, ?)");
        $insStmt->execute([$tournamentId, $userId]);

        // Get new count
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM tournament_registrations WHERE tournament_id = ?");
        $countStmt->execute([$tournamentId]);
        $newTotal = (int)$countStmt->fetchColumn();

        echo json_encode([
            'success'   => true,
            'reg_count' => $newTotal,
            'message'   => 'Successfully registered!'
        ]);
        break;

    default:
        // Handle POST request to root api/tournament.php if action is sent in Body
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($input['action']) && $input['action'] === 'register') {
            // Handled above in case 'register', but need to route correctly if action is only in body
             $tournamentId = (int)($input['tournament_id'] ?? 0);
             if (!$tournamentId) {
                 echo json_encode(['success' => false, 'error' => 'Invalid tournament.']);
                 exit;
             }

             // Only athletes can register
             if ($userRole !== 'athlete') {
                 echo json_encode(['success' => false, 'error' => 'Only athletes can register for tournaments.']);
                 exit;
             }

             // Check tournament status
             $tStmt = $pdo->prepare("SELECT status FROM tournaments WHERE id = ?");
             $tStmt->execute([$tournamentId]);
             $tStatus = $tStmt->fetchColumn();

             if (!$tStatus) {
                 echo json_encode(['success' => false, 'error' => 'Tournament not found.']);
                 exit;
             }
             if ($tStatus !== 'open') {
                 echo json_encode(['success' => false, 'error' => 'Registration is currently closed for this tournament.']);
                 exit;
             }

             // Check if already registered
             $checkStmt = $pdo->prepare("SELECT id FROM tournament_registrations WHERE tournament_id = ? AND user_id = ?");
             $checkStmt->execute([$tournamentId, $userId]);
             if ($checkStmt->fetch()) {
                 echo json_encode(['success' => false, 'error' => 'You are already registered for this tournament.']);
                 exit;
             }

             // Insert registration
             $insStmt = $pdo->prepare("INSERT INTO tournament_registrations (tournament_id, user_id) VALUES (?, ?)");
             $insStmt->execute([$tournamentId, $userId]);

             // Get new count
             $countStmt = $pdo->prepare("SELECT COUNT(*) FROM tournament_registrations WHERE tournament_id = ?");
             $countStmt->execute([$tournamentId]);
             $newTotal = (int)$countStmt->fetchColumn();

             echo json_encode([
                 'success'   => true,
                 'reg_count' => $newTotal,
                 'message'   => 'Successfully registered!'
             ]);
             break;
        }

        echo json_encode(['success' => false, 'error' => 'Invalid action.']);
        break;
}
