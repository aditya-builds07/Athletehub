<?php
/**
 * AthleteHub — Tournaments Page
 * View, filter, and register for tournaments using a magazine layout.
 */

session_start();
require_once '../includes/session.php';
require_once '../config/db.php';

$userId   = $_SESSION['user_id'];
$userRole = $_SESSION['role'];
$basePath = defined('BASE_URL') ? BASE_URL : '';


// ── Read filters from GET ──
$search   = trim($_GET['search'] ?? '');
$sport    = trim($_GET['sport'] ?? '');
$location = trim($_GET['location'] ?? '');
$status   = trim($_GET['status'] ?? '');
$sort     = $_GET['sort'] ?? 'soonest';

// ── Handle Register (POST request routed through here/API) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    if (($input['action'] ?? '') === 'register') {
        if ($userRole !== 'athlete') {
            echo json_encode(['success' => false, 'error' => 'Only athletes can register']);
            exit;
        }

        $tId = (int)$input['tournament_id'];
        
        // check not registered
        $chk = $pdo->prepare("SELECT id FROM tournament_registrations WHERE tournament_id = ? AND user_id = ?");
        $chk->execute([$tId, $userId]);
        if ($chk->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Already registered']);
            exit;
        }

        // check open
        $chkStat = $pdo->prepare("SELECT status FROM tournaments WHERE id = ?");
        $chkStat->execute([$tId]);
        if ($chkStat->fetchColumn() !== 'open') {
            echo json_encode(['success' => false, 'error' => 'Registration is closed']);
            exit;
        }

        // register
        $ins = $pdo->prepare("INSERT INTO tournament_registrations (tournament_id, user_id) VALUES (?, ?)");
        $ins->execute([$tId, $userId]);

        // new total
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM tournament_registrations WHERE tournament_id = ?");
        $cnt->execute([$tId]);
        
        echo json_encode([
            'success'   => true,
            'reg_count' => (int)$cnt->fetchColumn(),
            'message'   => 'Successfully registered!'
        ]);
        exit;
    }
}

// ── Base Select structure ──
$baseSelect = "SELECT t.*,
  IFNULL(rc.reg_count, 0) AS reg_count,
  IFNULL(ur.is_reg, 0) AS already_registered
FROM tournaments t
LEFT JOIN (
  SELECT tournament_id, COUNT(*) as reg_count 
  FROM tournament_registrations 
  GROUP BY tournament_id
) rc ON t.id = rc.tournament_id
LEFT JOIN (
  SELECT tournament_id, 1 as is_reg 
  FROM tournament_registrations 
  WHERE user_id = ?
) ur ON t.id = ur.tournament_id";

// ── Fetch FEATURED tournament ──
$featured = null;
$featWhere = ["t.status = 'open'"];
$featParams = [$userId];

if ($search !== '') {
    $featWhere[] = "t.name LIKE ?";
    $featParams[] = "%{$search}%";
}
if ($sport !== '') {
    $featWhere[] = "t.sport = ?";
    $featParams[] = $sport;
}
if ($location !== '') {
    $featWhere[] = "t.location LIKE ?";
    $featParams[] = "%{$location}%";
}

$featWhereSQL = implode(' AND ', $featWhere);

