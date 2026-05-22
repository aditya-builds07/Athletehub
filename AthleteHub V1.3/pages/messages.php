<?php
/**
 * AthleteHub — Messages Page
 * Chat and inbox functionality.
 */

session_start();
require_once '../includes/session.php';
require_once '../config/db.php';

$userId   = $_SESSION['user_id'];
$userName = $_SESSION['name'];

// ── Helpers ──
function getRoleGradient($role) {
    if ($role === 'athlete') return 'linear-gradient(135deg, #0057FF, #00C2FF)';
    if ($role === 'coach') return 'linear-gradient(135deg, #0891B2, #06B6D4)';
    if ($role === 'recruiter') return 'linear-gradient(135deg, #7C3AED, #6D28D9)';
    if ($role === 'club') return 'linear-gradient(135deg, #D97706, #F59E0B)';
    return 'linear-gradient(135deg, #334155, #475569)';
}

function getRolePillColor($role) {
    if ($role === 'athlete') return 'background: rgba(0,87,255,0.1); color: #0057FF;';
    if ($role === 'coach') return 'background: rgba(8,145,178,0.1); color: #0891B2;';
    if ($role === 'recruiter') return 'background: rgba(124,58,237,0.1); color: #7C3AED;';
    if ($role === 'club') return 'background: rgba(217,119,6,0.1); color: #D97706;';
    return 'background: rgba(51,65,85,0.1); color: #334155;';
}

function timeAgo($datetime) {
    if (!$datetime) return '';
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm';
    if ($diff < 86400) return floor($diff / 3600) . 'h';
    return date('M d', $time);
}

// ── Handle POST Actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token()) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
        exit;
    }

    $action = $_POST['action'] ?? '';
    
    if ($action === 'send_message') {
        header('Content-Type: application/json');
        $receiverId = (int)($_POST['receiver_id'] ?? 0);
        $text = trim($_POST['message_text'] ?? '');
        
        if ($receiverId <= 0 || empty($text) || mb_strlen($text) > 1000) {
            echo json_encode(['success' => false, 'error' => 'Invalid message data.']);
            exit;
        }
        
        $chk = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $chk->execute([$receiverId]);
        if (!$chk->fetchColumn()) {
            echo json_encode(['success' => false, 'error' => 'Receiver not found.']);
            exit;
        }
        
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $receiverId, $text]);
        $msgId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message_id' => $msgId,
            'created_at' => date('Y-m-d H:i:s'),
            'sender_name'=> $userName
        ]);
        exit;
    }
    
    if ($action === 'delete_message') {
        header('Content-Type: application/json');
        $msgId = (int)($_POST['message_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ? AND sender_id = ?");
        $stmt->execute([$msgId, $userId]);
        echo json_encode(['success' => true]);
        exit;
    }
}

// ── Init Active Conversation ──
$activeUserId = isset($_GET['to']) && is_numeric($_GET['to']) ? (int)$_GET['to'] : null;
$searchQuery  = trim($_GET['q'] ?? '');

// ── MARK MESSAGES READ BEFORE FETCHING INBOX ──
if ($activeUserId) {
    $upd = $pdo->prepare("UPDATE messages SET is_read=1 WHERE receiver_id=? AND sender_id=? AND is_read=0");
    $upd->execute([$userId, $activeUserId]);
}

// ── Active Chat Polling (AJAX) ──
if (isset($_GET['ajax']) && $_GET['ajax'] == 1 && $activeUserId) {
    header('Content-Type: application/json');
    $lastId = (int)($_GET['last_id'] ?? 0);
    
    // Mark read
    $upd = $pdo->prepare("UPDATE messages SET is_read=1 WHERE receiver_id=? AND sender_id=? AND is_read=0");
    $upd->execute([$userId, $activeUserId]);
    
    $stmt = $pdo->prepare("
        SELECT m.*, u.name AS sender_name, u.profile_pic AS sender_pic, u.role AS sender_role
        FROM messages m JOIN users u ON m.sender_id = u.id
        WHERE ((m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?))
        AND m.id > ?
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$userId, $activeUserId, $activeUserId, $userId, $lastId]);
    $newMsgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'messages' => $newMsgs]);
    exit;
}

