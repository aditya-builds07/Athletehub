<?php
/**
 * AthleteHub — Sports News Page
 * View full-width magazine news layout or read individual articles.
 */

session_start();
require_once '../includes/session.php';
require_once '../config/db.php';

$userId   = $_SESSION['user_id'];
$userRole = $_SESSION['role'];
$basePath = defined('BASE_URL') ? BASE_URL : '';


// ── HTML Helpers ──
function getImgClass($category) {
    $map = [
        'Athletics' => 'img-athletics',
        'Cricket'   => 'img-cricket',
        'Football'  => 'img-football',
        'Swimming'  => 'img-swimming',
        'Badminton' => 'img-badminton',
        'National'  => 'img-national',
        'IPL'       => 'img-ipl',
    ];
    return $map[$category] ?? 'img-general';
}

function getSportEmoji($category) {
    $map = [
        'Athletics' => '🏃',
        'Cricket'   => '🏏',
        'Football'  => '⚽',
        'Swimming'  => '🏊',
        'Badminton' => '🏸',
        'National'  => '🇮🇳',
        'IPL'       => '🏏',
    ];
    return $map[$category] ?? '📰';
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return floor($diff / 86400) . 'd ago';
}



// ── SINGLE ARTICLE VIEW ──
if (isset($_GET['article']) && is_numeric($_GET['article'])) {
    $articleId = (int)$_GET['article'];
    
    $astmt = $pdo->prepare("SELECT n.*, u.name AS author_name FROM news n JOIN users u ON n.posted_by = u.id WHERE n.id = ?");
    $astmt->execute([$articleId]);
    $article = $astmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$article) {
        header("Location: news.php");
        exit;
    }
    
    $pageTitle = htmlspecialchars($article['title']) . " — AthleteHub";
    $pageCSS   = ['news'];
    $pageJS    = ['news'];
    require_once '../includes/header.php';
    ?>
    <main class="news-wrap">
        <a href="news.php" class="back-link">← Back to News</a>
        
        <div class="glass-card article-page">
            <div class="article-hero-img <?= getImgClass($article['category'] ?? 'General') ?>">
                <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute; opacity:0.08; fill:white;">
                    <circle cx="160" cy="40" r="60"/>
                    <circle cx="20" cy="180" r="40"/>
                    <circle cx="90%" cy="120" r="80"/>
                </svg>
                <div class="hero-emoji"><?= getSportEmoji($article['category'] ?? 'General') ?></div>
                <div class="big-overlay"></div>
                <div class="hero-overlay-content">
                    <span class="big-cat"><?= strtoupper(htmlspecialchars($article['category'] ?? 'General')) ?></span>
                    <h1 class="hero-title"><?= htmlspecialchars($article['title']) ?></h1>
                </div>
            </div>
            
            <div class="article-body">
                <div class="meta-row">
                    <div class="avatar-circle avatar-xs">
                        <span class="avatar-initials" style="font-size:12px;"><?= strtoupper(substr($article['author_name'], 0, 1)) ?></span>
                    </div>
                    <span class="author-name">By <?= htmlspecialchars($article['author_name']) ?></span>
                    <span class="meta-dot">·</span>
                    <span class="meta-date"><?= date('M d, Y', strtotime($article['created_at'])) ?></span>
                    <span class="meta-dot">·</span>
                    <span class="cat-pill inline-pill"><?= htmlspecialchars($article['category'] ?? 'General') ?></span>
                </div>
                
                <hr class="art-divider">
                
                <div class="art-content">
                    <?= nl2br(htmlspecialchars($article['content'])) ?>
                </div>
                
                <div class="share-row">
                    <button class="btn btn-outline btn-sm btn-share" data-url="<?= htmlspecialchars('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') ?>">
                        ↗ Share this article
                    </button>
                    <a href="news.php" class="more-news-link">← More News</a>
                </div>
            </div>
        </div>
    </main>
    <?php require_once '../includes/footer.php'; ?>
    <?php exit;
}

// ── LISTING VIEW LOGIC ──
$search   = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');

// Categories for pills
$categories = ['Athletics', 'Cricket', 'Football', 'Swimming', 'Badminton', 'National', 'IPL'];

// Nav badge unread simulation
$unreadCount = 2; // Fixed as per foundation

// Build Listing Page
$pageTitle = 'Sports News — AthleteHub';
$pageCSS   = ['news'];
$pageJS    = ['news'];
require_once '../includes/header.php';
?>

<main class="news-wrap">

    <!-- ━━━━━━━━ SECTION 1 — HERO BAR ━━━━━━━━ -->
    <div class="glass-card news-hero">
        <div class="hero-left">
            <h1 class="hero-title">📰 Sports News</h1>
            <p class="hero-sub">Latest updates from the world of sports</p>
        </div>
        
        <form method="GET" action="news.php" class="hero-right" id="searchForm">
            <?php if ($category): ?>
                <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
            <?php endif; ?>
            <input type="text" name="search" class="glass-input" style="flex: 1; min-width: 250px;"
                   placeholder="Search articles..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-primary btn-sm">Search</button>
        </form>
    </div>

    <!-- ━━━━━━━━ SECTION 2 — CATEGORY PILLS ━━━━━━━━ -->
    <div class="category-pills">
        <a href="news.php<?= $search ? '?search='.urlencode($search) : '' ?>" 
           class="cat-pill <?= $category === '' ? 'active' : '' ?>">All</a>
           
        <?php foreach($categories as $cat): 
            $href = 'news.php?category=' . urlencode($cat);
            if ($search) $href .= '&search=' . urlencode($search);
        ?>
            <a href="<?= $href ?>" class="cat-pill <?= $category === $cat ? 'active' : '' ?>">
                <?= htmlspecialchars($cat) ?>
            </a>
        <?php endforeach; ?>
    </div>



    <!-- ━━━━━━━━ DYNAMIC LIVE NEWS FROM API ━━━━━━━━ -->
    <div id="liveNewsSection" style="margin-bottom: 2rem;" class="glass-card">
        <div class="list-header mb-3 pb-2" style="border-bottom: 1px solid rgba(0,0,0,0.1);">
            <span class="lh-title text-primary"><span style="color:var(--danger)">🔥</span> LIVE SPORTS UPDATES</span>
            <span id="liveNewsError" class="text-danger text-sm" style="display:none; font-weight: 600;">Failed to load live news.</span>
        </div>
        <div class="list-grid" id="liveNewsCards">
            <!-- Loading Indicator -->
            <div id="liveNewsLoading" style="grid-column: 1 / -1; padding: 30px; text-align: center; color: var(--text-muted);">
                Fetching latest headlines from Times of India...
            </div>
            <!-- Fetched articles injected here via JavaScript -->
        </div>
    </div>

    <!-- Static database news section removed per request -->

</main>



<?php require_once '../includes/footer.php'; ?>