if ($status === '' || $status === 'open') {
    $featStmt = $pdo->prepare("
        $baseSelect
        WHERE $featWhereSQL
        ORDER BY t.start_date ASC
        LIMIT 1
    ");
    $featStmt->execute($featParams);
    $featured = $featStmt->fetch(PDO::FETCH_ASSOC);
}

// ── Fetch REMAINING tournaments ──
$where  = [];
$params = [$userId];

// Exclude featured if present
if ($featured) {
    $where[]  = "t.id != ?";
    $params[] = $featured['id'];
}

if ($search !== '') {
    $where[]  = "t.name LIKE ?";
    $params[] = "%{$search}%";
}
if ($sport !== '') {
    $where[]  = "t.sport = ?";
    $params[] = $sport;
}
if ($location !== '') {
    $where[]  = "t.location LIKE ?";
    $params[] = "%{$location}%";
}
if ($status !== '') {
    $where[]  = "t.status = ?";
    $params[] = $status;
}

$whereSQL = count($where) > 0 ? "WHERE " . implode(' AND ', $where) : "";
$orderSQL = $sort === 'latest' ? "t.start_date DESC" : "t.start_date ASC";

$tournStmt = $pdo->prepare("
    $baseSelect
    $whereSQL
    ORDER BY $orderSQL
");
$tournStmt->execute($params);
$tournaments = $tournStmt->fetchAll(PDO::FETCH_ASSOC);

// Total for display
$totalFound = count($tournaments) + ($featured ? 1 : 0);

// For JS Calendar
$allDatesStmt = $pdo->query("SELECT start_date FROM tournaments WHERE status != 'closed'");
$allTournaments = $allDatesStmt->fetchAll(PDO::FETCH_ASSOC);


// ── Fetch filters metadata ──
$sports = $pdo->query("SELECT DISTINCT sport FROM tournaments ORDER BY sport")->fetchAll(PDO::FETCH_COLUMN);
$locations = $pdo->query("SELECT DISTINCT location FROM tournaments ORDER BY location")->fetchAll(PDO::FETCH_COLUMN);

// ── Tabs count ──
$stCountStmt = $pdo->query("SELECT status, COUNT(*) as cnt FROM tournaments GROUP BY status");
$statusCounts = ['open' => 0, 'upcoming' => 0, 'closed' => 0];
$totalStatusCount = 0;
while ($row = $stCountStmt->fetch(PDO::FETCH_ASSOC)) {
    $statusCounts[$row['status']] = (int)$row['cnt'];
    $totalStatusCount += (int)$row['cnt'];
}

// ── Sidebar: Sports breakdown ──
$sbStmt = $pdo->query("
    SELECT sport, COUNT(*) as cnt
    FROM tournaments
    WHERE status != 'closed'
    GROUP BY sport ORDER BY cnt DESC
");
$sportBreakdown = $sbStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Sidebar: Upcoming 3 ──
$upStmt = $pdo->query("
    SELECT * FROM tournaments
    WHERE start_date >= CURDATE()
    ORDER BY start_date ASC LIMIT 3
");
$upcoming = $upStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Theme helpers ──
function getSportGradient($sportName) {
    $map = [
        'athletics' => 'linear-gradient(135deg, #0057FF, #00C2FF)',
        'football'  => 'linear-gradient(135deg, #16A34A, #0891B2)',
        'cricket'   => 'linear-gradient(135deg, #D97706, #F59E0B)',
        'swimming'  => 'linear-gradient(135deg, #0891B2, #06B6D4)',
    ];
    $key = strtolower($sportName);
    return $map[$key] ?? 'linear-gradient(135deg, #7C3AED, #6D28D9)';
}

// ── Page Info ──
$pageTitle = 'Tournaments';
$pageCSS   = ['tournaments'];
$pageJS    = ['tournaments'];
require_once '../includes/header.php';
?>

<!-- ═══════════════ TOURNAMENT COMPONENT ═══════════════ -->
<div class="tourn-wrap">

    <!-- ━━━━━━━━ SECTION 1 — HERO + FILTER BAR ━━━━━━━━ -->
    <div class="glass-card tourn-hero">
        <div class="hero-text" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
            <div>
                <h1 class="hero-title">🏆 Sports Tournaments</h1>
                <p class="hero-sub">Discover, register and compete in tournaments across India</p>
            </div>
            <?php if ($userRole === 'club'): ?>
                <button class="btn btn-primary" id="openCreateTournamentModal">
                    ✨ Create Tournament
                </button>
            <?php endif; ?>
        </div>

        <form method="GET" action="" class="filter-row" id="filterForm">
            <input type="text" name="search" class="glass-input filter-search"
                   placeholder="Search tournament name..."
                   value="<?= htmlspecialchars($search) ?>">

            <select name="sport" class="glass-input filter-select">
                <option value="">All Sports</option>
                <?php foreach ($sports as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>" <?= $sport === $s ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="location" class="glass-input filter-select">
                <option value="">All Locations</option>
                <?php foreach ($locations as $loc): ?>
                    <option value="<?= htmlspecialchars($loc) ?>" <?= $location === $loc ? 'selected' : '' ?>>
                        <?= htmlspecialchars($loc) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="status" class="glass-input filter-select">
                <option value="">All Statuses</option>
                <option value="open"     <?= $status === 'open' ? 'selected' : '' ?>>Open</option>
                <option value="upcoming" <?= $status === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                <option value="closed"   <?= $status === 'closed' ? 'selected' : '' ?>>Closed</option>
            </select>

            <select name="sort" class="glass-input filter-select">
                <option value="soonest" <?= $sort === 'soonest' ? 'selected' : '' ?>>Soonest First</option>
                <option value="latest"  <?= $sort === 'latest' ? 'selected' : '' ?>>Latest First</option>
            </select>

            <button type="submit" class="btn btn-primary btn-sm filter-btn">
                🔍 Search
            </button>
        </form>

        <?php 
            // Filter Pills display logic
            $activeFilters = [];
            if ($search !== '') $activeFilters['search'] = "Search: \"$search\"";
            if ($sport !== '') $activeFilters['sport'] = "Sport: $sport";
            if ($location !== '') $activeFilters['location'] = "Location: $location";
            
            if (!empty($activeFilters)): 
        ?>
            <div class="filter-pills mt-3">
                <?php foreach($activeFilters as $key => $lbl): ?>
                    <span class="filter-pill" data-key="<?= $key ?>">
                        <?= htmlspecialchars($lbl) ?>
                        <button type="button" class="pill-clear" data-key="<?= $key ?>">×</button>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>


    <!-- ━━━━━━━━ SECTION 2 — MAIN MAGAZINE GRID ━━━━━━━━ -->
    <div class="tourn-grid">

        <!-- ══ LEFT COLUMN ══ -->
        <div class="tourn-left">

            <div class="jobs-header">
                <span class="jobs-count">
                    <strong><?= $totalFound ?></strong> tournament<?= $totalFound !== 1 ? 's' : '' ?> found
                </span>
            </div>

            <!-- Status Tabs -->
            <div class="status-tabs">
                <a href="?<?= http_build_query(array_merge($_GET, ['status' => ''])) ?>"
                   class="stab <?= $status === '' ? 'active' : '' ?>">
                    All <span class="tab-count"><?= $totalStatusCount ?></span>
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'open'])) ?>"
                   class="stab <?= $status === 'open' ? 'active' : '' ?>">
                    🟢 Open <span class="tab-count"><?= $statusCounts['open'] ?></span>
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'upcoming'])) ?>"
                   class="stab <?= $status === 'upcoming' ? 'active' : '' ?>">
                    🗓️ Upcoming <span class="tab-count"><?= $statusCounts['upcoming'] ?></span>
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'closed'])) ?>"
                   class="stab <?= $status === 'closed' ? 'active' : '' ?>">
                    ⚫ Closed <span class="tab-count"><?= $statusCounts['closed'] ?></span>
                </a>
            </div>

            <!-- FEATURED CARD -->
            <?php if ($featured): 
                $grad = getSportGradient($featured['sport']);
                $fStart = date('M d', strtotime($featured['start_date']));
            ?>
            <div class="glass-card mag-featured" data-id="<?= $featured['id'] ?>">
                <div class="mag-banner" style="background: <?= $grad ?>;">
                    <div class="mag-banner-top">
                        <span class="mag-feat-label">⭐ FEATURED TOURNAMENT</span>
                        <h2 class="mag-feat-name"><?= htmlspecialchars($featured['name']) ?></h2>
                        <span class="mag-feat-org">Organized by ACME Sports (Demo)</span>
                    </div>
                    <div class="mag-banner-bottom">
                        <span class="mag-date-lbl">Starts</span>
                        <div class="mag-feat-date"><?= $fStart ?></div>
                    </div>
                </div>

                <div class="mag-info">
                    <div class="mag-info-top">
                        <div class="mag-info-row">
                            <span class="material-icons-round" style="font-size:14px; color:var(--text-muted)">location_on</span>
                            <span style="font-size:12px"><?= htmlspecialchars($featured['location']) ?></span>
                        </div>
                        <div class="mag-info-row">
                            <span class="material-icons-round" style="font-size:14px; color:var(--text-muted)">event</span>
                            <span style="font-size:12px">
                                <?= date('M d', strtotime($featured['start_date'])) ?> – 
                                <?= date('M d, Y', strtotime($featured['end_date'])) ?>
                            </span>
                        </div>
                        <div class="mag-info-row">
                            <span class="material-icons-round" style="font-size:14px; color:var(--text-muted)">directions_run</span>
                            <span style="font-size:12px"><?= htmlspecialchars($featured['sport']) ?></span>
                        </div>
                        
                        <div class="mag-prize-big mt-3">
                            🏅 <?= htmlspecialchars($featured['prize_info']) ?>
                        </div>
                        <div class="mag-reg-count mt-1">
                            👥 <span class="reg-val"><?= $featured['reg_count'] ?></span> athletes registered
                        </div>
                    </div>

                    <div class="mag-info-bottom">
                        <?php if ($userRole === 'club' && $userId == ($featured['host_user_id'] ?? null)): ?>
                            <div style="display:flex; gap:8px; width:100%;">
                                <button class="btn btn-primary btn-sm btn-view-participants flex-1" 
                                        data-tournament-id="<?= $featured['id'] ?>"
                                        data-name="<?= htmlspecialchars($featured['name']) ?>">
                                    👥 View Participants
                                </button>
                                <button class="btn btn-outline btn-sm btn-manage-tournament flex-1"
                                        data-tournament-id="<?= $featured['id'] ?>">
                                    ⚙️ Manage
                                </button>
                            </div>
                        <?php elseif ($featured['already_registered']): ?>
                            <div class="registered-status">✓ You are Registered!</div>
                        <?php elseif ($userRole !== 'athlete'): ?>
                            <span class="text-muted" style="font-size:11px">Only athletes can register</span>
                        <?php else: ?>
                            <button class="btn btn-primary btn-register w-full" 
                                    data-tournament-id="<?= $featured['id'] ?>"
                                    data-name="<?= htmlspecialchars($featured['name']) ?>">
                                Register Now →
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>


            <!-- SMALL CARDS -->
            <div class="small-cards-grid">
                <?php foreach($tournaments as $t): 
                    $grad = getSportGradient($t['sport']);
                    $sd   = date('d', strtotime($t['start_date']));
                    $sm   = date('M', strtotime($t['start_date']));
                    
                    $badgeStyle = 'background: rgba(0,0,0,0.2); color:white;';
                    $badgeText  = 'Closed';
                    if ($t['status'] === 'open') {
                        $badgeStyle = 'background: rgba(255,255,255,0.25); color:white;';
                        $badgeText = 'Open';
                    } elseif ($t['status'] === 'upcoming') {
                        $badgeStyle = 'background: rgba(255,255,255,0.25); color:white;';
                        $badgeText = 'Upcoming';
                    }
                ?>
                <div class="glass-card mag-small" data-id="<?= $t['id'] ?>">
                    <div class="mag-small-top" style="background: <?= $grad ?>">
                        <div class="mag-small-date-box" style="display:flex; flex-direction:column; align-items:center;">
                            <div class="mag-small-date"><?= $sd ?></div>
                            <div class="mag-small-mon"><?= strtoupper($sm) ?></div>
                        </div>
                        <div class="mag-status-badge" style="<?= $badgeStyle ?>; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600;"><?= $badgeText ?></div>
                    </div>
                    
                    <div class="mag-small-body">
                        <h3 class="mag-small-title"><?= htmlspecialchars($t['name']) ?></h3>
                        <p class="mag-small-sub"><?= htmlspecialchars($t['sport']) ?> • <?= htmlspecialchars($t['location']) ?></p>

                        <div class="stats-row" style="display:flex; gap:12px; margin:8px 0;">
                            <span class="stat-lbl" style="font-size:10px; color:var(--text-muted);">📍 <?= htmlspecialchars($t['location']) ?></span>
                            <span class="stat-lbl" style="font-size:10px; color:var(--text-muted);">👥 <span class="reg-val"><?= $t['reg_count'] ?></span> athletes</span>
                        </div>

                        <div class="mag-prize-sm" style="font-size:12px; font-weight:bold; color:var(--amber-600); margin-bottom:12px;">🏅 <?= htmlspecialchars($t['prize_info']) ?></div>

                        <div class="mag-small-footer" style="display:flex; justify-content:space-between; align-items:center;">
                            <span class="deadline-txt" style="font-size:10px; color:var(--text-muted);">📅 Starts <?= date('M d', strtotime($t['start_date'])) ?></span>

                            <div class="mag-action">
                            <?php if ($userRole === 'club' && $userId == ($t['host_user_id'] ?? null)): ?>
                                <div style="display:flex; gap:4px;">
                                    <button class="btn btn-sm btn-primary btn-view-participants"
                                            data-tournament-id="<?= $t['id'] ?>"
                                            data-name="<?= htmlspecialchars($t['name']) ?>"
                                            style="padding:4px 8px; font-size:10px;">👥 Participants</button>
                                    <button class="btn btn-sm btn-outline btn-manage-tournament"
                                            data-tournament-id="<?= $t['id'] ?>"
                                            style="padding:4px 8px; font-size:10px;">⚙️ Manage</button>
                                </div>
                            <?php elseif ($t['already_registered']): ?>
                                <button class="btn btn-sm registered-btn" disabled style="background:transparent; color:var(--success); border:1px solid var(--success);">✓ Registered</button>
                            <?php elseif ($t['status'] === 'closed'): ?>
                                <button class="btn btn-sm closed-btn" disabled style="background:transparent; color:var(--text-muted); border:1px solid var(--border-subtle);">Closed</button>
                            <?php elseif ($t['status'] === 'upcoming'): ?>
                                <button class="btn btn-sm notify-btn" style="background:transparent; color:var(--primary); border:1px solid var(--primary);">Notify Me</button>
                            <?php else: ?>
                                <?php if ($userRole === 'athlete'): ?>
                                    <button class="btn btn-sm btn-primary mag-small-btn btn-register"
                                            data-tournament-id="<?= $t['id'] ?>"
                                            data-name="<?= htmlspecialchars($t['name']) ?>">Register →</button>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:10px">Athletes only</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (!$featured && empty($tournaments)): ?>
                <div class="glass-card empty-state">
                    <span class="empty-emoji">🏆</span>
                    <h3>No tournaments found</h3>
                    <p class="text-muted text-sm" style="margin-bottom:12px;">Try adjusting your filters.</p>
                    <a href="?status=" class="btn btn-outline btn-sm">Clear all filters</a>
                </div>
            <?php endif; ?>

        </div>
        

        <!-- ══ RIGHT SIDEBAR ══ -->
        <aside class="tourn-right">
            
            <!-- Miniature Calendar -->
            <div class="glass-card widget-card">
                <h5 class="widget-title">📅 <span id="calTitle">Mini Calendar</span></h5>
                <div class="mini-calendar" id="miniCalendar">
                    <!-- JS renders calendar here -->
                </div>
                <!-- Pass tournament dates for JS to highlight -->
                <script>
                    const tDates = <?= json_encode(array_column($allTournaments, 'start_date')) ?>;
                </script>
            </div>

            <!-- Happening Soon -->
            <div class="glass-card widget-card">
                <h5 class="widget-title">⚡ Happening Soon</h5>
                <?php if (empty($upcoming)): ?>
                    <p class="text-sm text-muted">Nothing scheduled soon.</p>
                <?php else: ?>
                    <div class="tips-list">
                        <?php foreach($upcoming as $up): 
                            $dotColor = $up['status'] === 'open' ? '#16A34A' : '#7C3AED';
                        ?>
                        <div class="upcoming-item" data-id="<?= $up['id'] ?>">
                            <div class="up-dot" style="background:<?= $dotColor ?>"></div>
                            <div style="flex:1">
                                <div class="up-name"><?= htmlspecialchars($up['name']) ?></div>
                                <div class="up-meta"><?= date('M d', strtotime($up['start_date'])) ?> • <?= htmlspecialchars($up['location']) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- By Sport -->
            <div class="glass-card widget-card">
                <h5 class="widget-title">🏅 By Sport</h5>
                <?php if (empty($sportBreakdown)): ?>
                    <p class="text-sm text-muted">No data available.</p>
                <?php else: ?>
                    <div class="tips-list">
                        <?php foreach($sportBreakdown as $sb): ?>
                        <div class="sport-item" data-sport="<?= htmlspecialchars($sb['sport']) ?>">
                            <span class="sp-name"><?= htmlspecialchars($sb['sport']) ?></span>
                            <span class="sport-count"><?= $sb['cnt'] ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </aside>

    </div>
