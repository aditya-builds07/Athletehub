<?php
session_start();

require_once __DIR__ . '/../config/db.php';

/**
 * AthleteHub — Global Header
 * Glassmorphism navigation bar with user dropdown.
 * Expects: $pageTitle (string) — sets the <title> tag.
 */

// Base path for assets
$basePath = defined('BASE_URL') ? BASE_URL : '';
$assets = defined('ASSETS') ? ASSETS : $basePath . '/assets';
$uploads = defined('UPLOADS') ? UPLOADS : $basePath . '/uploads';

// User info from session
$userName    = $_SESSION['name']    ?? 'User';
$userRole    = $_SESSION['role']    ?? 'athlete';
$userId      = $_SESSION['user_id'] ?? 0;
$profilePic  = $_SESSION['profile_pic'] ?? '';

// --- Self-Healing Cache ---
if (empty($profilePic) && $userId && isset($pdo)) {
    try {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $healStmt = $pdo->prepare("SELECT profile_pic FROM users WHERE id = ?");
        $healStmt->execute([$userId]);
        $dbPic = $healStmt->fetchColumn();
        if (!empty($dbPic)) {
            $profilePic = $dbPic;
            $_SESSION['profile_pic'] = $dbPic;
        }
    } catch (Exception $e) {
        error_log("Header profile_pic heal error: " . $e->getMessage());
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
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->prepare("SELECT COUNT(id) FROM messages WHERE receiver_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        $unreadCount = (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Header messages count error: " . $e->getMessage());
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
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
    <title><?php echo htmlspecialchars($pageTitle ?? 'AthleteHub'); ?> — AthleteHub</title>
    <?php if (function_exists('csrf_meta_tag')) echo csrf_meta_tag(); ?>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">

    <!-- Stylesheets -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars($assets); ?>/css/main.css?t=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($assets); ?>/css/glass.css?t=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($assets); ?>/css/responsive.css?t=<?php echo time(); ?>">
    <?php if (!empty($pageCSS)): ?>
        <?php foreach ((array)$pageCSS as $css): ?>
            <link rel="stylesheet" href="<?php echo htmlspecialchars($assets); ?>/css/<?php echo htmlspecialchars($css); ?>.css?t=<?php echo time(); ?>">
        <?php endforeach; ?>
    <?php endif; ?>

    <style>
        *, *::before, *::after { box-sizing: border-box; }
        a, button, .nav-link, .glass-input { transition: all 0.3s ease; }
        
        @media (min-width: 768px) {
            .nav-inner { padding: 0 2rem; }
            
            /* Fix 1.2: Nav links (desktop only to not break mobile) */
            .nav-links {
                display: flex !important;
                align-items: center !important;
                justify-content: center !important; /* Center the links */
                gap: 0.5rem !important;
                flex: 1 !important;
            }
        }

        /* Fix 1: overall layout */
        .glass-nav .nav-inner {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 0 !important;
        }

        /* Fix 1.1: Logo section */
        .nav-logo {
            display: flex !important;
            align-items: center !important;
            gap: 0.5rem !important;
            flex-shrink: 0 !important;
            margin-right: 2rem !important;
            min-width: fit-content !important;
        }

        /* Fix 3: Right side */
        .nav-right {
            display: flex !important;
            align-items: center !important;
            justify-content: flex-end !important;
            gap: 1rem !important;
            flex-shrink: 0 !important;
            margin-left: auto !important;
            min-width: fit-content !important;
        }
    </style>

    <script>
        window.AthleteHubBaseUrl = '<?php echo htmlspecialchars($basePath); ?>';
    </script>
</head>

<body>

<!-- ═══════════════ GLASSMORPHISM NAVIGATION ═══════════════ -->
<nav class="glass-nav" id="mainNav">
    <div class="nav-inner">

        <!-- Logo -->
        <a href="<?php echo htmlspecialchars($basePath); ?>/pages/feed.php" class="nav-logo" id="navLogo">
            <img src="<?php echo htmlspecialchars($assets); ?>/images/logo-trophy.png" alt="AthleteHub Logo" class="logo-image" style="width: 46px; height: 46px; object-fit: contain; filter: drop-shadow(0 2px 8px rgba(0,0,0,0.15));">
            <span class="logo-text">Athlete<strong>Hub</strong></span>
        </a>

        <!-- Navigation Links -->
        <ul class="nav-links" id="navLinks">
            <li>
                <a href="<?php echo htmlspecialchars($basePath); ?>/pages/feed.php"
                   class="nav-link <?php echo ($currentPage === 'feed') ? 'active' : ''; ?>"
                   id="navFeed">
                    <span class="material-icons-round">dynamic_feed</span>
                    <span class="nav-label">Feed</span>
                </a>
            </li>
            <li>
                <a href="<?php echo htmlspecialchars($basePath); ?>/pages/users.php"
                   class="nav-link <?php echo ($currentPage === 'users') ? 'active' : ''; ?>"
                   id="navUsers">
                    <span class="material-icons-round">people_alt</span>
                    <span class="nav-label">People</span>
                </a>
            </li>
            <li>
                <a href="<?php echo htmlspecialchars($basePath); ?>/pages/recruitment.php"
                   class="nav-link <?php echo ($currentPage === 'recruitment') ? 'active' : ''; ?>"
                   id="navRecruit">
                    <span class="material-icons-round">work_outline</span>
                    <span class="nav-label">Recruit</span>
                </a>
            </li>
            <li>
                <a href="<?php echo htmlspecialchars($basePath); ?>/pages/tournaments.php"
                   class="nav-link <?php echo ($currentPage === 'tournaments') ? 'active' : ''; ?>"
                   id="navTournaments">
                    <span class="material-icons-round">emoji_events</span>
                    <span class="nav-label">Tournaments</span>
                </a>
            </li>
            <li>
                <a href="<?php echo htmlspecialchars($basePath); ?>/pages/news.php"
                   class="nav-link <?php echo ($currentPage === 'news') ? 'active' : ''; ?>"
                   id="navNews">
                    <span class="material-icons-round">newspaper</span>
                    <span class="nav-label">News</span>
                </a>
            </li>
            <li style="position: relative;">
                <a href="<?php echo htmlspecialchars($basePath); ?>/pages/livestreams.php"
                   class="nav-link <?php echo ($currentPage === 'livestreams') ? 'active' : ''; ?>"
                   id="navLive">
                    <span class="material-icons-round" style="color:var(--red-500, #ef4444)">sensors</span>
                    <span class="nav-label">Live</span>
                    <span class="live-dot-indicator shadow-glow" id="liveDotIndicator" style="display:none; width: 8px; height: 8px; background: var(--red-500, #ef4444); border-radius: 50%; position: absolute; top: 12px; right: 12px; animation: pulse 2s infinite;"></span>
                </a>
            </li>
            <li>
                <a href="<?php echo htmlspecialchars($basePath); ?>/pages/messages.php"
                   class="nav-link <?php echo ($currentPage === 'messages') ? 'active' : ''; ?>"
                   id="navMessages">
                    <span class="material-icons-round">chat_bubble_outline</span>
                    <span class="nav-label">Messages</span>
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge-count" id="unreadBadge"><?php echo htmlspecialchars((string)$unreadCount); ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>

        <!-- Right: Notifications + User Dropdown -->
        <div class="nav-right">


            <!-- Notification Bell -->
            <button class="nav-icon-btn" id="notifBell" aria-label="Notifications">
                <span class="material-icons-round">notifications_none</span>
            </button>

            <!-- User Avatar + Dropdown -->
            <div class="nav-user" id="userMenu">
                <button class="avatar-circle avatar-md" id="userAvatarBtn" aria-label="User menu">
                    <?php if ($profilePic): ?>
                        <img src="<?php echo htmlspecialchars($uploads); ?>/profile_pics/<?php echo htmlspecialchars($profilePic); ?>"
                             alt="<?php echo htmlspecialchars($userName); ?>">
                    <?php else: ?>
                        <span class="avatar-initials"><?php echo htmlspecialchars($initials); ?></span>
                    <?php endif; ?>
                </button>

                <!-- Dropdown -->
                <div class="dropdown-menu glass-card" id="userDropdown">
                    <div class="dropdown-header">
                        <strong><?php echo htmlspecialchars($userName); ?></strong>
                        <span class="text-muted"><?php echo htmlspecialchars(ucfirst($userRole)); ?></span>
                    </div>
                    <div class="dropdown-divider"></div>
                    <a href="<?php echo htmlspecialchars($basePath); ?>/pages/profile.php?id=<?php echo htmlspecialchars((string)$userId); ?>" class="dropdown-item" id="dropdownProfile">
                        <span class="material-icons-round">person_outline</span> My Profile
                    </a>
                    <a href="<?php echo htmlspecialchars($basePath); ?>/pages/edit-profile.php" class="dropdown-item" id="dropdownSettings">
                        <span class="material-icons-round">settings</span> Settings
                    </a>
                    <?php if ($userRole === 'athlete' || $userRole === 'coach'): ?>
                        <a href="<?php echo htmlspecialchars($basePath); ?>/pages/apply_role.php" class="dropdown-item" id="dropdownApplyRole">
                            <span class="material-icons-round" style="color: var(--primary, #6366f1);">how_to_reg</span> Elevate Account
                        </a>
                    <?php endif; ?>
                    <?php if ($userRole === 'admin'): ?>
                        <?php 
                            $pendingRoles = 0;
                            if (isset($pdo)) {
                                try {
                                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                    $stmtRole = $pdo->query("SELECT COUNT(id) FROM role_applications WHERE status = 'pending'");
                                    $pendingRoles = (int)$stmtRole->fetchColumn();
                                } catch (PDOException $e) {
                                    error_log("Header pending roles error: " . $e->getMessage());
                                }
                            }
                        ?>
                        <div class="dropdown-divider"></div>
                        <a href="<?php echo htmlspecialchars($basePath); ?>/pages/admin/role_applications.php" class="dropdown-item" id="dropdownAdminRoles">
                            <span class="material-icons-round text-yellow-500">admin_panel_settings</span> Role Applications
                            <?php if ($pendingRoles > 0): ?>
                                <span class="bg-red-500 text-white text-[10px] px-1.5 py-0.5 rounded-full ml-auto"><?php echo htmlspecialchars((string)$pendingRoles); ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                    <div class="dropdown-divider"></div>
                    <a href="<?php echo htmlspecialchars($basePath); ?>/api/logout.php" class="dropdown-item dropdown-item--danger" id="dropdownLogout">
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
