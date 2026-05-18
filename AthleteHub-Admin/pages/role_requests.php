<?php
/**
 * AthleteHub Admin — Role Requests List Page
 * Shows all role applications with search, status/role filters, and pagination.
 */
session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle   = 'Role Requests';
$currentPage = 'role_requests';

/* ── Pagination ────────────────────────────────────────── */
$perPage = 15;
$page    = max(1, (int) ($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

/* ── Filters ────────────────────────────────────────────── */
$statusFilter = $_GET['status'] ?? 'all';
$roleFilter   = $_GET['role']   ?? 'all';
$search       = trim($_GET['q'] ?? '');

$validStatuses = ['all', 'pending', 'approved', 'rejected'];
$validRoles    = ['all', 'club', 'recruiter'];
if (!in_array($statusFilter, $validStatuses, true)) $statusFilter = 'all';
if (!in_array($roleFilter, $validRoles, true)) $roleFilter = 'all';

/* ── Build Query ────────────────────────────────────────── */
$where  = [];
$params = [];

if ($statusFilter !== 'all') {
    $where[]  = 'ra.status = ?';
    $params[] = $statusFilter;
}
if ($roleFilter !== 'all') {
    $where[]  = 'ra.requested_role = ?';
    $params[] = $roleFilter;
}
if ($search !== '') {
    $where[]  = '(u.name LIKE ? OR u.email LIKE ? OR ra.organisation_name LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* ── Count total rows ───────────────────────────────────── */
$countSQL  = "SELECT COUNT(*) FROM role_applications ra JOIN users u ON ra.user_id = u.id $whereSQL";
$countStmt = $pdo->prepare($countSQL);
$countStmt->execute($params);
$totalRows  = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));

/* ── Fetch page rows ────────────────────────────────────── */
$sql = "SELECT ra.id, ra.requested_role, ra.organisation_name, ra.status,
               ra.submitted_at, ra.created_at,
               u.name AS applicant_name, u.email AS applicant_email,
               u.profile_pic
        FROM role_applications ra
        JOIN users u ON ra.user_id = u.id
        $whereSQL
        ORDER BY ra.submitted_at DESC, ra.created_at DESC
        LIMIT $perPage OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$applications = $stmt->fetchAll();

/* ── Stat Counts ────────────────────────────────────────── */
$counts = ['all' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$cStmt  = $pdo->query("SELECT status, COUNT(*) AS cnt FROM role_applications GROUP BY status");
while ($row = $cStmt->fetch()) {
    $counts[$row['status']] = (int) $row['cnt'];
    $counts['all'] += (int) $row['cnt'];
}

/* ── URL helper ─────────────────────────────────────────── */
function buildUrl(array $override = []): string {
    $base = [
        'status' => $_GET['status'] ?? 'all',
        'role'   => $_GET['role']   ?? 'all',
        'q'      => $_GET['q']      ?? '',
        'page'   => $_GET['page']   ?? '1',
    ];
    $merged = array_merge($base, $override);
    $merged = array_filter($merged, fn($v) => $v !== '' && $v !== 'all' && $v !== '1');
    return '?' . http_build_query($merged);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?> — AthleteHub Admin</title>
    <meta name="description" content="Review and manage athlete role upgrade applications.">
    <meta name="admin-csrf" content="<?php echo e($_SESSION['admin_csrf'] ?? ''); ?>">
    <meta name="rr-api-url" content="<?php echo e(BASE_URL); ?>/api/handle_role.php">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo e(ASSETS); ?>/css/admin.css">
    <link rel="stylesheet" href="<?php echo e(ASSETS); ?>/css/role_requests.css">
</head>
<body>
<div class="admin-layout">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="admin-main">
        <?php include __DIR__ . '/../includes/header.php'; ?>
        <div class="admin-content">

            <!-- Page Header -->
            <div class="rr-page-header">
                <div>
                    <h1>Role Requests</h1>
                    <p>Review and manage athlete role upgrade applications.</p>
                </div>
            </div>

            <!-- Stats -->
            <div class="rr-stats">
                <div class="rr-stat rr-stat--total">
                    <div class="rr-stat-icon"><span class="material-icons-round">assignment</span></div>
                    <div class="rr-stat-body">
                        <div class="rr-stat-value"><?php echo $counts['all']; ?></div>
                        <div class="rr-stat-label">Total</div>
                    </div>
                </div>
                <div class="rr-stat rr-stat--pending">
                    <div class="rr-stat-icon"><span class="material-icons-round">hourglass_empty</span></div>
                    <div class="rr-stat-body">
                        <div class="rr-stat-value"><?php echo $counts['pending']; ?></div>
                        <div class="rr-stat-label">Pending</div>
                    </div>
                </div>
                <div class="rr-stat rr-stat--approved">
                    <div class="rr-stat-icon"><span class="material-icons-round">check_circle</span></div>
                    <div class="rr-stat-body">
                        <div class="rr-stat-value"><?php echo $counts['approved']; ?></div>
                        <div class="rr-stat-label">Approved</div>
                    </div>
                </div>
                <div class="rr-stat rr-stat--rejected">
                    <div class="rr-stat-icon"><span class="material-icons-round">cancel</span></div>
                    <div class="rr-stat-body">
                        <div class="rr-stat-value"><?php echo $counts['rejected']; ?></div>
                        <div class="rr-stat-label">Rejected</div>
                    </div>
                </div>
            </div>

            <!-- Filter Tabs (Status) -->
            <div class="filter-tabs">
                <?php foreach (['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $val => $label): ?>
                    <a href="<?php echo buildUrl(['status' => $val, 'page' => '1']); ?>"
                       class="filter-tab <?php echo $statusFilter === $val ? 'active' : ''; ?>">
                        <?php echo e($label); ?> (<?php echo $counts[$val]; ?>)
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Toolbar -->
            <div class="rr-toolbar">
                <div class="search-box">
                    <span class="material-icons-round">search</span>
                    <input type="text"
                           id="rrSearchInput"
                           placeholder="Search by name, username or organisation…"
                           value="<?php echo e($search); ?>"
                           aria-label="Search applications">
                </div>
                <div class="rr-toolbar-right">
                    <select id="rrRoleFilter" class="select-styled" aria-label="Filter by role"
                            onchange="window.location = buildUrlFromSelect(this.value)">
                        <option value="all"      <?php echo $roleFilter === 'all'       ? 'selected' : ''; ?>>All Roles</option>
                        <option value="club"     <?php echo $roleFilter === 'club'      ? 'selected' : ''; ?>>Club</option>
                        <option value="recruiter"<?php echo $roleFilter === 'recruiter' ? 'selected' : ''; ?>>Recruiter</option>
                    </select>
                </div>
            </div>

            <!-- Table -->
            <div class="card">
                <table class="data-table" id="rrTable">
                    <thead>
                        <tr>
                            <th>Applicant</th>
                            <th>Requested Role</th>
                            <th>Organisation</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($applications)): ?>
                        <tr id="rrEmptyState">
                            <td colspan="6">
                                <div class="empty-state">
                                    <span class="material-icons-round">inbox</span>
                                    <p>No applications found.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($applications as $a):
                            $dateDisplay = $a['submitted_at'] ?? $a['created_at'];
                            $searchData  = strtolower($a['applicant_name'] . ' ' . $a['applicant_email'] . ' ' . $a['organisation_name']);
                        ?>
                        <tr class="rr-table-row" data-search="<?php echo e($searchData); ?>">
                            <td>
                                <div class="user-cell">
                                    <div class="mini-avatar">
                                        <?php if (!empty($a['profile_pic'])): ?>
                                            <img src="<?php echo e(MAIN_APP_URL); ?>/uploads/profile_pics/<?php echo e($a['profile_pic']); ?>" alt="">
                                        <?php else: ?>
                                            <?php echo e(strtoupper(substr($a['applicant_name'], 0, 1))); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div style="font-weight:600"><?php echo e($a['applicant_name']); ?></div>
                                        <div style="font-size:.75rem;color:var(--text-muted)"><?php echo e($a['applicant_email']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-role-<?php echo e($a['requested_role']); ?>">
                                    <?php echo e(ucfirst($a['requested_role'])); ?>
                                </span>
                            </td>
                            <td><?php echo e($a['organisation_name']); ?></td>
                            <td style="white-space:nowrap"><?php echo $dateDisplay ? e(date('M d, Y', strtotime($dateDisplay))) : '—'; ?></td>
                            <td>
                                <span class="badge badge-status-<?php echo e($a['status']); ?>">
                                    <?php echo e(ucfirst($a['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo e(BASE_URL); ?>/pages/role_request_detail.php?id=<?php echo (int)$a['id']; ?>"
                                   class="rr-review-link"
                                   id="review-btn-<?php echo (int)$a['id']; ?>">
                                    <span class="material-icons-round">rate_review</span>
                                    Review
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr id="rrEmptyState" style="display:none">
                            <td colspan="6">
                                <div class="empty-state">
                                    <span class="material-icons-round">search_off</span>
                                    <p>No results match your search.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="rr-pagination">
                    <div class="rr-pagination-info">
                        Showing <?php echo $offset + 1; ?>–<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo $totalRows; ?> results
                    </div>
                    <div class="rr-pagination-links">
                        <?php if ($page > 1): ?>
                            <a href="<?php echo buildUrl(['page' => $page - 1]); ?>" class="rr-page-btn" aria-label="Previous">
                                <span class="material-icons-round" style="font-size:16px">chevron_left</span>
                            </a>
                        <?php endif; ?>

                        <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                            <a href="<?php echo buildUrl(['page' => $p]); ?>"
                               class="rr-page-btn <?php echo $p === $page ? 'active' : ''; ?>"
                               aria-label="Page <?php echo $p; ?>">
                                <?php echo $p; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="<?php echo buildUrl(['page' => $page + 1]); ?>" class="rr-page-btn" aria-label="Next">
                                <span class="material-icons-round" style="font-size:16px">chevron_right</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div><!-- /card -->

        </div><!-- /admin-content -->
    </div><!-- /admin-main -->
</div><!-- /admin-layout -->

<!-- Confirm Modal (reused from role_requests.js) -->
<div class="modal-overlay" id="rrConfirmModal" role="dialog" aria-modal="true" aria-labelledby="rrConfirmTitle">
    <div class="modal" style="max-width:440px">
        <div class="modal-body" style="text-align:center;padding-top:2rem">
            <div class="rr-confirm-modal-icon" id="rrConfirmIcon"></div>
            <div class="rr-confirm-modal-title" id="rrConfirmTitle">Confirm Action</div>
            <div class="rr-confirm-modal-subtitle" id="rrConfirmSubtitle"></div>
        </div>
        <div class="modal-footer" style="justify-content:center;gap:1rem">
            <button class="btn btn-outline" id="rrCancelBtn" type="button">Cancel</button>
            <button class="btn btn-success" id="rrConfirmBtn" type="button">Confirm</button>
        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
// Role filter select → navigate
function buildUrlFromSelect(role) {
    const url = new URL(window.location.href);
    if (role === 'all') url.searchParams.delete('role');
    else url.searchParams.set('role', role);
    url.searchParams.set('page', '1');
    return url.toString();
}

// Search input → submit via GET for server-side filter on Enter
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('rrSearchInput');
    if (searchInput) {
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                const url = new URL(window.location.href);
                const val = searchInput.value.trim();
                if (val) url.searchParams.set('q', val);
                else url.searchParams.delete('q');
                url.searchParams.set('page', '1');
                window.location = url.toString();
            }
        });
    }
});
</script>
<script src="<?php echo e(ASSETS); ?>/js/role_requests.js"></script>
</body>
</html>