</div>


<!-- ═══════════════ REGISTER MODAL ═══════════════ -->
<?php if ($userRole === 'athlete'): ?>
<div class="modal-overlay" id="registerModal">
    <div class="glass-modal" style="position:relative">
        <button class="modal-close" id="closeRegisterModal">
            <span class="material-icons-round">close</span>
        </button>
        <h3 class="modal-title">Register for Tournament</h3>
        <p class="text-md font-bold text-primary mb-4" id="modTName">Tournament Name</p>

        <div class="mod-info-grid mb-4">
            <div class="mi-row">
                <span class="material-icons-round text-muted" style="font-size:14px">location_on</span>
                <span class="text-sm" id="modTLoc">Location</span>
            </div>
            <div class="mi-row">
                <span class="material-icons-round text-muted" style="font-size:14px">event</span>
                <span class="text-sm" id="modTDates">Dates</span>
            </div>
            <div class="mi-row">
                <span class="material-icons-round text-muted" style="font-size:14px">emoji_events</span>
                <span class="text-sm font-bold text-amber-600" id="modTPrize">Prize</span>
            </div>
            <div class="mi-row">
                <span class="material-icons-round text-muted" style="font-size:14px">group</span>
                <span class="text-sm" id="modTRegs">Reg count</span>
            </div>
        </div>

        <div class="auth-divider mb-3"><span>Registering as</span></div>

        <div class="apply-user-info glass-card mb-4">
            <div class="flex items-center gap-sm">
                <div class="avatar-circle avatar-sm">
                    <span class="avatar-initials" style="font-size:10px"><?= strtoupper(substr($_SESSION['name'], 0, 1)) ?></span>
                </div>
                <div>
                    <p class="text-sm font-semibold"><?= htmlspecialchars($_SESSION['name']) ?></p>
                    <p class="text-xs text-muted"><?= htmlspecialchars($_SESSION['sport'] ?? 'Athlete') ?></p>
                </div>
            </div>
        </div>

        <p class="text-xs text-muted mb-4" style="display:flex;align-items:center;gap:4px">
            <span class="material-icons-round" style="font-size:14px">info</span>
            Your profile will be visible to the tournament organizer.
        </p>

        <div class="flex gap-sm">
            <button type="button" class="btn btn-glass flex-1" id="cancelRegister">Cancel</button>
            <button type="button" class="btn btn-primary flex-1" id="btn-confirm-register" data-tournament-id="">
                <span class="material-icons-round" style="font-size:16px">check_circle</span>
                Confirm Registration
            </button>
        </div>
    </div>
