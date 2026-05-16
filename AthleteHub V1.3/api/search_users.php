<?php
/**
 * AthleteHub — Search Users API
 */

session_start();
require_once '../config/db.php';
require_once '../includes/session.php';

header('Content-Type: application/json');

// Return 401 if not logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

// 1. Sanitize & Whitelist Inputs
$search = trim($_GET['q'] ?? '');
$role   = $_GET['role'] ?? 'all';
$sort   = $_GET['sort'] ?? 'newest';
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * 20;

$allowed_roles = ['all', 'athlete', 'coach', 'recruiter', 'club'];
$allowed_sorts = ['newest', 'name_asc', 'most_followed'];

if (!in_array($role, $allowed_roles, true)) $role = 'all';
if (!in_array($sort, $allowed_sorts, true)) $sort = 'newest';

// 2. Build Query Dynamically
// Note: `is_active` was not found in schema. Assuming `role != 'admin'` only.
$whereClauses = ["role != 'admin'"];
$params = [];

if ($role !== 'all') {
    $whereClauses[] = "role = ?";
    $params[] = $role;
}

if ($search !== '') {
    $whereClauses[] = "(name LIKE ? OR REPLACE(LOWER(name), ' ', '_') LIKE ? OR sport LIKE ? OR location LIKE ?)";
    $search_param = '%' . $search . '%';
    array_push($params, $search_param, $search_param, $search_param, $search_param);
}

$whereSql = implode(' AND ', $whereClauses);

// 3. Build Sort Order
$orderSql = "ORDER BY created_at DESC";
if ($sort === 'name_asc') {
    $orderSql = "ORDER BY name ASC";
} elseif ($sort === 'most_followed') {
    $orderSql = "ORDER BY followers_count DESC, created_at DESC";
}

try {
    // 4. Count Total for Pagination
    $countSql = "SELECT COUNT(*) FROM users WHERE $whereSql";
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetchColumn();
    $pages = max(1, ceil($total / 20));

    // 5. Fetch Users
    // Aliasing columns to match JSON API requirements
    $sql = "SELECT 
                id, 
                name AS full_name, 
                REPLACE(LOWER(name), ' ', '_') AS username, 
                role, 
                sport, 
                location, 
                profile_pic AS avatar, 
                is_verified, 
                created_at 
            FROM users 
            WHERE $whereSql 
            $orderSql 
            LIMIT 20 OFFSET ?";
            
    $stmt = $pdo->prepare($sql);
    
    // Bind all params explicitly because LIMIT/OFFSET must be integers in some PDO configurations
    $paramIndex = 1;
    foreach ($params as $val) {
        $stmt->bindValue($paramIndex++, $val, PDO::PARAM_STR);
    }
    $stmt->bindValue($paramIndex, $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $users = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'users'   => $users,
        'total'   => $total,
        'page'    => $page,
        'pages'   => $pages
    ]);

} catch (PDOException $e) {
    error_log('[Search Users API Error] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database query failed.']);
}