// ── GET INBOX DATA ──
$convStmt = $pdo->prepare("
    SELECT u.id, u.name, u.role, u.profile_pic,
      (SELECT message_text FROM messages 
       WHERE (sender_id=u.id AND receiver_id=?) OR (sender_id=? AND receiver_id=u.id) 
       ORDER BY created_at DESC LIMIT 1) AS last_message,
      (SELECT created_at FROM messages 
       WHERE (sender_id=u.id AND receiver_id=?) OR (sender_id=? AND receiver_id=u.id) 
       ORDER BY created_at DESC LIMIT 1) AS last_time,
      (SELECT COUNT(*) FROM messages 
       WHERE sender_id=u.id AND receiver_id=? AND is_read=0) AS unread_count
    FROM users u
    WHERE u.id IN (
      SELECT DISTINCT CASE WHEN sender_id=? THEN receiver_id ELSE sender_id END
      FROM messages WHERE sender_id=? OR receiver_id=?
    )
    ORDER BY last_time DESC
");
$convStmt->execute([$userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId]);
$allConversations = $convStmt->fetchAll(PDO::FETCH_ASSOC);

$conversations = [];
foreach($allConversations as $c) {
    if ($searchQuery !== '' && stripos($c['name'], $searchQuery) === false) continue;
    $conversations[] = $c;
}

// ── GET ACTIVE CHAT DATA ──
$messages = [];
$activeUser = null;

if ($activeUserId) {
    // Fetch active user
    $auStmt = $pdo->prepare("SELECT id, name, role, profile_pic FROM users WHERE id=?");
    $auStmt->execute([$activeUserId]);
    $activeUser = $auStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($activeUser) {
        $msgStmt = $pdo->prepare("
            SELECT m.*, u.name AS sender_name, u.profile_pic AS sender_pic, u.role AS sender_role
            FROM messages m JOIN users u ON m.sender_id = u.id
            WHERE (m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?)
            ORDER BY m.created_at ASC
        ");
        $msgStmt->execute([$userId, $activeUserId, $activeUserId, $userId]);
        $messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $activeUserId = null; // invalid user
    }
}

// ── GET NEW MESSAGE MODAL USERS ──
$usersStmt = $pdo->prepare("SELECT id, name, role, sport, profile_pic FROM users WHERE id != ? ORDER BY name ASC");
$usersStmt->execute([$userId]);
$allUsers = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// ── NAV UNREAD BADGE ──
$urStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id=? AND is_read=0");
$urStmt->execute([$userId]);
$unreadCount = (int)$urStmt->fetchColumn();


$pageTitle = 'Messages — AthleteHub';
$pageCSS   = ['messages'];
$pageJS    = ['messages'];
require_once '../includes/header.php';
?>

<!-- Include Iconify explicitly as requested -->
<script src="https://code.iconify.design/iconify-icon/1.0.7/iconify-icon.min.js"></script>

<main class="msg-wrap <?= $activeUserId ? 'has-active' : 'no-active' ?>">
    <div class="msg-layout">

        <!-- ━━━━━━━━ LEFT PANEL — INBOX ━━━━━━━━ -->
        <div class="inbox">
            <div class="inbox-header">
                <div class="inbox-title">
                    <iconify-icon icon="solar:chat-round-bold-duotone" style="font-size:20px"></iconify-icon>
                    Messages
                    <?php if ($unreadCount > 0): ?>
                        <span class="unread-badge"><?= $unreadCount ?></span>
                    <?php endif; ?>
                </div>
                <button type="button" class="new-msg-btn" id="btnNewMsg" title="New message">
                    <iconify-icon icon="solar:pen-new-square-bold" style="font-size:18px"></iconify-icon>
                </button>
            </div>
            
            <form class="search-box" method="GET" action="messages.php">
                <?php if ($activeUserId): ?>
                    <input type="hidden" name="to" value="<?= $activeUserId ?>">
                <?php endif; ?>
                <iconify-icon icon="solar:magnifer-linear" style="font-size:18px; color:var(--text-muted)"></iconify-icon>
                <input type="text" name="q" placeholder="Search conversations..." value="<?= htmlspecialchars($searchQuery) ?>">
            </form>
            
            <div class="conv-list">
                <?php if (empty($conversations)): ?>
                    <div class="conv-empty">
                        <iconify-icon icon="solar:chat-round-bold-duotone" style="font-size:48px; opacity:0.1"></iconify-icon>
                        <p style="margin:12px 0 4px; font-weight:600; color:var(--text-dark)">No messages yet</p>
                        <a href="javascript:void(0)" onclick="document.getElementById('btnNewMsg').click();" style="color:var(--primary); font-size:13px; font-weight:600; text-decoration:none;">Start a conversation →</a>
                    </div>
                <?php else: ?>
                    <?php foreach($conversations as $idx => $c): 
                        $isActive = ($c['id'] == $activeUserId);
                        // V1 static online (green for first 2)
                        $isOnline = ($idx < 2);
                    ?>
                        <div class="conv-item <?= $isActive ? 'active' : '' ?>" data-user-id="<?= $c['id'] ?>">
                            <div class="conv-av">
                                <?php if ($c['profile_pic']): ?>
                                    <img src="<?= htmlspecialchars($c['profile_pic']) ?>" alt="<?= htmlspecialchars($c['name']) ?>'s avatar">
                                <?php else: ?>
                                    <div class="conv-av-initials" style="background: <?= getRoleGradient($c['role']) ?>">
                                        <?= strtoupper(substr($c['name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="online-dot" style="background: <?= $isOnline ? '#16A34A' : '#94A3B8' ?>"></div>
                            </div>
                            
                            <div class="conv-info">
                                <div class="conv-name-row">
                                    <span class="conv-name"><?= htmlspecialchars($c['name']) ?></span>
                                    <span class="role-tag" style="<?= getRolePillColor($c['role']) ?>"><?= htmlspecialchars($c['role']) ?></span>
                                    <span class="conv-time"><?= timeAgo($c['last_time']) ?></span>
                                </div>
                                <div class="conv-preview"><?= htmlspecialchars($c['last_message'] ?: 'Say hi!') ?></div>
                            </div>
                            
                            <?php if ($c['unread_count'] > 0): ?>
                                <div class="conv-unread"><?= $c['unread_count'] ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ━━━━━━━━ RIGHT PANEL — CHAT ━━━━━━━━ -->
        <div class="chat">
            
            <?php if (!$activeUserId): ?>
                <!-- CASE 1: EMPTY CHAT -->
                <div class="chat-empty">
                    <iconify-icon icon="solar:chat-round-bold-duotone" style="font-size:48px; opacity:0.3; margin-bottom:12px;"></iconify-icon>
                    <p style="font-size:14px; color:var(--text-muted); margin-bottom:8px;">Select a conversation to start chatting</p>
                    <p style="font-size:12px; color:var(--text-muted); margin-bottom:16px;">or</p>
                    <button class="btn btn-primary btn-sm" onclick="document.getElementById('btnNewMsg').click();">+ New Message</button>
                </div>
            <?php else: 
                // Determine online static for header layout
                // We'll just assume online for demo feeling
            ?>
                <!-- CASE 2: ACTIVE CHAT -->
                <div class="chat-header">
                    <div class="chat-header-left">
                        <a href="messages.php" class="back-link-mobile">
                            <iconify-icon icon="solar:arrow-left-bold" style="font-size:20px; color:var(--text-dark)"></iconify-icon>
                        </a>
                        <div class="conv-av-small">
                            <?php if ($activeUser['profile_pic']): ?>
                                <img src="<?= htmlspecialchars($activeUser['profile_pic']) ?>" alt="<?= htmlspecialchars($activeUser['name']) ?>'s avatar">
                            <?php else: ?>
                                <div class="conv-av-initials" style="background: <?= getRoleGradient($activeUser['role']) ?>">
                                    <?= strtoupper(substr($activeUser['name'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="chat-status-col">
                            <span class="chat-name"><?= htmlspecialchars($activeUser['name']) ?></span>
                            <span class="chat-status"><iconify-icon icon="solar:circle-bold" style="font-size:10px"></iconify-icon> Online now · <?= ucfirst($activeUser['role']) ?></span>
                        </div>
                    </div>
                    
                    <div class="chat-actions">
                        <a href="profile.php?id=<?= $activeUser['id'] ?>" class="icon-btn" title="View Profile">
                            <iconify-icon icon="solar:user-bold-duotone" style="font-size:20px"></iconify-icon>
                        </a>
                        <button class="icon-btn search-chat-btn" title="Search in chat">
                            <iconify-icon icon="solar:magnifer-bold" style="font-size:20px"></iconify-icon>
                        </button>
                        <div class="dropdown-wrap">
                            <button class="icon-btn" title="More options">
                                <iconify-icon icon="solar:menu-dots-bold" style="font-size:20px"></iconify-icon>
                            </button>
                            <!-- Dropdown menu (V1 static behavior handled in CSS/JS) -->
                        </div>
                    </div>
                </div>

                <div class="chat-messages" id="chatBox">
                    <?php 
                    $lastDate = '';
                    foreach($messages as $m): 
                        $sent = ($m['sender_id'] == $userId);
                        $msgDate = date('M d, Y', strtotime($m['created_at']));
                        
                        if ($msgDate !== $lastDate) {
                            $displayDate = $msgDate;
                            if ($msgDate === date('M d, Y')) $displayDate = "Today";
                            elseif ($msgDate === date('M d, Y', strtotime('-1 day'))) $displayDate = "Yesterday";
                            
                            echo '<div class="date-divider"><span>' . $displayDate . '</span></div>';
                            $lastDate = $msgDate;
                        }
                    ?>
                        <div class="msg-row <?= $sent ? 'sent' : 'received' ?>" data-msg-id="<?= $m['id'] ?>">
                            <div class="msg-av-sm">
                                <?php if ($m['sender_pic']): ?>
                                    <img src="<?= htmlspecialchars($m['sender_pic']) ?>" alt="<?= htmlspecialchars($m['sender_name']) ?>'s avatar">
                                <?php else: ?>
                                    <div class="conv-av-initials" style="background: <?= getRoleGradient($m['sender_role']) ?>">
                                        <?= strtoupper(substr($m['sender_name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="bubble <?= $sent ? 'sent' : 'received' ?>">
                                <div class="bubble-text"><?= nl2br(htmlspecialchars($m['message_text'])) ?></div>
                                <div class="bubble-time">
                                    <?= date('g:i A', strtotime($m['created_at'])) ?>
                                    <?php if ($sent): ?>
                                        <span class="read-tick">
                                            <?php if ($m['is_read']): ?>
                                                <iconify-icon icon="solar:check-read-bold" style="color:var(--primary); font-size:12px; margin-left:2px;"></iconify-icon>
                                            <?php else: ?>
                                                <iconify-icon icon="solar:check-bold" style="color:rgba(255,255,255,0.65); font-size:12px; margin-left:2px;"></iconify-icon>
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($sent): ?>
                                    <button class="btn-delete-msg" title="Delete message" data-id="<?= $m['id'] ?>">
                                        <iconify-icon icon="solar:trash-bin-bold-duotone" style="font-size:14px"></iconify-icon>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="typing-indicator" id="typingIndicator" style="display:none;">
                        <div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div>
                    </div>
                </div>

                <div class="chat-input-bar">
                    <button type="button" class="attach-btn" id="btnAttach" title="Attach file">
                        <iconify-icon icon="solar:paperclip-bold" style="font-size:22px"></iconify-icon>
                    </button>
                    <button type="button" class="attach-btn" id="btnEmojiToggle" title="Emojis">
                        <iconify-icon icon="solar:emoji-funny-square-bold-duotone" style="font-size:22px"></iconify-icon>
                    </button>
                    
                    <input type="text" id="messageInput" class="msg-input" placeholder="Type a message..." autocomplete="off">
                    <input type="hidden" id="activeReceiverId" value="<?= $activeUser['id'] ?>">
                    
                    <button type="button" class="send-btn" id="btnSend">
                        <iconify-icon icon="solar:arrow-right-bold" style="font-size:20px"></iconify-icon>
                    </button>
                    
                    <!-- EMOJI PICKER -->
                    <div class="emoji-picker glass-modal" id="emojiPicker" style="display:none;">
                        <?php 
                        $emojis = ['🏆', '🥇', '🏅', '🎯', '🏃', '💪', '👏', '🔥', '⚡', '🎉', '😊', '👍', '🤝', '💬', '📢', '🌟'];
                        foreach($emojis as $em) echo "<span class='ep-emoji'>$em</span>";
                        ?>
                    </div>
                </div>

            <?php endif; ?>
            
        </div>
    </div>
</main>

<!-- ━━━━━━━━ NEW MESSAGE MODAL ━━━━━━━━ -->
<div class="modal-overlay" id="newMsgModal">
    <div class="glass-modal" style="width: 460px; padding: 20px;">
        <div class="modal-header" style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
            <div>
                <h3 class="modal-title m-0" style="font-size:18px; margin-bottom:4px;">New Message</h3>
                <p class="text-xs text-muted">Start a conversation with an athlete, coach or recruiter</p>
            </div>
            <button class="modal-close" id="closeNewMsgModal"><span class="material-icons-round">close</span></button>
        </div>
        
        <div class="form-group mb-3 relative">
            <iconify-icon icon="solar:magnifer-linear" style="position:absolute; left:12px; top:12px; color:var(--text-muted); font-size:18px;"></iconify-icon>
            <input type="text" id="searchUserInput" class="glass-input w-full" placeholder="Search by name or sport..." style="padding-left:36px;">
        </div>
        
        <div class="users-list-modal" id="modalUsersList" style="max-height:300px; overflow-y:auto; margin:0 -20px; padding:0 20px;">
            <?php foreach($allUsers as $u): ?>
                <div class="modal-user-item" data-name="<?= strtolower($u['name']) ?>" data-sport="<?= strtolower($u['sport'] ?: '') ?>" onclick="window.location.href='messages.php?to=<?= $u['id'] ?>'">
                    <div class="conv-av" style="width:36px; height:36px; font-size:12px;">
                        <?php if ($u['profile_pic']): ?>
                            <img src="<?= htmlspecialchars($u['profile_pic']) ?>" alt="<?= htmlspecialchars($u['name']) ?>'s avatar">
                        <?php else: ?>
                            <div class="conv-av-initials" style="background: <?= getRoleGradient($u['role']) ?>">
                                <?= strtoupper(substr($u['name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div style="flex:1;">
                        <div style="font-size:13px; font-weight:700; color:var(--text-dark); margin-bottom:2px;">
                            <?= htmlspecialchars($u['name']) ?>
                        </div>
                        <div style="display:flex; gap:6px; align-items:center;">
                            <span class="role-tag" style="font-size:9px; padding:2px 6px; <?= getRolePillColor($u['role']) ?>"><?= htmlspecialchars($u['role']) ?></span>
                            <?php if ($u['sport']): ?>
                                <span style="font-size:10px; color:var(--text-muted);">&bull; <?= htmlspecialchars($u['sport']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <span style="font-size:10px; font-weight:600; color:var(--primary); background:rgba(0,87,255,0.08); padding:4px 10px; border-radius:12px;">Message →</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
    // Embed JS variables for easy access in messages.js
    window.chatConfig = {
        activeUserId: <?= $activeUserId ? $activeUserId : 'null' ?>,
        lastMsgId: <?= (!empty($messages)) ? end($messages)['id'] : 0 ?>
    };

    // ──────────────────────────────────────────
    // SYSTEM MESSAGE FORMATTER (OFFICIAL LETTER)
    // ──────────────────────────────────────────
    function formatSystemMessages() {
        document.querySelectorAll('.bubble-text').forEach(el => {
            if (el.dataset.formatted) return;
            
            let htmlText = el.innerHTML;
            let text = el.innerText || el.textContent;
            
            if (text.includes('application for the') && text.includes('role')) {
                let isApproved = text.includes('approved') && !text.includes('not approved');
                let isRejected = text.includes('not approved') || text.includes('rejected');
                
                if (isApproved || isRejected) {
                    el.dataset.formatted = "true";
                    
                    let roleMatch = text.match(/application for the (.*?) role/i);
                    let role = roleMatch ? roleMatch[1].trim() : 'Requested';
                    
                    let today = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
                    let refId = Math.floor(Math.random() * 90000) + 10000;
                    let userName = "Applicant";
                    let chatNameObj = document.querySelector('.chat-name');
                    if (chatNameObj) {
                        let cn = chatNameObj.textContent.trim();
                        if (cn.toLowerCase() !== 'admin') {
                            userName = cn;
                        }
                    }
                    
                    let bodyHtml = '';
                    if (isApproved) {
                        bodyHtml = `
                            Dear ${userName},<br><br>
                            We are pleased to inform you that your application for the <b>${role}</b> role has been successfully approved after review by the AthleteHub administration team.<br><br>
                            Your account permissions have now been updated.<br><br>
                            <b>New permissions:</b><br>
                            &bull; Browse athlete profiles<br>
                            &bull; Post recruitment opportunities<br>
                            &bull; Send recruitment offers<br>
                            &bull; Access advanced filters<br><br>
                            <b>Rules & Conditions:</b><br>
                            &bull; Maintain professional conduct.<br>
                            &bull; Follow community guidelines.<br>
                            &bull; Misuse of privileges may result in suspension.<br><br>
                            If you need assistance, contact the administration team.
                        `;
                    } else {
                        let reasonMatch = text.split(/Reason:/i);
                        let reason = reasonMatch.length > 1 ? reasonMatch[1].trim() : "Not specified.";
                        
                        bodyHtml = `
                            Dear ${userName},<br><br>
                            We regret to inform you that your application for the <b>${role}</b> role has been reviewed and was not approved at this time.<br><br>
                            <b>Reason for rejection:</b><br>
                            ${reason}<br><br>
                            Please review your application details and ensure all provided information is accurate and complete before submitting a new request.<br><br>
                            If you believe this was a mistake or need further assistance, please contact the administration team.
                        `;
                    }
                    
                    let cardHtml = `
                        <div style="background:#fff; border-radius:12px; border:1px solid #e2e8f0; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05); padding:24px; max-width:700px; color:#1e293b; font-family:sans-serif; text-align:left; line-height:1.7; width:100%; box-sizing:border-box;">
                            <div style="border-bottom:1px solid #f1f5f9; padding-bottom:16px; margin-bottom:16px;">
                                <div style="font-size:12px; text-transform:uppercase; color:#64748b; font-weight:700; letter-spacing:0.05em; margin-bottom:4px;">System Notification</div>
                                <div style="font-size:12px; color:#94a3b8; margin-bottom:12px;">AthleteHub Administration</div>
                                <div style="display:flex; justify-content:space-between; font-size:12px; color:#64748b;">
                                    <span>Date: ${today}</span>
                                    <span>Reference ID: AH-${refId}</span>
                                </div>
                            </div>
                            
                            <div style="font-size:18px; font-weight:600; color:#0f172a; margin-bottom:16px;">
                                ${role} Role Application ${isApproved ? 'Approval' : 'Rejection'}
                            </div>
                            
                            <div style="font-size:14.5px; color:#334155;">
                                ${bodyHtml}
                                <br><br>
                                <span style="color:#64748b;">Regards,<br>AthleteHub Administration Team</span>
                            </div>
                        </div>
                    `;
                    
                    el.innerHTML = cardHtml;
                    
                    let bubble = el.closest('.bubble');
                    if (bubble) {
                        bubble.style.background = 'transparent';
                        bubble.style.padding = '0';
                        bubble.style.boxShadow = 'none';
                        bubble.style.border = 'none';
                        bubble.style.maxWidth = '100%';
                        
                        // Hide original timestamps and checkmarks inside the bubble since they clash with the clean letter UI
                        let timeNode = bubble.querySelector('.bubble-time');
                        if (timeNode) timeNode.style.display = 'none';
                    }
                }
            }
        });
    }

    // Run immediately when script executes
    document.addEventListener('DOMContentLoaded', formatSystemMessages);
    
    // Also observe the chatbox for new messages to format them instantly
    document.addEventListener('DOMContentLoaded', () => {
        const chatBox = document.getElementById('chatBox');
        if (chatBox) {
            const observer = new MutationObserver(formatSystemMessages);
            observer.observe(chatBox, { childList: true, subtree: true });
        }
    });
</script>

<style>
    /* Messages page — lock viewport & hide footer (layout handled in messages.css) */
    body, html { 
        overflow: hidden !important;
    }
    .site-footer { 
        display: none !important;
    }
</style>

<?php require_once '../includes/footer.php'; ?>
