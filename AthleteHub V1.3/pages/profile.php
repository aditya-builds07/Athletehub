<?php
/**
 * AthleteHub — Athlete Profile Page
 * Displays user profile with stats, posts, achievements, and connections.
 */

session_start();
require_once '../includes/session.php';
require_once '../config/db.php';

$loggedInUserId = $_SESSION['user_id'];
$basePath = defined('BASE_URL') ? BASE_URL : '';


// ── Handle AJAX Verification Application (POST) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'apply_verification') {
        $reason = trim($input['reason'] ?? '');
        if (strlen($reason) < 50) {
            echo json_encode(['success' => false, 'message' => 'Reason must be at least 50 characters.']);
            exit;
        }
        // Check no existing pending request
        $check = $pdo->prepare("SELECT id FROM verification_requests WHERE user_id = ? AND status = 'pending'");
        $check->execute([$loggedInUserId]);
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'You already have a pending verification request.']);
            exit;
        }
        $stmt = $pdo->prepare("INSERT INTO verification_requests (user_id, reason) VALUES (?, ?)");
        $stmt->execute([$loggedInUserId, $reason]);
        echo json_encode(['success' => true, 'message' => 'Application submitted! Admin will review within 2–3 days.']);
        exit;
    }
    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

// ── Get profile ID from URL ──
$profileId = isset($_GET['id']) ? (int)$_GET['id'] : $loggedInUserId;
if ($profileId <= 0) {
    header('Location: ' . $basePath . '/pages/feed.php');
    exit;
}

