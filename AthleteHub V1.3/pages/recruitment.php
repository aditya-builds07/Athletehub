<?php
/**
 * AthleteHub — Recruitment Hub
 * Browse, filter, apply to jobs/scholarships/tryouts/training.
 * Recruiters & clubs can post new listings.
 */

session_start();
require_once '../includes/session.php';
require_once '../config/db.php';

$userId   = $_SESSION['user_id'];
$userRole = $_SESSION['role'];
$basePath = defined('BASE_URL') ? BASE_URL : '';


// ── Handle POST: new listing ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input  = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'post_job') {
        if ($userRole !== 'recruiter') {
            echo json_encode(['success' => false, 'error' => 'Not authorized. Only recruiters can post opportunities.']);
            exit;
        }

        $title       = trim($input['title'] ?? '');
        $jobSport    = trim($input['sport'] ?? '');
        $jobLocation = trim($input['location'] ?? '');
        $type        = trim($input['type'] ?? '');
        $description = trim($input['description'] ?? '');
        $deadline    = trim($input['deadline'] ?? '');

        $validTypes = ['job', 'scholarship', 'tryout', 'training'];

        if (!$title || !$jobSport || !$jobLocation || !$type || !$description || !$deadline) {
            echo json_encode(['success' => false, 'error' => 'All fields are required.']);
            exit;
        }
        if (!in_array($type, $validTypes)) {
            echo json_encode(['success' => false, 'error' => 'Invalid opportunity type.']);
            exit;
        }
        if (strlen($description) < 30) {
            echo json_encode(['success' => false, 'error' => 'Description must be at least 30 characters.']);
            exit;
        }
        if (strtotime($deadline) <= strtotime('today')) {
            echo json_encode(['success' => false, 'error' => 'Deadline must be a future date.']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO recruitment (posted_by, title, sport, location, type, description, deadline, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([$userId, $title, $jobSport, $jobLocation, $type, $description, $deadline]);

        echo json_encode(['success' => true, 'message' => 'Listing posted successfully!', 'listing_id' => (int)$pdo->lastInsertId()]);
        exit;
    }
    echo json_encode(['success' => false, 'error' => 'Unknown action.']);
    exit;
}

// ── Filters from GET ──
$search   = trim($_GET['search'] ?? '');
$sport    = trim($_GET['sport'] ?? '');
$location = trim($_GET['location'] ?? '');
$type     = trim($_GET['type'] ?? '');
$sort     = $_GET['sort'] ?? 'newest';
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 10;
$offset   = ($page - 1) * $perPage;

// ── Build dynamic query ──
$where  = ["r.is_active = 1"];
$params = [$userId]; // first param is for already_applied sub-query

if ($search !== '') {
    $where[]  = "(r.title LIKE ? OR r.description LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if ($sport !== '') {
    $where[]  = "r.sport = ?";
    $params[] = $sport;
}
if ($location !== '') {
    $where[]  = "r.location LIKE ?";
    $params[] = "%{$location}%";
}
if ($type !== '') {
    $where[]  = "r.type = ?";
    $params[] = $type;
}

$whereSQL = implode(' AND ', $where);
$orderSQL = $sort === 'deadline' ? 'r.deadline ASC' : 'r.id DESC';

// Count total for pagination
$countSQL = "SELECT COUNT(*) FROM recruitment r WHERE {$whereSQL}";
// Remove first param (already_applied) for count query
$countParams = array_slice($params, 1);
$countStmt = $pdo->prepare($countSQL);
$countStmt->execute($countParams);
$totalListings = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalListings / $perPage));

// Fetch listings
$sql = "
    SELECT r.*, u.name AS poster_name, u.role AS poster_role, u.profile_pic AS poster_pic,
        (SELECT COUNT(*) FROM recruitment_applications WHERE recruitment_id = r.id) AS applicant_count,
        (SELECT COUNT(*) FROM recruitment_applications WHERE recruitment_id = r.id AND user_id = ?) AS already_applied
    FROM recruitment r
    JOIN users u ON r.posted_by = u.id
    WHERE {$whereSQL}
    ORDER BY {$orderSQL}
    LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$listings = $stmt->fetchAll();

