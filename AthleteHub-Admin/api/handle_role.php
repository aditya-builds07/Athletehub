<?php
/**
 * AthleteHub Admin — Handle Role Application API  v2.0
 * ─────────────────────────────────────────────────────
 * POST endpoint: approve or reject a role application.
 *
 * Security hardening:
 *   ✔ session_start() via auth_check.php (first include)
 *   ✔ Admin guard: user_id + role = 'admin'
 *   ✔ CSRF: hash_equals on canonical csrf_token key
 *   ✔ HTTP method: POST only
 *   ✔ Input whitelist: action must be approve|reject
 *   ✔ app_id cast to (int), rejected if ≤ 0
 *   ✔ admin_note sanitised before embedding in notification
 *   ✔ requested_role whitelisted before embedding in message
 *   ✔ PDO prepared statements throughout
 *   ✔ DB transaction with rollback on failure
 *   ✔ Errors logged — never echoed
 *   ✔ Sensitive columns never returned
 *   ✔ HTTP 403 for auth/CSRF failures; 400 for input errors
 */

// ── Bootstrap: session + auth guard + CSRF token + DB ─────────────
require_once __DIR__ . '/../includes/auth_check.php';

header('Content-Type: application/json');

// ── HTTP method guard ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// ── CSRF validation ───────────────────────────────────────────────
$postedToken  = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';
if (empty($sessionToken) || !hash_equals($sessionToken, $postedToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'CSRF validation failed']);
    exit;
}

// ── Input: action whitelist ───────────────────────────────────────
$action = $_POST['action'] ?? '';
if (!in_array($action, ['approve', 'reject'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

// ── Input: app_id ─────────────────────────────────────────────────
$appId   = (int)($_POST['app_id'] ?? 0);
$adminId = (int)$_SESSION['user_id'];

if ($appId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid application ID']);
    exit;
}

// ── Allowed roles whitelist (prevent crafted DB values polluting msgs) ──
const ALLOWED_ROLES = ['club', 'recruiter', 'athlete', 'coach'];

// ── Fetch application (only safe columns) ────────────────────────
try {
    $stmt = $pdo->prepare(
        "SELECT ra.id, ra.user_id, ra.requested_role, ra.status,
                u.name AS applicant_name
         FROM   role_applications ra
         JOIN   users u ON ra.user_id = u.id
         WHERE  ra.id = ?
         LIMIT  1"
    );
    $stmt->execute([$appId]);
    $app = $stmt->fetch();
} catch (PDOException $e) {
    error_log('[handle_role.php] fetch error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'A server error occurred']);
    exit;
}

if (!$app) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Application not found']);
    exit;
}

if ($app['status'] !== 'pending') {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'Application has already been reviewed']);
    exit;
}

// Whitelist the stored requested_role before embedding in any message
$requestedRole = in_array($app['requested_role'], ALLOWED_ROLES, true)
    ? $app['requested_role']
    : 'unknown';

// ── APPROVE ───────────────────────────────────────────────────────
if ($action === 'approve') {
    try {
        $pdo->beginTransaction();

        // 1. Update application status
        $stmt = $pdo->prepare(
            "UPDATE role_applications
             SET    status = 'approved', reviewed_by = ?, reviewed_at = NOW()
             WHERE  id = ?"
        );
        $stmt->execute([$adminId, $appId]);

        // 2. Upgrade user role + set verified
        $stmt = $pdo->prepare(
            "UPDATE users SET role = ?, is_verified = 1 WHERE id = ?"
        );
        $stmt->execute([$requestedRole, $app['user_id']]);

        // 3. Build rich approval message with rules & conditions
        $rolePerms = [
            'club'      => "✅ Create & manage tournaments\n✅ Recruit athletes and coaches\n✅ Host livestreams\n✅ Post recruitment listings",
            'recruiter' => "✅ Browse and scout athlete profiles\n✅ Post recruitment opportunities\n✅ Send direct recruitment offers\n✅ Access advanced search filters",
            'athlete'   => "✅ Register for tournaments\n✅ Build your sports portfolio\n✅ Connect with coaches and clubs\n✅ Apply for recruitment listings",
            'coach'     => "✅ Create training programs\n✅ Connect with athletes and clubs\n✅ Host livestream training sessions\n✅ Post coaching availability",
        ];
        $permsText = $rolePerms[$requestedRole] ?? "✅ Access role-specific features";

        $notifMsg = "🎉 Congratulations! Your application for the "
            . ucfirst($requestedRole)
            . " role has been approved. Your account has been upgraded.\n\n"
            . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            . "🔑 YOUR NEW PERMISSIONS:\n"
            . $permsText . "\n\n"
            . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            . "📋 RULES & CONDITIONS:\n"
            . "• Your role is on a 30-day probation period. Maintain regular activity to keep your privileges.\n"
            . "• Please maintain professional conduct at all times. Violations may result in role revocation.\n"
            . "• Review our community guidelines before using your new privileges.\n"
            . "• Misuse of role privileges (spam, harassment, fake listings) will lead to immediate suspension.\n\n"
            . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            . "💬 NEED HELP?\n"
            . "If you have any questions, message the Admin directly or visit the Help Center.\n\n"
            . "Welcome aboard! 🚀";

        // Notifications table does not exist, so we skip inserting into it.

        // 4. System message (optional — guarded; skipped if sender row absent)
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO messages (sender_id, receiver_id, message_text, created_at)
             VALUES (?, ?, ?, NOW())"
        );
        $stmt->execute([$adminId, $app['user_id'], $notifMsg]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Application approved successfully']);

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('[handle_role.php] approve error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'A server error occurred. Please try again.']);
    }
    exit;
}

// ── REJECT ────────────────────────────────────────────────────────
if ($action === 'reject') {
    // admin_note is required — strip and validate length
    $adminNote = trim($_POST['admin_note'] ?? '');
    if ($adminNote === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Admin note is required for rejection']);
        exit;
    }
    // Truncate to DB column limit; strip any HTML tags before storage
    $adminNote = strip_tags(mb_substr($adminNote, 0, 1000));

    try {
        $pdo->beginTransaction();

        // 1. Update application with note
        $stmt = $pdo->prepare(
            "UPDATE role_applications
             SET    status = 'rejected', admin_note = ?, reviewed_by = ?, reviewed_at = NOW()
             WHERE  id = ?"
        );
        $stmt->execute([$adminNote, $adminId, $appId]);

        // 2. Notification (safe: note stored via PDO; role whitelisted)
        $notifMsg = 'Your application for the '
                    . ucfirst($requestedRole)
                    . ' role was not approved. Reason: ' . $adminNote;
        // Notifications table does not exist, so we skip inserting into it.

        // 3. System message (optional — guarded)
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO messages (sender_id, receiver_id, message_text, created_at)
             VALUES (?, ?, ?, NOW())"
        );
        $stmt->execute([$adminId, $app['user_id'], $notifMsg]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Application rejected successfully']);

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('[handle_role.php] reject error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'A server error occurred. Please try again.']);
    }
    exit;
}