// ── Fetch profile user ──
$userStmt = $pdo->prepare("
    SELECT u.*,
        (SELECT COUNT(*) FROM follows WHERE following_id = u.id) AS followers_count,
        (SELECT COUNT(*) FROM follows WHERE follower_id = u.id)  AS following_count,
        (SELECT COUNT(*) FROM posts WHERE user_id = u.id)        AS post_count
    FROM users u WHERE u.id = ?
");
$userStmt->execute([$profileId]);
$user = $userStmt->fetch();

if (!$user) {
    header('Location: ' . $basePath . '/pages/feed.php');
    exit;
}

// ── Is own profile? ──
$isOwnProfile = ($profileId === $loggedInUserId);

// ── Check follow status ──
$isFollowing = false;
if (!$isOwnProfile) {
    $followCheck = $pdo->prepare("SELECT id FROM follows WHERE follower_id = ? AND following_id = ?");
    $followCheck->execute([$loggedInUserId, $profileId]);
    $isFollowing = (bool)$followCheck->fetch();
}

// ── Verification status ──
$verifyStatus = null;
$adminNote = null;
if ($isOwnProfile) {
    $verifyStmt = $pdo->prepare("SELECT status, admin_note FROM verification_requests WHERE user_id = ? ORDER BY requested_at DESC LIMIT 1");
    $verifyStmt->execute([$loggedInUserId]);
    $verifyRow = $verifyStmt->fetch();
    if ($verifyRow) {
        $verifyStatus = $verifyRow['status'];
        $adminNote = $verifyRow['admin_note'];
    }
}

// ── Fetch user posts (newest first, paginated) ──
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$postStmt = $pdo->prepare("
    SELECT p.*,
        COUNT(DISTINCT pl.id) AS like_count,
        COUNT(DISTINCT pc.id) AS comment_count
    FROM posts p
    LEFT JOIN post_likes pl ON p.id = pl.post_id
    LEFT JOIN post_comments pc ON p.id = pc.post_id
    WHERE p.user_id = ?
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
");
$postStmt->execute([$profileId, $perPage, $offset]);
$userPosts = $postStmt->fetchAll();

// ── AJAX: return posts HTML ──
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    foreach ($userPosts as $post) {
        echo renderProfilePostItem($post, $isOwnProfile, $basePath);
    }
    exit;
}

// ── Fetch connections (users this person follows) ──
$connStmt = $pdo->prepare("
    SELECT u.id, u.name, u.role, u.sport, u.profile_pic
    FROM follows f JOIN users u ON f.following_id = u.id
    WHERE f.follower_id = ? LIMIT 5
");
$connStmt->execute([$profileId]);
$connections = $connStmt->fetchAll();

// ── Role-specific 4th stat ──
$fourthStatValue = '—';
$fourthStatLabel = 'Sport';
switch ($user['role']) {
    case 'athlete':
        $fourthStatValue = $user['sport'] ?: '—';
        $fourthStatLabel = 'Sport';
        break;
    case 'coach':
        $fourthStatValue = '—';
        $fourthStatLabel = 'Athletes Coached';
        break;
    case 'recruiter':
        $recruiterCountStmt = $pdo->prepare("SELECT COUNT(*) FROM recruitment WHERE posted_by = ?");
        $recruiterCountStmt->execute([$profileId]);
        $fourthStatValue = (int)$recruiterCountStmt->fetchColumn();
        $fourthStatLabel = 'Jobs Posted';
        break;
    case 'club':
        $fourthStatValue = '—';
        $fourthStatLabel = 'Members';
        break;
    default:
        $fourthStatLabel = 'Role';
        $fourthStatValue = ucfirst($user['role']);
}

// ── Helpers ──
function getProfileInitials($name) {
    $parts = explode(' ', $name);
    $init = strtoupper(substr($parts[0], 0, 1));
    if (isset($parts[1])) $init .= strtoupper(substr($parts[1], 0, 1));
    return $init;
}

function relativeTimeProfile($datetime) {
    $now  = new DateTime();
    $then = new DateTime($datetime);
    $diff = $now->getTimestamp() - $then->getTimestamp();
    if ($diff < 60)    return 'Just now';
    if ($diff < 3600)  return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return $then->format('M j');
}

function renderProfilePostItem($post, $isOwn, $basePath) {
    $time = relativeTimeProfile($post['created_at']);
    ob_start();
    ?>
    <div class="post-item" data-post-id="<?= $post['id'] ?>">
        <div class="post-item-content">
            <p class="post-truncated"><?= nl2br(htmlspecialchars($post['content'])) ?></p>
            <button class="post-read-more" type="button">read more</button>
        </div>
        <?php if ($post['image_url']): ?>
            <div class="post-item-thumb">
                <img src="<?= htmlspecialchars($post['image_url']) ?>" alt="Post image" loading="lazy">
            </div>
        <?php endif; ?>
        <div class="post-item-meta">
            <span class="time-ago"><?= $time ?></span>
            <span class="post-item-stats">👍 <?= $post['like_count'] ?>  💬 <?= $post['comment_count'] ?></span>
            <?php if ($isOwn): ?>
                <button class="btn-delete-post-profile" data-post-id="<?= $post['id'] ?>" title="Delete post">
                    <span class="material-icons-round">delete_outline</span>
                </button>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ── Role badge config ──
$roleBadgeConfig = [
    'athlete'   => ['emoji' => '🏃', 'class' => 'role-badge--athlete'],
    'coach'     => ['emoji' => '🎯', 'class' => 'role-badge--coach'],
    'recruiter' => ['emoji' => '📋', 'class' => 'role-badge--recruiter'],
    'club'      => ['emoji' => '🏟', 'class' => 'role-badge--club'],
    'admin'     => ['emoji' => '🔧', 'class' => 'role-badge--admin'],
];
$badge = $roleBadgeConfig[$user['role']] ?? $roleBadgeConfig['athlete'];

// ── Initials ──
$initials = getProfileInitials($user['name']);
$joinedDate = date('M Y', strtotime($user['created_at']));

// ── Set page config for header/footer ──
$pageTitle = htmlspecialchars($user['name']);
$pageCSS = ['profile'];
$pageJS  = ['profile'];
require_once '../includes/header.php';
?>

<!-- ═══════════════ PROFILE PAGE ═══════════════ -->
<div class="profile-wrap">

    <!-- ━━━━━━━━ SECTION 1 — COVER BANNER ━━━━━━━━ -->
    <div class="cover-banner" id="coverBanner">
        <!-- Avatar -->
        <div class="cover-avatar" id="profileAvatar">
            <?php if ($user['profile_pic']): ?>
                <img src="<?= $basePath ?>/uploads/profile_pics/<?= htmlspecialchars($user['profile_pic']) ?>"
                     alt="<?= htmlspecialchars($user['name']) ?>">
            <?php else: ?>
                <span class="cover-avatar-initials"><?= $initials ?></span>
            <?php endif; ?>
        </div>

        <!-- Action Buttons -->
        <div class="cover-actions">
            <?php if ($isOwnProfile): ?>
                <a href="<?= $basePath ?>/pages/edit-profile.php" class="btn btn-glass btn-sm cover-action-btn" id="editProfileBtn">
                    ✏️ Edit Profile
                </a>
            <?php else: ?>
                <a href="<?= $basePath ?>/pages/messages.php?to=<?= $profileId ?>" class="btn btn-glass btn-sm cover-action-btn" id="messageBtn">
                    💬 Message
                </a>
                <?php if ($isFollowing): ?>
                    <button class="btn btn-following btn-sm cover-action-btn" data-user-id="<?= $profileId ?>" data-action="unfollow" id="followToggleBtn">
                        ✓ Following
                    </button>
                <?php else: ?>
                    <button class="btn btn-follow btn-sm cover-action-btn" data-user-id="<?= $profileId ?>" data-action="follow" id="followToggleBtn">
                        + Follow
                    </button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ━━━━━━━━ SECTION 2 — PROFILE INFO CARD ━━━━━━━━ -->
    <div class="glass-card profile-info-card" id="profileInfoCard">
        <!-- Row 1: Name + Badge + Verified -->
        <div class="profile-name-row">
            <h1 class="profile-display-name"><?= htmlspecialchars($user['name']) ?></h1>
            <span class="role-badge <?= $badge['class'] ?>">
                <?= $badge['emoji'] ?> <?= ucfirst(htmlspecialchars($user['role'])) ?>
            </span>
            <?php if ($user['is_verified']): ?>
                <span class="material-icons-round text-blue-500 ml-1" title="Verified Account" style="font-size: 24px; text-shadow: 0 0 10px rgba(59,130,246,0.5);">verified</span>
            <?php endif; ?>
        </div>

        <!-- Row 2: Role + Location -->
        <p class="profile-subtitle">
            <?= ucfirst(htmlspecialchars($user['role'])) ?>
            <?php if ($user['location']): ?>
                <span class="dot-sep">·</span> <?= htmlspecialchars($user['location']) ?>
            <?php endif; ?>
        </p>

        <!-- Row 3: Bio -->
        <?php if ($user['bio']): ?>
            <p class="profile-bio"><?= nl2br(htmlspecialchars($user['bio'])) ?></p>
        <?php endif; ?>

        <!-- Row 4: Sport Tags -->
        <?php if ($user['sport']): ?>
            <div class="profile-sport-tags">
                <?php foreach (explode(',', $user['sport']) as $s): ?>
                    <span class="sport-tag"><?= htmlspecialchars(trim($s)) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Row 5: Details Row -->
        <div class="profile-details-row">
            <?php if ($user['location']): ?>
                <span class="detail-item">📍 <?= htmlspecialchars($user['location']) ?></span>
            <?php endif; ?>
            <span class="detail-item">📅 Joined <?= $joinedDate ?></span>
        </div>

        <!-- Row 6: Verification Status Bar (own profile only) -->
        <?php if ($isOwnProfile): ?>
            <div class="verify-status-wrap" id="verifyStatusWrap">
                <?php if ($user['is_verified']): ?>
                    <div class="verify-status-bar verify-bar--verified">
                        <span class="material-icons-round">verified</span>
                        Verified Account
                    </div>
                <?php elseif ($verifyStatus === 'pending'): ?>
                    <div class="verify-status-bar verify-bar--pending">
                        <span class="material-icons-round">hourglass_top</span>
                        Verification Pending — Admin will review your request soon
                    </div>
                <?php elseif ($verifyStatus === 'rejected'): ?>
                    <div class="verify-status-bar verify-bar--rejected">
                        <span class="material-icons-round">cancel</span>
                        Verification Rejected<?php if ($adminNote): ?> — <?= htmlspecialchars($adminNote) ?><?php endif; ?>
                        <button class="btn btn-outline btn-sm btn-reapply" id="openVerifyModal" style="margin-left:auto">
                            Apply again →
                        </button>
                    </div>
                <?php else: ?>
                    <button class="btn btn-outline btn-sm btn-get-verified" id="openVerifyModal">
                        Get Verified →
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ━━━━━━━━ SECTION 3 — STATS ROW ━━━━━━━━ -->
    <div class="stats-grid" id="statsGrid">
        <div class="glass-card stat-card clickable" data-stat="followers" id="statFollowers">
            <span class="stat-number" id="followersCount"><?= $user['followers_count'] ?></span>
            <span class="stat-label">Followers</span>
        </div>
        <div class="glass-card stat-card clickable" data-stat="following" id="statFollowing">
            <span class="stat-number" id="followingCount"><?= $user['following_count'] ?></span>
            <span class="stat-label">Following</span>
        </div>
        <div class="glass-card stat-card" id="statPosts">
            <span class="stat-number" id="postCountStat"><?= $user['post_count'] ?></span>
            <span class="stat-label">Posts</span>
        </div>
        <div class="glass-card stat-card" id="statFourth">
            <span class="stat-number"><?= htmlspecialchars($fourthStatValue) ?></span>
            <span class="stat-label"><?= htmlspecialchars($fourthStatLabel) ?></span>
        </div>
    </div>

    <!-- ━━━━━━━━ SECTION 4 — MAIN CONTENT GRID ━━━━━━━━ -->
    <div class="profile-grid">

        <!-- LEFT COLUMN -->
        <div class="profile-grid-left">

            <!-- CARD A — Achievements -->
            <div class="glass-card" id="achievementsCard">
                <h3 class="card-title">🏆 Achievements</h3>
                <div class="ach-grid">
                    <div class="ach-card">
                        <div class="ach-icon-box ach-gold">🥇</div>
                        <div class="ach-info">
                            <span class="ach-title">State Champion</span>
                            <span class="ach-desc">Won gold at State Championship</span>
                            <span class="ach-year">2025</span>
                        </div>
                    </div>
                    <div class="ach-card">
                        <div class="ach-icon-box ach-blue">🏅</div>
                        <div class="ach-info">
                            <span class="ach-title">National Selection</span>
                            <span class="ach-desc">Selected for National U-21 squad</span>
                            <span class="ach-year">2025</span>
                        </div>
                    </div>
                    <div class="ach-card">
                        <div class="ach-icon-box ach-green">🎖</div>
                        <div class="ach-info">
                            <span class="ach-title">MVP Award</span>
                            <span class="ach-desc">Most Valuable Player — ISL Qualifiers</span>
                            <span class="ach-year">2026</span>
                        </div>
                    </div>
                    <div class="ach-card">
                        <div class="ach-icon-box ach-purple">⚡</div>
                        <div class="ach-info">
                            <span class="ach-title">Top Performer</span>
                            <span class="ach-desc">Ranked #3 in regional leaderboard</span>
                            <span class="ach-year">2026</span>
                        </div>
                    </div>
                </div>
                <?php if ($isOwnProfile): ?>
                    <a href="<?= $basePath ?>/pages/edit-profile.php" class="ach-edit-link">
                        Add achievements in Edit Profile →
                    </a>
                <?php endif; ?>
            </div>

            <!-- CARD B — Recent Posts -->
            <div class="glass-card" id="recentPostsCard">
                <h3 class="card-title">📝 Recent Posts (<?= $user['post_count'] ?>)</h3>
                <div class="posts-list" id="postsListContainer">
                    <?php if (empty($userPosts)): ?>
                        <div class="empty-state-mini">
                            <p class="text-muted text-sm">No posts yet.</p>
                            <?php if ($isOwnProfile): ?>
                                <a href="<?= $basePath ?>/pages/feed.php" class="btn btn-outline btn-sm mt-3">
                                    Share your first achievement →
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($userPosts as $post): ?>
                            <?= renderProfilePostItem($post, $isOwnProfile, $basePath) ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php if (count($userPosts) >= $perPage): ?>
                    <button class="btn btn-glass btn-sm w-full mt-3 btn-load-more" id="loadMorePosts"
                            data-profile-id="<?= $profileId ?>" data-page="1">
                        Load more →
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT COLUMN -->
        <div class="profile-grid-right">

            <!-- CARD C — Profile Details -->
            <div class="glass-card" id="profileDetailsCard">
                <h3 class="card-title">📋 Profile Details</h3>
                <div class="info-rows">
                    <div class="info-row">
                        <span class="info-label">Role</span>
                        <span class="role-badge <?= $badge['class'] ?>" style="font-size:11px;padding:2px 8px">
                            <?= $badge['emoji'] ?> <?= ucfirst(htmlspecialchars($user['role'])) ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Sport</span>
                        <span class="info-value"><?= htmlspecialchars($user['sport'] ?? '—') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Location</span>
                        <span class="info-value"><?= htmlspecialchars($user['location'] ?? '—') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Verified</span>
                        <?php if ($user['is_verified']): ?>
                            <span class="info-value text-success">✓ Verified</span>
                        <?php else: ?>
                            <span class="info-value text-muted">Not yet verified</span>
                        <?php endif; ?>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Member since</span>
                        <span class="info-value"><?= $joinedDate ?></span>
                    </div>
                </div>
            </div>

            <!-- CARD D — Connections -->
            <div class="glass-card" id="connectionsCard">
                <h3 class="card-title">👥 Connections</h3>
                <?php if (empty($connections)): ?>
                    <p class="text-muted text-sm">No connections yet.</p>
                <?php else: ?>
                    <div class="conn-list">
                        <?php foreach ($connections as $conn):
                            $cInit = getProfileInitials($conn['name']);
                            $cGrad = match($conn['role']) {
                                'coach'     => 'avatar-coach',
                                'recruiter' => 'avatar-recruiter',
                                'club'      => 'avatar-club',
                                'admin'     => 'avatar-admin',
                                default     => 'avatar-athlete'
                            };
                        ?>
                            <div class="conn-item">
                                <a href="<?= $basePath ?>/pages/profile.php?id=<?= $conn['id'] ?>" class="conn-item-left">
                                    <?php if ($conn['profile_pic']): ?>
                                        <div class="avatar-circle avatar-sm <?= $cGrad ?>">
                                            <img src="<?= $basePath ?>/uploads/profile_pics/<?= htmlspecialchars($conn['profile_pic']) ?>"
                                                 alt="<?= htmlspecialchars($conn['name']) ?>">
                                        </div>
                                    <?php else: ?>
                                        <div class="avatar-circle avatar-sm <?= $cGrad ?>">
                                            <span class="avatar-initials" style="font-size:10px"><?= $cInit ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="conn-info">
                                        <span class="conn-name"><?= htmlspecialchars($conn['name']) ?></span>
                                        <span class="conn-sport"><?= htmlspecialchars($conn['sport'] ?? ucfirst($conn['role'])) ?></span>
                                    </div>
                                </a>
                                <a href="<?= $basePath ?>/pages/messages.php?to=<?= $conn['id'] ?>" class="btn btn-glass btn-sm btn-pill conn-msg-btn">
                                    Message
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════ VERIFICATION MODAL ═══════════════ -->
<?php if ($isOwnProfile && !$user['is_verified'] && $verifyStatus !== 'pending'): ?>
<div class="modal-overlay" id="verifyModal">
    <div class="glass-modal" style="position:relative">
        <button class="modal-close" id="closeVerifyModal" aria-label="Close">
            <span class="material-icons-round">close</span>
        </button>
        <h3 class="modal-title">Apply for Verification</h3>
        <p class="text-muted text-sm mb-4">
            Tell us why you should be verified as a <?= htmlspecialchars($user['role']) ?> on AthleteHub.
        </p>
        <form id="verifyForm">
            <div class="form-group mb-4">
                <textarea class="glass-input glass-textarea" id="verifyReason" name="reason"
                          placeholder="Describe your sports background, achievements, or credentials..."
                          rows="5" required minlength="50"></textarea>
                <span class="form-hint" id="verifyCharCount">0 / 50 minimum characters</span>
            </div>
            <div class="flex gap-sm justify-end">
                <button type="button" class="btn btn-glass" id="cancelVerify">Cancel</button>
                <button type="submit" class="btn btn-primary" id="submitVerify">
                    <span class="material-icons-round" style="font-size:16px">verified_user</span>
                    Submit Application
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ═══════════════ FOLLOWERS / FOLLOWING MODAL ═══════════════ -->
<div class="modal-overlay" id="userListModal">
    <div class="glass-modal" style="position:relative">
        <button class="modal-close" id="closeUserListModal" aria-label="Close">
            <span class="material-icons-round">close</span>
        </button>
        <h3 class="modal-title" id="userListModalTitle">Followers</h3>
        <div class="user-list-content" id="userListContent">
            <div class="text-center text-muted text-sm p-4">Loading...</div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