// ── Filter dropdowns ──
$sportsStmt = $pdo->query("SELECT DISTINCT sport FROM recruitment WHERE is_active = 1 ORDER BY sport");
$sports = $sportsStmt->fetchAll(PDO::FETCH_COLUMN);

$locationsStmt = $pdo->query("SELECT DISTINCT location FROM recruitment WHERE is_active = 1 AND location IS NOT NULL ORDER BY location");
$locations = $locationsStmt->fetchAll(PDO::FETCH_COLUMN);

// ── Sidebar stats ──
$openCount = (int)$pdo->query("SELECT COUNT(*) FROM recruitment WHERE is_active = 1")->fetchColumn();

$totalApps = (int)$pdo->query("SELECT COUNT(*) FROM recruitment_applications")->fetchColumn();

$newThisWeek = (int)$pdo->query("SELECT COUNT(*) FROM recruitment WHERE is_active = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

$sportsCount = (int)$pdo->query("SELECT COUNT(DISTINCT sport) FROM recruitment WHERE is_active = 1")->fetchColumn();

// ── Type counts for tabs ──
$typeCountStmt = $pdo->query("SELECT type, COUNT(*) as cnt FROM recruitment WHERE is_active = 1 GROUP BY type");
$typeCounts = ['job' => 0, 'scholarship' => 0, 'tryout' => 0, 'training' => 0];
while ($row = $typeCountStmt->fetch()) {
    $typeCounts[$row['type']] = (int)$row['cnt'];
}
$typeCountAll = array_sum($typeCounts);

// ── Helpers ──
function getRecruitInitials($name) {
    $parts = explode(' ', $name);
    $init = strtoupper(substr($parts[0], 0, 1));
    if (isset($parts[1])) $init .= strtoupper(substr($parts[1], 0, 1));
    return $init;
}

function deadlineStatus($deadline) {
    if (!$deadline) return ['label' => 'Open', 'class' => 'jbadge--open'];
    $daysLeft = (int)((strtotime($deadline) - strtotime('today')) / 86400);
    if ($daysLeft < 0) return ['label' => 'Closed', 'class' => 'jbadge--closed', 'days' => $daysLeft];
    if ($daysLeft <= 7) return ['label' => 'Closing Soon', 'class' => 'jbadge--warning', 'days' => $daysLeft];
    return ['label' => 'Open', 'class' => 'jbadge--open', 'days' => $daysLeft];
}

$typeLabels = ['job' => 'Job', 'scholarship' => 'Scholarship', 'tryout' => 'Tryout', 'training' => 'Training'];
$typeEmojis = ['job' => '💼', 'scholarship' => '🎓', 'tryout' => '🏟️', 'training' => '🏋️'];

// ── Build active filters list ──
$activeFilters = [];
if ($search !== '')   $activeFilters['search']   = "Search: \"{$search}\"";
if ($sport !== '')    $activeFilters['sport']    = "Sport: {$sport}";
if ($location !== '') $activeFilters['location'] = "Location: {$location}";
if ($type !== '')     $activeFilters['type']     = "Type: " . ($typeLabels[$type] ?? $type);

// ── Set page config ──
$pageTitle = 'Recruitment Hub';
$pageCSS = ['recruitment'];
$pageJS  = ['recruitment'];
require_once '../includes/header.php';
?>

<!-- ═══════════════ RECRUITMENT PAGE ═══════════════ -->
<div class="recruit-wrap">

    <!-- ━━━━━━━━ SECTION 1 — HERO + FILTER BAR ━━━━━━━━ -->
    <div class="glass-card hero-bar" id="heroBar">
        <div class="hero-text">
            <h1 class="hero-title">💼 Sports Recruitment Hub</h1>
            <p class="hero-sub">Discover jobs, scholarships, tryouts and training programs</p>
        </div>

        <form class="filter-row" id="filterForm" method="GET" action="">
            <input type="text" name="search" class="glass-input filter-search"
                   placeholder="Search by title, sport, club..."
                   value="<?= htmlspecialchars($search) ?>" id="filterSearch">

            <select name="sport" class="glass-input filter-select" id="filterSport">
                <option value="">All Sports</option>
                <?php foreach ($sports as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>" <?= $sport === $s ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="location" class="glass-input filter-select" id="filterLocation">
                <option value="">All Locations</option>
                <?php foreach ($locations as $loc): ?>
                    <option value="<?= htmlspecialchars($loc) ?>" <?= $location === $loc ? 'selected' : '' ?>>
                        <?= htmlspecialchars($loc) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="type" class="glass-input filter-select" id="filterType">
                <option value="">All Types</option>
                <option value="job"         <?= $type === 'job'         ? 'selected' : '' ?>>Job</option>
                <option value="scholarship" <?= $type === 'scholarship' ? 'selected' : '' ?>>Scholarship</option>
                <option value="tryout"      <?= $type === 'tryout'      ? 'selected' : '' ?>>Tryout</option>
                <option value="training"    <?= $type === 'training'    ? 'selected' : '' ?>>Training</option>
            </select>

            <select name="sort" class="glass-input filter-select" id="filterSort">
                <option value="newest"   <?= $sort === 'newest'   ? 'selected' : '' ?>>Newest First</option>
                <option value="deadline" <?= $sort === 'deadline' ? 'selected' : '' ?>>Deadline Soon</option>
            </select>

            <button type="submit" class="btn btn-primary btn-sm filter-btn">
                🔍 Search
            </button>
        </form>
    </div>

    <!-- ━━━━━━━━ SECTION 2 — MAIN GRID ━━━━━━━━ -->
    <div class="recruit-grid">

        <!-- ═══ LEFT — JOB LISTINGS ═══ -->
        <div class="recruit-left">

            <!-- Results header -->
            <div class="jobs-header">
                <span class="jobs-count" id="jobsCount">
                    <strong><?= $totalListings ?></strong> opportunit<?= $totalListings === 1 ? 'y' : 'ies' ?> found
                </span>
                <?php if (!empty($activeFilters)): ?>
                    <div class="filter-pills" id="filterPills">
                        <?php foreach ($activeFilters as $key => $label): ?>
                            <span class="filter-pill" data-filter="<?= $key ?>">
                                <?= htmlspecialchars($label) ?>
                                <button class="pill-clear" data-filter="<?= $key ?>" aria-label="Clear filter">×</button>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Type filter tabs -->
            <div class="type-tabs" id="typeTabs">
                <a href="?<?= http_build_query(array_merge($_GET, ['type' => '', 'page' => 1])) ?>"
                   class="type-tab <?= $type === '' ? 'active' : '' ?>">
                    All <span class="tab-count"><?= $typeCountAll ?></span>
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['type' => 'job', 'page' => 1])) ?>"
                   class="type-tab <?= $type === 'job' ? 'active' : '' ?>">
                    💼 Jobs <span class="tab-count"><?= $typeCounts['job'] ?></span>
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['type' => 'scholarship', 'page' => 1])) ?>"
                   class="type-tab <?= $type === 'scholarship' ? 'active' : '' ?>">
                    🎓 Scholarships <span class="tab-count"><?= $typeCounts['scholarship'] ?></span>
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['type' => 'tryout', 'page' => 1])) ?>"
                   class="type-tab <?= $type === 'tryout' ? 'active' : '' ?>">
                    🏟️ Tryouts <span class="tab-count"><?= $typeCounts['tryout'] ?></span>
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['type' => 'training', 'page' => 1])) ?>"
                   class="type-tab <?= $type === 'training' ? 'active' : '' ?>">
                    🏋️ Training <span class="tab-count"><?= $typeCounts['training'] ?></span>
                </a>
            </div>

            <!-- Listings -->
            <div class="jobs-list" id="jobsList">
                <?php if (empty($listings)): ?>
                    <div class="glass-card empty-state">
                        <span class="empty-emoji">💼</span>
                        <h3>No opportunities found</h3>
                        <p class="text-muted text-sm">Try adjusting your filters or search terms.</p>
                        <a href="<?= $basePath ?>/pages/recruitment.php" class="btn btn-outline btn-sm mt-4">
                            Clear all filters
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($listings as $job):
                        $dStatus  = deadlineStatus($job['deadline']);
                        $initials = getRecruitInitials($job['poster_name']);
                        $avatarClass = $job['poster_role'] === 'club' ? 'org-avatar--club' : 'org-avatar--recruiter';
                        $deadlinePassed = $dStatus['label'] === 'Closed';
                    ?>
                    <div class="glass-card job-card" data-id="<?= $job['id'] ?>" data-title="<?= htmlspecialchars($job['title']) ?>" data-poster="<?= htmlspecialchars($job['poster_name']) ?>">
                        <!-- Job Header -->
                        <div class="job-header">
                            <div class="job-header-left">
                                <?php if ($job['poster_pic']): ?>
                                    <div class="org-avatar <?= $avatarClass ?>">
                                        <img src="<?= $basePath ?>/uploads/profile_pics/<?= htmlspecialchars($job['poster_pic']) ?>"
                                             alt="<?= htmlspecialchars($job['poster_name']) ?>">
                                    </div>
                                <?php else: ?>
                                    <div class="org-avatar <?= $avatarClass ?>">
                                        <span class="org-initials"><?= $initials ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="job-header-info">
                                    <h4 class="job-title"><?= htmlspecialchars($job['title']) ?></h4>
                                    <p class="job-org"><?= htmlspecialchars($job['poster_name']) ?> · <?= htmlspecialchars($job['location'] ?? '') ?></p>
                                </div>
                            </div>
                            <?php if ($job['posted_by'] == $userId): ?>
                                <button class="btn-icon btn-delete-listing" data-job-id="<?= $job['id'] ?>" title="Delete listing">
                                    <span class="material-icons-round">delete_outline</span>
                                </button>
                            <?php endif; ?>
                        </div>

                        <!-- Badge Row -->
                        <div class="job-badges">
                            <span class="jbadge jbadge--<?= htmlspecialchars($job['type']) ?>">
                                <?= $typeEmojis[$job['type']] ?? '' ?> <?= $typeLabels[$job['type']] ?? ucfirst($job['type']) ?>
                            </span>
                            <span class="jbadge jbadge--sport"><?= htmlspecialchars($job['sport']) ?></span>
                            <span class="jbadge <?= $dStatus['class'] ?>"><?= $dStatus['label'] ?></span>
                        </div>

                        <!-- Description -->
                        <div class="job-desc">
                            <p class="job-desc-text" data-full="<?= htmlspecialchars($job['description']) ?>"><?= nl2br(htmlspecialchars(substr($job['description'], 0, 180))) ?><?php if (strlen($job['description']) > 180): ?><span class="desc-ellipsis">...</span><?php endif; ?></p>
                            <?php if (strlen($job['description']) > 180): ?>
                                <button class="read-more-link" type="button">read more</button>
                            <?php endif; ?>
                        </div>

                        <!-- Job Footer -->
                        <div class="job-footer">
                            <div class="job-meta">
                                <span class="jmeta">📍 <?= htmlspecialchars($job['location'] ?? '—') ?></span>
                                <span class="jmeta">👥 <span class="applicant-count"><?= $job['applicant_count'] ?></span> applied</span>
                                <?php if ($job['deadline']): ?>
                                    <span class="jmeta">📅 <?= date('M d, Y', strtotime($job['deadline'])) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="job-action">
                                <?php if ($job['already_applied']): ?>
                                    <button class="btn btn-applied btn-sm" disabled>✓ Applied</button>
                                <?php elseif ($deadlinePassed): ?>
                                    <button class="btn btn-glass btn-sm" disabled>Closed</button>
                                <?php else: ?>
                                    <button class="btn btn-primary btn-sm btn-apply" data-job-id="<?= $job['id'] ?>">
                                        Apply Now →
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (isset($dStatus['days']) && $dStatus['days'] >= 0 && $dStatus['days'] <= 3): ?>
                            <p class="deadline-warn">⚠️ Deadline in <?= $dStatus['days'] ?> day<?= $dStatus['days'] !== 1 ? 's' : '' ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination" id="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn btn-glass btn-sm">← Previous</a>
                <?php endif; ?>
                <span class="page-info">Page <?= $page ?> of <?= $totalPages ?></span>
                <?php if ($page < $totalPages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn btn-glass btn-sm">Next →</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- ═══ RIGHT SIDEBAR ═══ -->
        <aside class="recruit-right">

            <!-- Widget 1 — Post Opportunity -->
            <div class="glass-card widget-card" id="postOpportunityWidget">
                <h5 class="widget-title"><span>📢</span> Post Opportunity</h5>
                <?php if ($userRole === 'recruiter'): ?>
                    <p class="text-muted text-sm mb-3">Find the best athletes for your team or program.</p>
                    <button class="btn btn-primary btn-sm w-full" id="openPostJobModal">
                        + Post a Recruitment
                    </button>
                <?php else: ?>
                    <p class="text-muted text-sm mb-3">Only recruiters can post opportunities.</p>
                    <a href="<?= $basePath ?>/pages/profile.php?id=<?= $userId ?>" class="btn btn-outline btn-sm w-full">
                        Complete your profile →
                    </a>
                <?php endif; ?>
            </div>

            <!-- Widget 2 — Platform Stats -->
            <div class="glass-card widget-card" id="platformStatsWidget">
                <h5 class="widget-title"><span>📊</span> Platform Stats</h5>
                <div class="platform-stats">
                    <div class="pstat-row">
                        <span class="pstat-label">Open Listings</span>
                        <span class="pstat-value"><?= $openCount ?></span>
                    </div>
                    <div class="pstat-row">
                        <span class="pstat-label">Total Applied</span>
                        <span class="pstat-value"><?= $totalApps ?></span>
                    </div>
                    <div class="pstat-row">
                        <span class="pstat-label">New This Week</span>
                        <span class="pstat-value"><?= $newThisWeek ?></span>
                    </div>
                    <div class="pstat-row">
                        <span class="pstat-label">Sports Covered</span>
                        <span class="pstat-value"><?= $sportsCount ?></span>
                    </div>
                </div>
            </div>

            <!-- Widget 3 — Application Tips -->
            <div class="glass-card widget-card" id="tipsWidget">
                <h5 class="widget-title"><span>💡</span> Application Tips</h5>
                <div class="tips-list">
                    <div class="tip-item">
                        <span class="tip-icon">✅</span>
                        <p class="tip-text">Complete your profile — recruiters check it first</p>
                    </div>
                    <div class="tip-item">
                        <span class="tip-icon">📸</span>
                        <p class="tip-text">Add a profile photo to increase response rate by 3x</p>
                    </div>
                    <div class="tip-item">
                        <span class="tip-icon">⚡</span>
                        <p class="tip-text">Apply early — most spots fill within 48 hours</p>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</div>


<!-- ═══════════════ POST JOB MODAL ═══════════════ -->
<?php if ($userRole === 'recruiter'): ?>
<div class="modal-overlay" id="postJobModal">
    <div class="glass-modal" style="position:relative">
        <button class="modal-close" id="closePostJobModal" aria-label="Close">
            <span class="material-icons-round">close</span>
        </button>
        <h3 class="modal-title">Post a New Opportunity</h3>
        <p class="text-muted text-sm mb-4">Find athletes, coaches, and talent for your organization.</p>

        <form id="postJobForm">
            <div class="form-group mb-3">
                <label class="form-label">Job Title *</label>
                <input type="text" class="glass-input" id="jobTitle" name="title" placeholder="e.g. U-23 Football Striker" required>
            </div>
            <div class="form-row-2">
                <div class="form-group mb-3">
                    <label class="form-label">Sport *</label>
                    <input type="text" class="glass-input" id="jobSport" name="sport" placeholder="e.g. Football, Cricket" required>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Location *</label>
                    <input type="text" class="glass-input" id="jobLocationInput" name="location" placeholder="e.g. Mumbai, India" required>
                </div>
            </div>
            <div class="form-row-2">
                <div class="form-group mb-3">
                    <label class="form-label">Type *</label>
                    <select class="glass-input" id="jobType" name="type" required>
                        <option value="">Select type</option>
                        <option value="job">Job</option>
                        <option value="scholarship">Scholarship</option>
                        <option value="tryout">Tryout</option>
                        <option value="training">Training</option>
                    </select>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Deadline *</label>
                    <input type="date" class="glass-input" id="jobDeadline" name="deadline" required
                           min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                </div>
            </div>
            <div class="form-group mb-4">
                <label class="form-label">Description *</label>
                <textarea class="glass-input glass-textarea" id="jobDescription" name="description"
                          placeholder="Describe the opportunity, requirements, and benefits..."
                          rows="4" required minlength="30"></textarea>
                <span class="form-hint" id="descCharCount">0 / 30 minimum characters</span>
            </div>
            <div class="flex gap-sm justify-end">
                <button type="button" class="btn btn-glass" id="cancelPostJob">Cancel</button>
                <button type="submit" class="btn btn-primary" id="submitPostJob">
                    <span class="material-icons-round" style="font-size:16px">send</span>
                    Post Opportunity
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>


<!-- ═══════════════ APPLY MODAL ═══════════════ -->
<div class="modal-overlay" id="applyModal">
    <div class="glass-modal" style="position:relative">
        <button class="modal-close" id="closeApplyModal" aria-label="Close">
            <span class="material-icons-round">close</span>
        </button>
        <h3 class="modal-title" id="applyModalTitle">Apply for Opportunity</h3>
        <p class="text-muted text-sm mb-4" id="applyModalSub">Posted by Organization</p>

        <div class="apply-user-info glass-card mb-4">
            <div class="flex items-center gap-sm">
                <div class="avatar-circle avatar-sm">
                    <span class="avatar-initials" style="font-size:10px"><?= strtoupper(substr($_SESSION['name'], 0, 1)) . (isset(explode(' ', $_SESSION['name'])[1]) ? strtoupper(substr(explode(' ', $_SESSION['name'])[1], 0, 1)) : '') ?></span>
                </div>
                <div>
                    <p class="text-sm font-semibold"><?= htmlspecialchars($_SESSION['name']) ?></p>
                    <p class="text-xs text-muted"><?= htmlspecialchars($_SESSION['sport'] ?? ucfirst($userRole)) ?></p>
                </div>
            </div>
        </div>

        <div class="form-group mb-4">
            <label class="form-label">Message to recruiter (optional)</label>
            <textarea class="glass-input glass-textarea" id="applyMessage"
                      placeholder="Add a message to the recruiter..." rows="3"></textarea>
        </div>

        <p class="text-xs text-muted mb-4" style="display:flex;align-items:center;gap:4px">
            <span class="material-icons-round" style="font-size:14px">info</span>
            Your profile will be shared with the recruiter
        </p>

        <div class="flex gap-sm">
            <button type="button" class="btn btn-glass flex-1" id="cancelApply">Cancel</button>
            <button type="button" class="btn btn-primary flex-1" id="confirmApply" data-job-id="">
                <span class="material-icons-round" style="font-size:16px">check_circle</span>
                Confirm Application
            </button>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
