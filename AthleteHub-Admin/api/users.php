<?php
/**
 * AthleteHub Admin — Users API
 * Handles: search, view, change_role, suspend, delete
 * All via POST with PDO prepared statements.
 */
session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    /* ── Search users (GET) ── */
    case 'search':
        $q    = '%' . ($_GET['q'] ?? '') . '%';
        $role = $_GET['role'] ?? '';

        $sql = "SELECT id, name, email, role, is_verified, profile_pic, sport, location, bio, created_at,
                       IFNULL(suspended, 0) AS suspended
                FROM users WHERE (name LIKE ? OR email LIKE ?)";
        $params = [$q, $q];

        if ($role && $role !== 'all') {
            $sql .= " AND role = ?";
            $params[] = $role;
        }

        $sql .= " ORDER BY created_at DESC LIMIT 100";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true, 'users' => $stmt->fetchAll()]);
        break;

    /* ── View single user ── */
    case 'view':
        $id   = (int) ($_POST['user_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if ($user) {
            unset($user['password_hash']);
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
        break;

    /* ── Change role ── */
    case 'change_role':
        $id      = (int) ($_POST['user_id'] ?? 0);
        $newRole = $_POST['new_role'] ?? '';
        $allowed = ['athlete', 'coach', 'recruiter', 'club', 'admin'];

        if (!in_array($newRole, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Invalid role']);
            break;
        }

        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$newRole, $id]);
        echo json_encode(['success' => true, 'message' => 'Role updated']);
        break;

    /* ── Suspend / Unsuspend ── */
    case 'suspend':
        $id    = (int) ($_POST['user_id'] ?? 0);
        $value = (int) ($_POST['value'] ?? 1);
        $stmt  = $pdo->prepare("UPDATE users SET suspended = ? WHERE id = ?");
        $stmt->execute([$value, $id]);
        $msg = $value ? 'User suspended' : 'User unsuspended';
        echo json_encode(['success' => true, 'message' => $msg]);
        break;

    /* ── Delete user ── */
    case 'delete':
        $id   = (int) ($_POST['user_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'User deleted']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
