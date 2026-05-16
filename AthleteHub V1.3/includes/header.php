<?php
/**
 * AthleteHub — Global Header
 * Glassmorphism navigation bar with user dropdown.
 * Expects: $pageTitle (string) — sets the <title> tag.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Base path for assets (uses the constant from config/db.php)
$basePath = defined('BASE_URL') ? BASE_URL : '';


// User info from session
$userName    = $_SESSION['name']    ?? 'User';
$userRole    = $_SESSION['role']    ?? 'athlete';
$userId      = $_SESSION['user_id'] ?? 0;
$profilePic  = $_SESSION['profile_pic'] ?? '';

// --- Self-Healing Cache ---
// If the session says no picture, but the database has one, sync it silently so the user doesn't have to log out.
if (empty($profilePic) && $userId && isset($pdo)) {
    try {
        $healStmt = $pdo->prepare("SELECT profile_pic FROM users WHERE id = ?");
        $healStmt->execute([$userId]);
        $dbPic = $healStmt->fetchColumn();
        if (!empty($dbPic)) {
            $profilePic = $dbPic;
            $_SESSION['profile_pic'] = $dbPic;
        }
    } catch (Exception $e) {
        // fail silently 
    }
}

// Generate initials for avatar fallback
$nameParts = explode(' ', $userName);
$initials  = strtoupper(substr($nameParts[0], 0, 1));
if (isset($nameParts[1])) {
    $initials .= strtoupper(substr($nameParts[1], 0, 1));
}

// Unread messages count
$unreadCount = 0;
if (isset($pdo) && $userId) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        $unreadCount = (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        $unreadCount = 0;
    }
}

// Current page for active-link highlighting
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="AthleteHub — The premier sports networking platform for athletes, coaches, recruiters, and clubs.">
    <title><?php echo htmlspecialchars($pageTitle ?? 'AthleteHub'); ?> — AthleteHub</title>
    <?php if (function_exists('csrf_meta_tag')) echo csrf_meta_tag(); ?>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">

    <!-- Stylesheets -->
    <link rel="stylesheet" href="<?php echo $basePath; ?>/assets/css/main.css?t=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo $basePath; ?>/assets/css/glass.css?t=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo $basePath; ?>/assets/css/responsive.css?t=<?php echo time(); ?>">
    <?php if (!empty($pageCSS)): ?>
        <?php foreach ((array)$pageCSS as $css): ?>
            <link rel="stylesheet" href="<?php echo $basePath; ?>/assets/css/<?php echo $css; ?>.css?t=<?php echo time(); ?>">
        <?php endforeach; ?>
    <?php endif; ?>

    <script>
        window.AthleteHubBaseUrl = '<?php echo $basePath; ?>';
    </script>
</head>

<body>

<!-- ═══════════════ GLASSMORPHISM NAVIGATION ═══════════════ -->
<nav class="glass-nav" id="mainNav">
    <div class="nav-inner">

        <!-- Logo -->
        <a href="<?php echo $basePath; ?>/pages/feed.php" class="nav-logo" id="navLogo">
            <img src="<?php echo $basePath; ?>/assets/images/logo-trophy.png" alt="AthleteHub Logo" class="logo-image" style="width: 46px; height: 46px; object-fit: contain; filter: drop-shadow(0 2px 8px rgba(0,0,0,0.15));">
            <span class="logo-text">Athlete<strong>Hub</strong></span>
        </a>

        <!-- Navigation Links -->
        <ul class="nav-links" id="navLinks">
            <li>
                <a href="<?php echo $basePath; ?>/pages/feed.php"
                   class="nav-link <?php echo ($currentPage === 'feed') ? 'active' : ''; ?>"
                   id="navFeed">
                    <span class="material-icons-round">dynamic_feed</span>
                    <span class="nav-label">Feed</span>
                </a>
            </li>
            <li>
                <a href="<?php echo $basePath; ?>/pages/recruitment.php"
                   class="nav-link <?php echo ($currentPage === 'recruitment') ? 'active' : ''; ?>"
                   id="navRecruit">
                    <span class="material-icons-round">work_outline</span>
                    <span class="nav-label">Recruit</span>
                </a>
            </li>
            <li>
                <a href="<?php echo $basePath; ?>/pages/tournaments.php"
                   class="nav-link <?php echo ($currentPage === 'tournaments') ? 'active' : ''; ?>"
                   id="navTournaments">
                    <span class="material-icons-round">emoji_events</span>
                    <span class="nav-label">Tournaments</span>
                </a>
            </li>
            <li>
                <a href="<?php echo $basePath; ?>/pages/news.php"
                   class="nav-link <?php echo ($currentPage === 'news') ? 'active' : ''; ?>"
                   id="navNews">
                    <span class="material-icons-round">newspaper</span>
                    <span class="nav-label">News</span>
                </a>
            </li>
            <li style="position: relative;">
                <a href="<?php echo $basePath; ?>/pages/livestreams.php"
                   class="nav-link <?php echo ($currentPage === 'livestreams') ? 'active' : ''; ?>"
                   id="navLive">
                    <span class="material-icons-round" style="color:var(--red-500)">sensors</span>
                    <span class="nav-label">Live</span>
                    <span class="live-dot-indicator shadow-glow" id="liveDotIndicator" style="display:none; width: 8px; height: 8px; background: var(--red-500); border-radius: 50%; position: absolute; top: 12px; right: 12px; animation: pulse 2s infinite;"></span>
                </a>
            </li>
            <li>
                <a href="<?php echo $basePath; ?>/pages/messages.php"
                   class="nav-link <?php echo ($currentPage === 'messages') ? 'active' : ''; ?>"
                   id="navMessages">
                    <span class="material-icons-round">chat_bubble_outline</span>
                    <span class="nav-label">Messages</span>
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge-count" id="unreadBadge"><?php echo $unreadCount; ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>

        <!-- Right: Notifications + User Dropdown -->
        <div class="nav-right">

            <!-- Global Search -->
            <div class="nav-search-wrapper" id="globalSearchWrapper">
                <input type="text" class="glass-input search-input" id="globalSearchInput" placeholder="" autocomplete="off">
                <span class="material-icons-round search-icon">search</span>
                <div class="search-dropdown" id="searchDropdown">
                    <div class="search-results-list" id="searchResultsList"></div>
                    <div class="search-loading" id="searchLoading" style="display: none;">Searching...</div>
                </div>
            </div>

            <!-- Notification Bell -->
            <button class="nav-icon-btn" id="notifBell" aria-label="Notifications">
                <span class="material-icons-round">notifications_none</span>
            </button>

            <!-- User Avatar + Dropdown -->
            <div class="nav-user" id="userMenu">
                <button class="avatar-circle avatar-md" id="userAvatarBtn" aria-label="User menu">
                    <?php if ($profilePic): ?>
                        <img src="<?php echo $basePath . '/uploads/profile_pics/' . htmlspecialchars($profilePic); ?>"
                             alt="<?php echo htmlspecialchars($userName); ?>">
                    <?php else: ?>
                        <span class="avatar-initials"><?php echo $initials; ?></span>
                    <?php endif; ?>
                </button>

                <!-- Dropdown -->
                <div class="dropdown-menu glass-card" id="userDropdown">
                    <div class="dropdown-header">
                        <strong><?php echo htmlspecialchars($userName); ?></strong>
                        <span class="text-muted"><?php echo ucfirst(htmlspecialchars($userRole)); ?></span>
                    </div>
                    <div class="dropdown-divider"></div>
                    <a href="<?php echo $basePath; ?>/pages/profile.php?id=<?php echo $userId; ?>" class="dropdown-item" id="dropdownProfile">
                        <span class="material-icons-round">person_outline</span> My Profile
                    </a>
                    <a href="<?php echo $basePath; ?>/pages/edit-profile.php" class="dropdown-item" id="dropdownSettings">
                        <span class="material-icons-round">settings</span> Settings
                    </a>
                    <?php if ($userRole === 'athlete' || $userRole === 'coach'): ?>
                        <a href="<?php echo $basePath; ?>/pages/apply_role.php" class="dropdown-item" id="dropdownApplyRole">
                            <span class="material-icons-round text-primary">how_to_reg</span> Elevate Account
                        </a>
                    <?php endif; ?>
                    <?php if ($userRole === 'admin'): ?>
                        <?php 
                            $pendingRoles = 0;
                            if (isset($pdo)) {
                                $stmtRole = $pdo->query("SELECT COUNT(*) FROM role_applications WHERE status = 'pending'");
                                $pendingRoles = (int)$stmtRole->fetchColumn();
                            }
                        ?>
                        <div class="dropdown-divider"></div>
                        <a href="<?php echo $basePath; ?>/pages/admin/role_applications.php" class="dropdown-item" id="dropdownAdminRoles">
                            <span class="material-icons-round text-yellow-500">admin_panel_settings</span> Role Applications
                            <?php if ($pendingRoles > 0): ?>
                                <span class="bg-red-500 text-white text-[10px] px-1.5 py-0.5 rounded-full ml-auto"><?= $pendingRoles ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                    <div class="dropdown-divider"></div>
                    <a href="<?php echo $basePath; ?>/api/logout.php" class="dropdown-item dropdown-item--danger" id="dropdownLogout">
                        <span class="material-icons-round">logout</span> Logout
                    </a>
                </div>
            </div>

            <!-- Mobile hamburger -->
            <button class="nav-hamburger" id="navHamburger" aria-label="Toggle navigation">
                <span class="material-icons-round">menu</span>
            </button>
        </div>

    </div>
</nav>

<!-- Page Content Wrapper -->
<main class="page-wrapper">
