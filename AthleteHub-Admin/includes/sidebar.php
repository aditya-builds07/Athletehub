<?php
/**
 * AthleteHub Admin — Sidebar Navigation
 * Highlights the active page link.
 * Expects: $currentPage (string) set before include.
 */

// Count pending role applications for badge
$pendingCount = 0;
if (isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM role_applications WHERE status = 'pending'");
        $pendingCount = (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        $pendingCount = 0;
    }
}

$menuItems = [
    ['page' => 'dashboard',         'icon' => 'dashboard',           'label' => 'Dashboard'],
    ['page' => 'users',             'icon' => 'group',               'label' => 'Users'],
    ['page' => 'role_requests',     'icon' => 'assignment_ind',       'label' => 'Role Requests',     'badge' => $pendingCount],
    ['page' => 'recruitment',       'icon' => 'work',                'label' => 'Recruitment Posts'],
    ['page' => 'tournaments',       'icon' => 'emoji_events',        'label' => 'Tournaments'],
    ['page' => 'livestreams',       'icon' => 'sensors',             'label' => 'Live Streams'],
    ['page' => 'news',              'icon' => 'newspaper',           'label' => 'News'],
    ['page' => 'reports',           'icon' => 'flag',                'label' => 'Reports'],
];
?>

<!-- Sidebar overlay for mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="admin-sidebar" id="adminSidebar">
    <!-- Logo -->
    <div class="sidebar-logo">
        <div class="logo-icon">
            <span class="material-icons-round">shield</span>
        </div>
        <div class="logo-text">Athlete<span>Hub</span></div>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <div class="sidebar-section-title">Main Menu</div>

        <?php foreach ($menuItems as $item): ?>
            <a href="<?php echo BASE_URL; ?>/pages/<?php echo $item['page']; ?>.php"
               class="sidebar-link <?php echo ($currentPage === $item['page']) ? 'active' : ''; ?>"
               id="nav-<?php echo $item['page']; ?>">
                <span class="material-icons-round"><?php echo $item['icon']; ?></span>
                <?php echo $item['label']; ?>
                <?php if (!empty($item['badge']) && $item['badge'] > 0): ?>
                    <span class="sidebar-badge"><?php echo $item['badge']; ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Footer -->
    <div class="sidebar-footer">
        <a href="<?php echo BASE_URL; ?>/api/logout.php" class="sidebar-link" id="nav-logout">
            <span class="material-icons-round">logout</span>
            Logout
        </a>
    </div>
</aside>

<script>
/* Sidebar toggle for mobile/tablet */
document.addEventListener('DOMContentLoaded', () => {
    const sidebar  = document.getElementById('adminSidebar');
    const overlay  = document.getElementById('sidebarOverlay');
    const burger   = document.getElementById('hamburgerBtn');

    if (burger) {
        burger.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        });
    }
});
</script>