</div>
<?php endif; ?>


<!-- ═══════════════ CREATE TOURNAMENT MODAL ═══════════════ -->
<?php if ($userRole === 'club'): ?>
<div class="modal-overlay" id="createTournamentModal">
    <div class="glass-modal" style="position:relative; max-width: 500px;">
        <button class="modal-close" id="closeCreateTournamentModal" aria-label="Close">
            <span class="material-icons-round">close</span>
        </button>
        <h3 class="modal-title mb-4">✨ Create Tournament</h3>
        
        <form id="createTournamentForm" class="flex flex-col gap-3">
            <div>
                <label class="form-label text-xs font-semibold">Tournament Name *</label>
                <input type="text" name="name" class="glass-input w-full" required placeholder="e.g. Inter-Club Premier League">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label text-xs font-semibold">Sport *</label>
                    <select name="sport" class="glass-input w-full" required>
                        <option value="">Select Sport</option>
                        <option value="Football">Football</option>
                        <option value="Basketball">Basketball</option>
                        <option value="Cricket">Cricket</option>
                        <option value="Swimming">Swimming</option>
                        <option value="Athletics">Athletics</option>
                        <option value="Tennis">Tennis</option>
                        <option value="Badminton">Badminton</option>
                    </select>
                </div>
                <div>
                    <label class="form-label text-xs font-semibold">Location *</label>
                    <input type="text" name="location" class="glass-input w-full" required placeholder="City, State">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label text-xs font-semibold">Start Date *</label>
                    <input type="date" name="start_date" class="glass-input w-full" required>
                </div>
                <div>
                    <label class="form-label text-xs font-semibold">End Date *</label>
                    <input type="date" name="end_date" class="glass-input w-full" required>
                </div>
            </div>

            <div>
                <label class="form-label text-xs font-semibold">Prize Info / Rewards</label>
                <input type="text" name="prize_info" class="glass-input w-full" placeholder="e.g. ₹1,00,000 Prize Pool + Trophy">
            </div>

            <button type="submit" class="btn btn-primary w-full mt-2">
                🚀 Launch Tournament
            </button>
        </form>
    </div>
</div>


<!-- ═══════════════ VIEW PARTICIPANTS MODAL ═══════════════ -->
<div class="modal-overlay" id="viewParticipantsModal">
    <div class="glass-modal" style="position:relative; max-width: 550px; width: 100%;">
        <button class="modal-close" id="closeViewParticipantsModal" aria-label="Close">
            <span class="material-icons-round">close</span>
        </button>
        <h3 class="modal-title mb-1">👥 Registered Participants</h3>
        <p class="text-xs text-muted mb-4" id="partModalSub">Loading participant list...</p>

        <div class="participants-list-container max-h-80 overflow-y-auto pr-1 flex flex-col gap-2" id="participantsListContent">
            <!-- Dynamic Injection via JS -->
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
