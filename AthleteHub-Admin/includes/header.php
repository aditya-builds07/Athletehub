<?php
/**
 * AthleteHub Admin — Header (Top Bar)
 * Shows page title, notification bell, and admin user info.
 * Expects: $pageTitle (string)
 */

$adminName  = $_SESSION['user_name']  ?? 'Admin';
$adminEmail = $_SESSION['user_email'] ?? '';
$adminPic   = $_SESSION['user_pic']   ?? '';

$nameParts = explode(' ', $adminName);
$initials  = strtoupper(substr($nameParts[0], 0, 1));
if (isset($nameParts[1])) $initials .= strtoupper(substr($nameParts[1], 0, 1));
?>

<div class="admin-header" id="adminHeader">
    <div class="header-left">
        <button class="hamburger-btn" id="hamburgerBtn" aria-label="Toggle sidebar">
            <span class="material-icons-round">menu</span>
        </button>
        <h2><?php echo htmlspecialchars($pageTitle ?? 'Dashboard'); ?></h2>
    </div>

    <div class="header-right">
        <button class="header-icon-btn" id="headerNotifBtn" aria-label="Notifications">
            <span class="material-icons-round">notifications_none</span>
        </button>

        <div class="header-user">
            <div class="header-avatar">
                <?php if ($adminPic): ?>
                    <img src="/dashboard/AthleteHub V1.3/uploads/profile_pics/<?php echo htmlspecialchars($adminPic); ?>" alt="Admin">
                <?php else: ?>
                    <?php echo $initials; ?>
                <?php endif; ?>
            </div>
            <div class="header-user-info">
                <div class="header-user-name"><?php echo htmlspecialchars($adminName); ?></div>
                <div class="header-user-role">Administrator</div>
            </div>
        </div>
    </div>
</div>
