<?php
/**
 * AthleteHub Admin — Dashboard Page
 * Overview stats + recent users + quick actions.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle   = 'Dashboard';
$currentPage = 'dashboard';

// ── Stat queries ──
$stats = [];

$queries = [
    'total_users'       => "SELECT COUNT(*) FROM users",
    'total_athletes'    => "SELECT COUNT(*) FROM users WHERE role='athlete'",
    'total_coaches'     => "SELECT COUNT(*) FROM users WHERE role='coach'",
    'total_clubs'       => "SELECT COUNT(*) FROM users WHERE role='club'",
    'total_recruiters'  => "SELECT COUNT(*) FROM users WHERE role='recruiter'",
    'pending_apps'      => "SELECT COUNT(*) FROM role_applications WHERE status='pending'",
    'active_streams'    => "SELECT COUNT(*) FROM live_streams WHERE status='live'",
    'open_tournaments'  => "SELECT COUNT(*) FROM tournaments WHERE status='open'",
];

foreach ($queries as $key => $sql) {
    try {
        $stats[$key] = (int) $pdo->query($sql)->fetchColumn();
    } catch (PDOException $e) {
        $stats[$key] = 0;
    }
}

// Recent 10 users
$recentUsers = $pdo->query("SELECT id, name, email, role, is_verified, created_at FROM users ORDER BY created_at DESC LIMIT 10")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> — AthleteHub Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin.css">
</head>
<body>
<div class="admin-layout">

    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="admin-main">
        <?php include __DIR__ . '/../includes/header.php'; ?>

        <div class="admin-content">

            <!-- Stats Grid -->
            <div class="stats-grid">
                <?php
                $cards = [
                    ['label'=>'Total Users',      'value'=>$stats['total_users'],      'icon'=>'group',          'color'=>'#6366f1'],
                    ['label'=>'Athletes',          'value'=>$stats['total_athletes'],   'icon'=>'directions_run', 'color'=>'#06b6d4'],
                    ['label'=>'Coaches',           'value'=>$stats['total_coaches'],    'icon'=>'school',         'color'=>'#eab308'],
                    ['label'=>'Clubs',             'value'=>$stats['total_clubs'],      'icon'=>'sports_soccer',  'color'=>'#ec4899'],
                    ['label'=>'Recruiters',        'value'=>$stats['total_recruiters'], 'icon'=>'person_search',  'color'=>'#f97316'],
                    ['label'=>'Pending Applications','value'=>$stats['pending_apps'],   'icon'=>'pending_actions','color'=>'#ef4444'],
                    ['label'=>'Active Streams',    'value'=>$stats['active_streams'],   'icon'=>'sensors',        'color'=>'#22c55e'],
                    ['label'=>'Open Tournaments',  'value'=>$stats['open_tournaments'], 'icon'=>'emoji_events',   'color'=>'#8b5cf6'],
                ];
                foreach ($cards as $c): ?>
                    <div class="stat-card" style="--card-accent: <?php echo $c['color']; ?>">
                        <div class="stat-icon" style="background:<?php echo $c['color']; ?>20; color:<?php echo $c['color']; ?>">
                            <span class="material-icons-round"><?php echo $c['icon']; ?></span>
                        </div>
                        <div class="stat-value"><?php echo number_format($c['value']); ?></div>
                        <div class="stat-label"><?php echo $c['label']; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="<?php echo BASE_URL; ?>/pages/role_applications.php" class="btn btn-warning">
                    <span class="material-icons-round">assignment</span>
                    View Pending Applications (<?php echo $stats['pending_apps']; ?>)
                </a>
                <a href="<?php echo BASE_URL; ?>/pages/users.php" class="btn btn-primary">
                    <span class="material-icons-round">group</span>
                    Manage Users
                </a>
            </div>

            <!-- Recent Users Table -->
            <div class="card">
                <div class="card-header">
                    <h3>Recent Registrations</h3>
                    <a href="<?php echo BASE_URL; ?>/pages/users.php" class="btn btn-outline btn-sm">View All</a>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Verified</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsers as $u): ?>
                        <tr>
                            <td>#<?php echo $u['id']; ?></td>
                            <td><?php echo htmlspecialchars($u['name']); ?></td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td><span class="badge badge-<?php echo $u['role']; ?>"><?php echo $u['role']; ?></span></td>
                            <td>
                                <?php if ($u['is_verified']): ?>
                                    <span class="material-icons-round verified-icon">verified</span>
                                <?php else: ?>
                                    <span class="material-icons-round unverified-icon">cancel</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>
</body>
</html>
