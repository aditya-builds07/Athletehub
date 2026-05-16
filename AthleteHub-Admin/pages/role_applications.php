<?php
/**
 * AthleteHub Admin — Role Applications Page
 * Lists all role upgrade applications with filters, search, and pagination.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle   = 'Role Applications';
$currentPage = 'role_applications';

// ── Filters ──
$filter = $_GET['status'] ?? 'all';
$roleFilter = $_GET['role'] ?? 'all';
$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 15;
$offset = ($page - 1) * $limit;

// Whitelist filters
$allowed_status = ['all', 'pending', 'approved', 'rejected'];
$allowed_roles  = ['all', 'club', 'recruiter'];
if (!in_array($filter, $allowed_status, true)) $filter = 'all';
if (!in_array($roleFilter, $allowed_roles, true)) $roleFilter = 'all';

// ── Build dynamic query ──
$where  = ['1=1'];
$params = [];

if ($filter !== 'all') {
    $where[] = 'ra.status = ?';
    $params[] = $filter;
}
if ($roleFilter !== 'all') {
    $where[] = 'ra.requested_role = ?';
    $params[] = $roleFilter;
}
if ($search !== '') {
    $where[] = '(u.name LIKE ? OR ra.organisation_name LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSql = implode(' AND ', $where);

// Fetch applications
$stmt = $pdo->prepare("
    SELECT ra.*, u.name AS applicant_name, u.email AS applicant_email, u.profile_pic
    FROM role_applications ra
    JOIN users u ON ra.user_id = u.id
    WHERE $whereSql
    ORDER BY ra.created_at DESC
    LIMIT ? OFFSET ?
");
$paramIndex = 1;
foreach ($params as $val) {
    $stmt->bindValue($paramIndex++, $val, PDO::PARAM_STR);
}
$stmt->bindValue($paramIndex++, $limit, PDO::PARAM_INT);
$stmt->bindValue($paramIndex, $offset, PDO::PARAM_INT);
$stmt->execute();
$applications = $stmt->fetchAll();

// Total count for pagination
$countStmt = $pdo->prepare("
    SELECT COUNT(*) FROM role_applications ra
    JOIN users u ON ra.user_id = u.id
    WHERE $whereSql
");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $limit));

// Stats bar counts
$counts = ['all' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$cStmt = $pdo->query("SELECT status, COUNT(*) as cnt FROM role_applications GROUP BY status");
while ($row = $cStmt->fetch()) {
    $counts[$row['status']] = (int)$row['cnt'];
    $counts['all'] += (int)$row['cnt'];
}

// Helper for time ago
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M d, Y', $time);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> — AthleteHub Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/role_requests.css">
</head>
<body>
<div class="admin-layout">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="admin-main">
        <?php include __DIR__ . '/../includes/header.php'; ?>
        <div class="admin-content">

            <!-- Stats Bar -->
            <div class="stats-bar">
                <div class="stat-card">
                    <span class="material-icons-round" style="color: var(--primary, #6366f1)">assignment</span>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo $counts['all']; ?></div>
                        <div class="stat-label">Total</div>
                    </div>
                </div>
                <div class="stat-card">
                    <span class="material-icons-round" style="color: #eab308">hourglass_empty</span>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo $counts['pending']; ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                </div>
                <div class="stat-card">
                    <span class="material-icons-round" style="color: #22c55e">check_circle</span>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo $counts['approved']; ?></div>
                        <div class="stat-label">Approved</div>
                    </div>
                </div>
                <div class="stat-card">
                    <span class="material-icons-round" style="color: #ef4444">cancel</span>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo $counts['rejected']; ?></div>
                        <div class="stat-label">Rejected</div>
                    </div>
                </div>
            </div>

            <!-- Controls Row -->
            <div class="controls-row">
                <!-- Search -->
                <form method="GET" class="search-form">
                    <?php if ($filter !== 'all'): ?>
                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter); ?>">
                    <?php endif; ?>
                    <?php if ($roleFilter !== 'all'): ?>
                        <input type="hidden" name="role" value="<?php echo htmlspecialchars($roleFilter); ?>">
                    <?php endif; ?>
                    <span class="material-icons-round search-icon">search</span>
                    <input type="text" name="q" class="form-control search-input"
                           placeholder="Search by name or organisation..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </form>

                <!-- Role Filter -->
                <div class="role-filter">
                    <?php
                    $roleLabels = ['all' => 'All Roles', 'club' => 'Club', 'recruiter' => 'Recruiter'];
                    foreach ($roleLabels as $rv => $rl):
                        $params_str = http_build_query(array_filter([
                            'status' => $filter !== 'all' ? $filter : null,
                            'role' => $rv !== 'all' ? $rv : null,
                            'q' => $search ?: null
                        ]));
                    ?>
                        <a href="?<?php echo $params_str; ?>"
                           class="role-pill <?php echo $roleFilter === $rv ? 'active' : ''; ?>">
                            <?php echo $rl; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Status Filter Tabs -->
            <div class="filter-tabs">
                <?php foreach (['all', 'pending', 'approved', 'rejected'] as $tab):
                    $params_str = http_build_query(array_filter([
                        'status' => $tab !== 'all' ? $tab : null,
                        'role' => $roleFilter !== 'all' ? $roleFilter : null,
                        'q' => $search ?: null
                    ]));
                ?>
                    <a href="?<?php echo $params_str; ?>"
                       class="filter-tab <?php echo $filter === $tab ? 'active' : ''; ?>">
                        <?php echo ucfirst($tab); ?> (<?php echo $counts[$tab]; ?>)
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Applications Table -->
            <div class="card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Applicant</th>
                            <th>Requested Role</th>
                            <th>Organisation</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($applications)): ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <span class="material-icons-round">inbox</span>
                                    <p>No applications found matching your filters.</p>
                                    <a href="<?php echo BASE_URL; ?>/pages/role_applications.php" class="btn btn-outline btn-sm" style="margin-top: .5rem;">Clear Filters</a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($applications as $a):
                        // Generate initials
                        $parts = explode(' ', $a['applicant_name']);
                        $initials = strtoupper(substr($parts[0], 0, 1));
                        if (isset($parts[1])) $initials .= strtoupper(substr($parts[1], 0, 1));

                        // Initials color
                        $colors = ['#6366f1', '#8b5cf6', '#06b6d4', '#10b981', '#f59e0b', '#ef4444', '#ec4899'];
                        $ci = array_sum(array_map('ord', str_split($a['applicant_name']))) % count($colors);
                    ?>
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <div class="mini-avatar" style="background: <?php echo $colors[$ci]; ?>">
                                        <?php echo $initials; ?>
                                    </div>
                                    <div>
                                        <div style="font-weight:600"><?php echo htmlspecialchars($a['applicant_name']); ?></div>
                                        <div style="font-size:.75rem;color:var(--text-muted)"><?php echo htmlspecialchars($a['applicant_email']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge badge-<?php echo htmlspecialchars($a['requested_role']); ?>"><?php echo ucfirst(htmlspecialchars($a['requested_role'])); ?></span></td>
                            <td><?php echo htmlspecialchars($a['organisation_name']); ?></td>
                            <td title="<?php echo date('M d, Y H:i', strtotime($a['created_at'])); ?>"><?php echo timeAgo($a['created_at']); ?></td>
                            <td><span class="badge badge-<?php echo htmlspecialchars($a['status']); ?>"><?php echo ucfirst(htmlspecialchars($a['status'])); ?></span></td>
                            <td>
                                <a href="<?php echo BASE_URL; ?>/pages/role_request_detail.php?id=<?php echo (int)$a['id']; ?>"
                                   class="btn btn-outline btn-sm">
                                    <span class="material-icons-round">rate_review</span> Review
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php
                $baseParams = array_filter([
                    'status' => $filter !== 'all' ? $filter : null,
                    'role' => $roleFilter !== 'all' ? $roleFilter : null,
                    'q' => $search ?: null
                ]);
                ?>
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($baseParams, ['page' => $page - 1])); ?>" class="pagination-btn">&laquo; Prev</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?<?php echo http_build_query(array_merge($baseParams, ['page' => $i])); ?>"
                       class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo http_build_query(array_merge($baseParams, ['page' => $page + 1])); ?>" class="pagination-btn">Next &raquo;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>
</body>
</html>
