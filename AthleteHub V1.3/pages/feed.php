<?php
/**
 * AthleteHub — Home Feed
 * Social feed with posts, sidebars, and real-time interactions.
 */

session_start();
require_once '../includes/session.php';
require_once '../config/db.php';

// ── Current user info ──
$userId   = $_SESSION['user_id'];
$userRole = $_SESSION['role'];
$userName = $_SESSION['name'];
$profilePic = $_SESSION['profile_pic'] ?? '';
$basePath = defined('BASE_URL') ? BASE_URL : '';


// Generate initials
$nameParts = explode(' ', $userName);
$initials  = strtoupper(substr($nameParts[0], 0, 1));
if (isset($nameParts[1])) {
    $initials .= strtoupper(substr($nameParts[1], 0, 1));
}

// ── Fetch feed posts (newest first, max 20) ──
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$postStmt = $pdo->prepare("
    SELECT p.id, p.content, p.image_url, p.created_at,
           u.id AS author_id, u.name, u.role, u.sport, u.location,
           u.profile_pic, u.is_verified,
           COUNT(DISTINCT pl.id) AS like_count,
           COUNT(DISTINCT pc.id) AS comment_count,
           MAX(CASE WHEN pl.user_id = ? THEN 1 ELSE 0 END) AS user_liked
    FROM posts p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN post_likes pl ON p.id = pl.post_id
    LEFT JOIN post_comments pc ON p.id = pc.post_id
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
");
$postStmt->execute([$userId, $perPage, $offset]);
$posts = $postStmt->fetchAll();

// ── AJAX infinite scroll: return partial HTML ──
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if (isset($_GET['single_post_id'])) {
        $singlePostId = (int)$_GET['single_post_id'];
        $singlePostStmt = $pdo->prepare("
            SELECT p.id, p.content, p.image_url, p.created_at,
                   u.id AS author_id, u.name, u.role, u.sport, u.location,
                   u.profile_pic, u.is_verified,
                   COUNT(DISTINCT pl.id) AS like_count,
                   COUNT(DISTINCT pc.id) AS comment_count,
                   MAX(CASE WHEN pl.user_id = ? THEN 1 ELSE 0 END) AS user_liked
            FROM posts p
            JOIN users u ON p.user_id = u.id
            LEFT JOIN post_likes pl ON p.id = pl.post_id
            LEFT JOIN post_comments pc ON p.id = pc.post_id
            WHERE p.id = ?
            GROUP BY p.id
        ");
        $singlePostStmt->execute([$userId, $singlePostId]);
        if ($singlePost = $singlePostStmt->fetch()) {
            echo renderPostCard($singlePost, $userId, $basePath);
        }
    } else {
        foreach ($posts as $post) {
            echo renderPostCard($post, $userId, $basePath);
        }
    }
    exit;
}

// ── Sidebar: Upcoming tournaments ──
$tournStmt = $pdo->query("
    SELECT id, name, sport, location, start_date, status
    FROM tournaments
    WHERE status IN ('open','upcoming')
    ORDER BY start_date ASC
    LIMIT 3
");
$tournaments = $tournStmt->fetchAll();

// ── Sidebar: Latest news ──
$newsStmt = $pdo->query("
    SELECT id, title, created_at
    FROM news
    ORDER BY created_at DESC
    LIMIT 3
");
$news = $newsStmt->fetchAll();

// ── Sidebar: Suggested users to follow ──
$suggestStmt = $pdo->prepare("
    SELECT u.id, u.name, u.role, u.sport, u.profile_pic
    FROM users u
    WHERE u.id != ?
      AND u.role IN ('athlete','coach')
      AND u.id NOT IN (
          SELECT following_id FROM follows WHERE follower_id = ?
      )
    ORDER BY RAND()
    LIMIT 3
");
$suggestStmt->execute([$userId, $userId]);
$suggestions = $suggestStmt->fetchAll();

// ── Profile sidebar stats ──
$followerStmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE following_id = ?");
$followerStmt->execute([$userId]);
$followerCount = (int) $followerStmt->fetchColumn();

$followingStmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = ?");
$followingStmt->execute([$userId]);
$followingCount = (int) $followingStmt->fetchColumn();

$postCountStmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ?");
$postCountStmt->execute([$userId]);
$postCount = (int) $postCountStmt->fetchColumn();

// ── Messages count ──
$unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$unreadStmt->execute([$userId]);
$unreadCount = (int) $unreadStmt->fetchColumn();

$userSport    = $_SESSION['sport']    ?? '';
$userLocation = $_SESSION['location'] ?? '';

// ── Helper: generate avatar gradient class by role ──
function avatarGradient($role) {
    switch ($role) {
        case 'coach':     return 'avatar-coach';
        case 'recruiter': return 'avatar-recruiter';
        case 'club':      return 'avatar-club';
        case 'admin':     return 'avatar-admin';
        default:          return 'avatar-athlete';
    }
}

// ── Helper: render initials from name ──
function getInitials($name) {
    $parts = explode(' ', $name);
    $init = strtoupper(substr($parts[0], 0, 1));
    if (isset($parts[1])) $init .= strtoupper(substr($parts[1], 0, 1));
    return $init;
}

// ── Helper: relative time ──
function relativeTime($datetime) {
    $now  = new DateTime();
    $then = new DateTime($datetime);
    $diff = $now->getTimestamp() - $then->getTimestamp();

    if ($diff < 60)    return 'Just now';
    if ($diff < 3600)  return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return $then->format('M j');
}

// ── Helper: render a single post card (reused for AJAX) ──
function renderPostCard($post, $userId, $basePath) {
    $authorInitials = getInitials($post['name']);
    $gradClass = avatarGradient($post['role']);
    $timeStr = relativeTime($post['created_at']);
    $liked = $post['user_liked'] ? 'liked' : '';
    $likeIcon = $post['user_liked'] ? 'favorite' : 'favorite_border';
    $isOwn = ($post['author_id'] == $userId);
    $isRecruitType = in_array($post['role'], ['club', 'recruiter']);

    ob_start();
    ?>
    <div class="glass-card post-card" data-post-id="<?= $post['id'] ?>">
        <!-- Post Header -->
        <div class="post-header">
            <div class="post-author-info">
                <?php if ($post['profile_pic']): ?>
                    <div class="avatar-circle avatar-md <?= $gradClass ?>">
                        <img src="<?= $basePath ?>/uploads/profile_pics/<?= htmlspecialchars($post['profile_pic']) ?>"
                             alt="<?= htmlspecialchars($post['name']) ?>">
                    </div>
                <?php else: ?>
                    <div class="avatar-circle avatar-md <?= $gradClass ?>">
                        <span class="avatar-initials"><?= $authorInitials ?></span>
                    </div>
                <?php endif; ?>
                <div class="post-meta">
                    <div class="post-author-row">
                        <a href="<?= $basePath ?>/pages/profile.php?id=<?= $post['author_id'] ?>" class="post-author-name">
                            <?= htmlspecialchars($post['name']) ?>
                        </a>
                        <?php if ($post['is_verified']): ?>
                            <span class="badge badge-verified">
                                <span class="material-icons-round" style="font-size:11px">verified</span>
                                Verified
                            </span>
                        <?php endif; ?>
                        <?php if ($isRecruitType): ?>
                            <span class="badge badge-recruit">Recruitment</span>
                        <?php endif; ?>
                    </div>
                    <div class="post-sub-meta">
                        <span class="badge badge-<?= htmlspecialchars($post['role']) ?>"><?= ucfirst(htmlspecialchars($post['role'])) ?></span>
                        <span class="time-ago"><?= $timeStr ?></span>
                        <?php if ($post['sport']): ?>
                            <span class="post-sport-tag"><?= htmlspecialchars($post['sport']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php if ($isOwn): ?>
            <div class="post-menu-wrapper">
                <button class="btn-icon post-menu-btn" aria-label="Post options">
                    <span class="material-icons-round">more_horiz</span>
                </button>
                <div class="post-dropdown">
                    <button class="post-dropdown-item btn-delete-post" data-post-id="<?= $post['id'] ?>">
                        <span class="material-icons-round">delete_outline</span>
                        Delete Post
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Post Content -->
        <div class="post-body">
            <p class="post-text"><?= nl2br(htmlspecialchars($post['content'])) ?></p>
            <?php if ($post['image_url']): ?>
                <div class="post-image-wrapper">
                    <img src="<?= htmlspecialchars($post['image_url']) ?>" alt="Post image" class="post-image" loading="lazy">
                </div>
            <?php endif; ?>
        </div>

        <!-- Post Stats -->
        <?php if ($post['like_count'] > 0 || $post['comment_count'] > 0): ?>
        <div class="post-stats-bar">
            <?php if ($post['like_count'] > 0): ?>
                <span class="post-stat"><span class="material-icons-round" style="font-size:14px;color:var(--accent)">favorite</span> <?= $post['like_count'] ?></span>
            <?php endif; ?>
            <?php if ($post['comment_count'] > 0): ?>
                <span class="post-stat"><?= $post['comment_count'] ?> comment<?= $post['comment_count'] > 1 ? 's' : '' ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Post Actions -->
        <div class="post-actions">
            <button class="post-action-btn btn-like <?= $liked ?>" data-post-id="<?= $post['id'] ?>">
                <span class="material-icons-round like-icon"><?= $likeIcon ?></span>
                <span class="like-label"><?= $post['user_liked'] ? 'Liked' : 'Like' ?></span>
            </button>
            <button class="post-action-btn btn-comment" data-post-id="<?= $post['id'] ?>">
                <span class="material-icons-round">chat_bubble_outline</span>
                <span>Comment</span>
            </button>
            <button class="post-action-btn btn-share">
                <span class="material-icons-round">share</span>
                <span>Share</span>
            </button>
        </div>

        <!-- Comments Section (hidden by default) -->
        <div class="comment-section" id="comments-<?= $post['id'] ?>" data-loaded="false">
            <div class="comments-list"></div>
            <div class="comment-input-row">
                <div class="avatar-circle avatar-sm">
                    <span class="avatar-initials" style="font-size:10px"><?= getInitials($_SESSION['name'] ?? 'U') ?></span>
                </div>
                <form class="comment-form" data-post-id="<?= $post['id'] ?>">
                    <input type="text" class="glass-input comment-input" placeholder="Write a comment..." required>
                    <button type="submit" class="btn-send-comment" aria-label="Send comment">
                        <span class="material-icons-round">send</span>
                    </button>
                </form>
            </div>
        </div>

        <?php if ($isRecruitType): ?>
        <!-- Apply Button for recruitment posts -->
        <div class="post-apply-row">
            <button class="btn btn-primary btn-apply w-full" data-post-id="<?= $post['id'] ?>">
                Apply Now <span class="material-icons-round" style="font-size:16px">arrow_forward</span>
            </button>
        </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

// ── Set page title & extra CSS for header ──
$pageTitle = 'Feed';
$pageCSS = ['feed'];
$pageJS  = ['feed'];
require_once '../includes/header.php';
?>

<!-- ═══════════════ FEED LAYOUT ═══════════════ -->
<div class="feed-layout">

    <!-- ━━━━━━━━━━━━━━━━━━━ LEFT SIDEBAR ━━━━━━━━━━━━━━━━━━━ -->
    <aside class="feed-sidebar feed-sidebar-left">

        <!-- Profile Summary Card -->
        <div class="glass-card sidebar-profile-card">
            <div class="profile-banner"></div>
            <div class="profile-avatar-wrapper">
                <?php if ($profilePic): ?>
                    <div class="avatar-circle avatar-lg profile-main-avatar">
                        <img src="<?= $basePath ?>/uploads/profile_pics/<?= htmlspecialchars($profilePic) ?>"
                             alt="<?= htmlspecialchars($userName) ?>">
                    </div>
                <?php else: ?>
                    <div class="avatar-circle avatar-lg profile-main-avatar">
                        <span class="avatar-initials"><?= $initials ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="profile-info">
                <h4 class="profile-name"><?= htmlspecialchars($userName) ?></h4>
                <p class="profile-role-location">
                    <?= ucfirst(htmlspecialchars($userRole)) ?>
                    <?php if ($userLocation): ?>
                        <span class="dot-sep">·</span> <?= htmlspecialchars($userLocation) ?>
                    <?php endif; ?>
                </p>
                <?php if ($userSport): ?>
                    <div class="profile-sport-tags">
                        <?php foreach (explode(',', $userSport) as $s): ?>
                            <span class="sport-pill"><?= htmlspecialchars(trim($s)) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="profile-stats-row">
                <div class="profile-stat">
                    <span class="stat-num"><?= $followerCount ?></span>
                    <span class="stat-label">Followers</span>
                </div>
                <div class="profile-stat">
                    <span class="stat-num"><?= $followingCount ?></span>
                    <span class="stat-label">Following</span>
                </div>
                <div class="profile-stat">
                    <span class="stat-num"><?= $postCount ?></span>
                    <span class="stat-label">Posts</span>
                </div>
            </div>
            <a href="<?= $basePath ?>/pages/profile.php?id=<?= $userId ?>" class="btn btn-glass btn-sm w-full view-profile-link">
                <span class="material-icons-round" style="font-size:16px">person</span>
                View Profile
            </a>
        </div>

        <!-- Navigation Menu Card -->
        <div class="glass-card sidebar-nav-card">
            <nav class="sidebar-nav">
                <a href="<?= $basePath ?>/pages/feed.php" class="sidebar-nav-link active" id="sideNavFeed">
                    <span class="nav-emoji">🏠</span> Feed
                </a>
                <a href="<?= $basePath ?>/pages/profile.php?id=<?= $userId ?>" class="sidebar-nav-link" id="sideNavProfile">
                    <span class="nav-emoji">👤</span> My Profile
                </a>
                <a href="<?= $basePath ?>/pages/recruitment.php" class="sidebar-nav-link" id="sideNavRecruit">
                    <span class="nav-emoji">💼</span> Recruitments
                </a>
                <a href="<?= $basePath ?>/pages/tournaments.php" class="sidebar-nav-link" id="sideNavTournaments">
                    <span class="nav-emoji">🏆</span> Tournaments
                </a>
                <a href="<?= $basePath ?>/pages/messages.php" class="sidebar-nav-link" id="sideNavMessages">
                    <span class="nav-emoji">💬</span> Messages
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge-count sidebar-badge"><?= $unreadCount ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?= $basePath ?>/pages/news.php" class="sidebar-nav-link" id="sideNavNews">
                    <span class="nav-emoji">📰</span> News
                </a>
                <?php if ($userRole === 'admin'): ?>
                <a href="<?= $basePath ?>/admin/dashboard.php" class="sidebar-nav-link" id="sideNavAdmin">
                    <span class="nav-emoji">🔧</span> Admin Panel
                </a>
                <?php endif; ?>
            </nav>
        </div>
    </aside>

    <!-- ━━━━━━━━━━━━━━━━━━━ CENTER FEED ━━━━━━━━━━━━━━━━━━━ -->
    <section class="feed-center">

        <!-- Create Post Bar -->
        <div class="glass-card create-post-bar" id="createPostBar">
            <?php if ($profilePic): ?>
                <div class="avatar-circle avatar-md">
                    <img src="<?= $basePath ?>/uploads/profile_pics/<?= htmlspecialchars($profilePic) ?>"
                         alt="<?= htmlspecialchars($userName) ?>">
                </div>
            <?php else: ?>
                <div class="avatar-circle avatar-md">
                    <span class="avatar-initials"><?= $initials ?></span>
                </div>
            <?php endif; ?>
            <div class="create-post-trigger" id="openCreateModal">
                Share your achievement or update, <?= htmlspecialchars($userName) ?>...
            </div>
            <div class="create-post-actions">
                <button class="btn-icon create-photo-btn" id="openCreateModalPhoto" aria-label="Add photo">
                    <span class="material-icons-round">photo_library</span>
                </button>
                <button class="btn btn-primary btn-sm" id="openCreateModalBtn">Post</button>
            </div>
        </div>

        <!-- Feed Posts -->
        <div class="feed-posts" id="feedPosts">
            <?php if (empty($posts)): ?>
                <div class="glass-card empty-state">
                    <span class="material-icons-round empty-icon">dynamic_feed</span>
                    <h3>No posts yet</h3>
                    <p class="text-muted">Be the first to share an achievement or update!</p>
                    <button class="btn btn-primary mt-4" id="emptyStatePost">
                        <span class="material-icons-round">edit</span> Create Post
                    </button>
                </div>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <?= renderPostCard($post, $userId, $basePath) ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Loading spinner for infinite scroll -->
        <div class="feed-loader" id="feedLoader" style="display:none">
            <div class="loader-dots">
                <span></span><span></span><span></span>
            </div>
        </div>
    </section>

    <!-- ━━━━━━━━━━━━━━━━━━━ RIGHT SIDEBAR ━━━━━━━━━━━━━━━━━━━ -->
    <aside class="feed-sidebar feed-sidebar-right">

        <!-- Upcoming Tournaments -->
        <div class="glass-card widget-card">
            <h5 class="widget-title">
                <span>📅</span> Upcoming Tournaments
            </h5>
            <?php if (empty($tournaments)): ?>
                <p class="text-muted text-sm">No upcoming tournaments.</p>
            <?php else: ?>
                <div class="widget-list">
                    <?php foreach ($tournaments as $t): ?>
                        <?php
                            $tDate = new DateTime($t['start_date']);
                            $statusColor = $t['status'] === 'open' ? 'open' : 'upcoming';
                        ?>
                        <div class="widget-item tournament-item">
                            <div class="date-box date-box-<?= $statusColor ?>">
                                <span class="date-day"><?= $tDate->format('d') ?></span>
                                <span class="date-month"><?= strtoupper($tDate->format('M')) ?></span>
                            </div>
                            <div class="widget-item-info">
                                <span class="widget-item-title"><?= htmlspecialchars($t['name']) ?></span>
                                <span class="widget-item-sub"><?= htmlspecialchars($t['sport']) ?> · <?= htmlspecialchars($t['location']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <a href="<?= $basePath ?>/pages/tournaments.php" class="widget-view-all">
                View All <span class="material-icons-round" style="font-size:14px">arrow_forward</span>
            </a>
        </div>

        <!-- Athletes to Follow -->
        <div class="glass-card widget-card">
            <h5 class="widget-title">
                <span>👥</span> Athletes to Follow
            </h5>
            <?php if (empty($suggestions)): ?>
                <p class="text-muted text-sm">No suggestions right now.</p>
            <?php else: ?>
                <div class="widget-list">
                    <?php foreach ($suggestions as $s): ?>
                        <?php $sInit = getInitials($s['name']); ?>
                        <div class="widget-item follow-item">
                            <div class="follow-item-left">
                                <?php if ($s['profile_pic']): ?>
                                    <div class="avatar-circle avatar-sm <?= avatarGradient($s['role']) ?>">
                                        <img src="<?= $basePath ?>/uploads/profile_pics/<?= htmlspecialchars($s['profile_pic']) ?>"
                                             alt="<?= htmlspecialchars($s['name']) ?>">
                                    </div>
                                <?php else: ?>
                                    <div class="avatar-circle avatar-sm <?= avatarGradient($s['role']) ?>">
                                        <span class="avatar-initials" style="font-size:10px"><?= $sInit ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="follow-item-info">
                                    <span class="follow-item-name"><?= htmlspecialchars($s['name']) ?></span>
                                    <span class="follow-item-sport"><?= htmlspecialchars($s['sport'] ?? ucfirst($s['role'])) ?></span>
                                </div>
                            </div>
                            <button class="btn btn-outline btn-sm btn-pill btn-follow" data-user-id="<?= $s['id'] ?>">
                                Follow
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <a href="<?= $basePath ?>/pages/feed.php?filter=discover" class="widget-view-all">
                Discover more <span class="material-icons-round" style="font-size:14px">arrow_forward</span>
            </a>
        </div>

        <!-- Sports News -->
        <div class="glass-card widget-card">
            <h5 class="widget-title">
                <span>📰</span> Sports News
            </h5>
            <?php if (empty($news)): ?>
                <p class="text-muted text-sm">No news yet.</p>
            <?php else: ?>
                <div class="widget-list">
                    <?php foreach ($news as $n): ?>
                        <a href="<?= $basePath ?>/pages/news.php" class="widget-item news-item">
                            <span class="news-headline"><?= htmlspecialchars($n['title']) ?></span>
                            <span class="time-ago"><?= relativeTime($n['created_at']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <a href="<?= $basePath ?>/pages/news.php" class="widget-view-all">
                Read all news <span class="material-icons-round" style="font-size:14px">arrow_forward</span>
            </a>
        </div>
    </aside>
</div>

<!-- ═══════════════ CREATE POST MODAL ═══════════════ -->
<div class="modal-overlay" id="createPostModal">
    <div class="glass-modal" style="position:relative">
        <button class="modal-close" id="closeCreateModal" aria-label="Close">
            <span class="material-icons-round">close</span>
        </button>
        <h3 class="modal-title">Create Post</h3>
        <p class="text-muted text-sm mb-4">What's your achievement today?</p>
        <form id="createPostForm">
            <div class="form-group mb-4">
                <textarea class="glass-input glass-textarea" id="postContent" name="content"
                          placeholder="Share something amazing..." rows="4" required></textarea>
            </div>
            <div class="form-group mb-4">
                <label class="form-label">
                    <span class="material-icons-round" style="font-size:16px;vertical-align:middle">image</span>
                    Upload Image (optional)
                </label>
                <input type="file" accept="image/*" class="glass-input" id="postImageFile">
                <div id="imagePreview" style="margin-top: 12px; display: none; text-align: center;">
                    <img id="imagePreviewImg" src="" style="max-height: 200px; max-width: 100%; border-radius: var(--radius-sm); border: 1px solid rgba(255,255,255,0.7); box-shadow: var(--shadow-sm);">
                </div>
            </div>
            <div class="flex gap-sm justify-end">
                <button type="button" class="btn btn-glass" id="cancelCreatePost">Cancel</button>
                <button type="submit" class="btn btn-primary" id="submitCreatePost">
                    <span class="material-icons-round" style="font-size:16px">send</span>
                    Post
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
