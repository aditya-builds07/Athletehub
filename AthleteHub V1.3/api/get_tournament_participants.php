<?php
/**
 * AthleteHub — Get Tournament Participants API
 * Club-Only Access for Hosted Tournaments
 */

session_start();
require_once '../includes/session.php';
require_once '../config/db.php';

header('Content-Type: application/json');

// Validate Session
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId   = (int)$_SESSION['user_id'];
$userRole = $_SESSION['role'];
$tournamentId = (int)($_GET['tournament_id'] ?? 0);

// Validate Role
if ($userRole !== 'club') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!$tournamentId) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid tournament ID']);
    exit;
}

// Validate Host Ownership
$stmt = $pdo->prepare("SELECT host_user_id FROM tournaments WHERE id = ?");
$stmt->execute([$tournamentId]);
$hostId = $stmt->fetchColumn();

if ($hostId === false || (int)$hostId !== $userId) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Fetch Registered Participants
$partStmt = $pdo->prepare("
    SELECT u.id, u.name, u.role, u.sport, u.profile_pic, tr.registered_at
    FROM tournament_registrations tr
    JOIN users u ON tr.user_id = u.id
    WHERE tr.tournament_id = ?
    ORDER BY tr.registered_at DESC
");
$partStmt->execute([$tournamentId]);
$participants = $partStmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success'      => true,
    'participants' => $participants
]);
