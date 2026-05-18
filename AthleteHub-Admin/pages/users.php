<?php
/**
 * AthleteHub Admin — Users Management Page
 */
require_once __DIR__ . '/../includes/auth_check.php';

$pageTitle   = 'Users Management';
$currentPage = 'users';

// Initial load
$role = $_GET['role'] ?? 'all';
$search = $_GET['q'] ?? '';

$sql = "SELECT id, name, email, role, is_verified, created_at, IFNULL(suspended,0) AS suspended FROM users WHERE 1=1";
$params = [];

if ($role && $role !== 'all') {
    $sql .= " AND role = ?";
    $params[] = $role;
}

if ($search) {
    $sql .= " AND (name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?> — AthleteHub Admin</title>
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

            <!-- Toolbar -->
            <div class="toolbar">
                <div class="search-box" style="min-width:280px;">
                    <span class="material-icons-round">search</span>
                    <input type="text" id="userSearch" placeholder="Search by name or email..." value="<?php echo e($search); ?>">
                </div>

                <select class="select-styled" id="roleFilter">
                    <option value="all" <?php echo $role==='all'?'selected':''; ?>>All Roles</option>
                    <option value="athlete" <?php echo $role==='athlete'?'selected':''; ?>>Athlete</option>
                    <option value="coach" <?php echo $role==='coach'?'selected':''; ?>>Coach</option>
                    <option value="club" <?php echo $role==='club'?'selected':''; ?>>Club</option>
                    <option value="recruiter" <?php echo $role==='recruiter'?'selected':''; ?>>Recruiter</option>
                    <option value="admin" <?php echo $role==='admin'?'selected':''; ?>>Admin</option>
                </select>
                <div class="toolbar-spacer"></div>
                <span style="color:var(--text-muted);font-size:.85rem;" id="userCount"><?php echo count($users); ?> users</span>
            </div>

            <!-- Users Table -->
            <div class="card">
                <table class="data-table" id="usersTable">
                    <thead>
                        <tr>
                            <th>ID</th><th>Name</th><th>Email</th><th>Role</th>
                            <th>Verified</th><th>Status</th><th>Joined</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="usersBody">
                        <?php foreach ($users as $u): ?>
                        <tr data-id="<?php echo e($u['id']); ?>">
                            <td>#<?php echo e($u['id']); ?></td>
                            <td><?php echo e($u['name']); ?></td>
                            <td><?php echo e($u['email']); ?></td>
                            <td><span class="badge badge-<?php echo e($u['role']); ?>"><?php echo e($u['role']); ?></span></td>
                            <td>
                                <?php if ($u['is_verified']): ?>
                                    <span class="material-icons-round verified-icon">verified</span>
                                <?php else: ?>
                                    <span class="material-icons-round unverified-icon">cancel</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['suspended']): ?>
                                    <span class="badge badge-rejected">Suspended</span>
                                <?php else: ?>
                                    <span class="badge badge-active">Active</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn btn-outline btn-sm" onclick="viewUser(<?php echo e($u['id']); ?>)" title="View">
                                        <span class="material-icons-round">visibility</span>
                                    </button>
                                    <button class="btn btn-outline btn-sm" onclick="openRoleModal(<?php echo e($u['id']); ?>, '<?php echo e($u['role']); ?>')" title="Change Role">
                                        <span class="material-icons-round">swap_horiz</span>
                                    </button>
                                    <?php if (!$u['suspended']): ?>
                                    <button class="btn btn-warning btn-sm" onclick="suspendUser(<?php echo e($u['id']); ?>, 1, this)" title="Suspend">
                                        <span class="material-icons-round">block</span>
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-success btn-sm" onclick="suspendUser(<?php echo e($u['id']); ?>, 0, this)" title="Unsuspend">
                                        <span class="material-icons-round">check_circle</span>
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn btn-danger btn-sm" onclick="deleteUser(<?php echo e($u['id']); ?>, this)" title="Delete">
                                        <span class="material-icons-round">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>

<!-- View User Modal -->
<div class="modal-overlay" id="viewUserModal">
    <div class="modal">
        <div class="modal-header">
            <h3>User Details</h3>
            <button class="modal-close" onclick="closeModal('viewUserModal')">&times;</button>
        </div>
        <div class="modal-body" id="viewUserBody">Loading...</div>
    </div>
</div>

<!-- Change Role Modal -->
<div class="modal-overlay" id="roleModal">
    <div class="modal" style="max-width:400px;">
        <div class="modal-header">
            <h3>Change User Role</h3>
            <button class="modal-close" onclick="closeModal('roleModal')">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="roleUserId">
            <div class="form-group">
                <label>New Role</label>
                <select class="form-control" id="newRoleSelect">
                    <option value="athlete">Athlete</option>
                    <option value="coach">Coach</option>
                    <option value="club">Club</option>
                    <option value="recruiter">Recruiter</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('roleModal')">Cancel</button>
            <button class="btn btn-primary" onclick="saveRole(this)">Save Role</button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<script>
const API = '<?php echo BASE_URL; ?>/api/users.php';
const CSRF = '<?php echo $_SESSION['csrf_token']; ?>';

// ── Live Search ──
let searchTimer;
document.getElementById('userSearch').addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        const q    = this.value;
        const role = document.getElementById('roleFilter').value;
        window.location.href = `?q=${encodeURIComponent(q)}&role=${role}`;
    }, 500);
});

document.getElementById('roleFilter').addEventListener('change', function() {
    const q = document.getElementById('userSearch').value;
    window.location.href = `?q=${encodeURIComponent(q)}&role=${this.value}`;
});

// ── View User ──
async function viewUser(id) {
    const body = document.getElementById('viewUserBody');
    body.innerHTML = 'Loading...';
    openModal('viewUserModal');

    const fd = new FormData();
    fd.append('action', 'view');
    fd.append('user_id', id);
    fd.append('csrf_token', CSRF);

    try {
        const res = await fetch(API, { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            const u = data.user;
            body.innerHTML = `
                <div class="detail-row"><div class="detail-label">ID</div><div class="detail-value">#${esc(u.id)}</div></div>
                <div class="detail-row"><div class="detail-label">Name</div><div class="detail-value">${esc(u.name)}</div></div>
                <div class="detail-row"><div class="detail-label">Email</div><div class="detail-value">${esc(u.email)}</div></div>
                <div class="detail-row"><div class="detail-label">Role</div><div class="detail-value"><span class="badge badge-${esc(u.role)}">${esc(u.role)}</span></div></div>
                <div class="detail-row"><div class="detail-label">Sport</div><div class="detail-value">${esc(u.sport || '—')}</div></div>
                <div class="detail-row"><div class="detail-label">Location</div><div class="detail-value">${esc(u.location || '—')}</div></div>
                <div class="detail-row"><div class="detail-label">Bio</div><div class="detail-value">${esc(u.bio || '—')}</div></div>
                <div class="detail-row"><div class="detail-label">Verified</div><div class="detail-value">${u.is_verified ? '✅ Yes' : '❌ No'}</div></div>
                <div class="detail-row"><div class="detail-label">Followers</div><div class="detail-value">${esc(u.followers_count)}</div></div>
                <div class="detail-row"><div class="detail-label">Following</div><div class="detail-value">${esc(u.following_count)}</div></div>
                <div class="detail-row"><div class="detail-label">Joined</div><div class="detail-value">${esc(u.created_at)}</div></div>`;
        } else {
            body.innerHTML = `<p style="color:var(--red)">${esc(data.message || 'User not found.')}</p>`;
        }
    } catch (e) {
        body.innerHTML = '<p style="color:var(--red)">A server error occurred.</p>';
    }
}

// ── Change Role ──
function openRoleModal(id, currentRole) {
    document.getElementById('roleUserId').value = id;
    document.getElementById('newRoleSelect').value = currentRole;
    openModal('roleModal');
}

async function saveRole(btn) {
    btn.disabled = true;
    const fd = new FormData();
    fd.append('action', 'change_role');
    fd.append('user_id', document.getElementById('roleUserId').value);
    fd.append('new_role', document.getElementById('newRoleSelect').value);
    fd.append('csrf_token', CSRF);

    try {
        const res  = await fetch(API, { method: 'POST', body: fd });
        const data = await res.json();
        showToast(data.message || (data.success ? 'Success' : 'Error'), data.success ? 'success' : 'error');
        if (data.success) setTimeout(() => location.reload(), 800);
    } catch (e) {
        showToast('A server error occurred', 'error');
    } finally {
        btn.disabled = false;
        closeModal('roleModal');
    }
}

// ── Suspend ──
async function suspendUser(id, value, btn) {
    if (!confirm(value ? 'Suspend this user?' : 'Unsuspend this user?')) return;
    btn.disabled = true;
    const fd = new FormData();
    fd.append('action', 'suspend');
    fd.append('user_id', id);
    fd.append('value', value);
    fd.append('csrf_token', CSRF);

    try {
        const res  = await fetch(API, { method: 'POST', body: fd });
        const data = await res.json();
        showToast(data.message || (data.success ? 'Success' : 'Error'), data.success ? 'success' : 'error');
        if (data.success) setTimeout(() => location.reload(), 800);
        else btn.disabled = false;
    } catch (e) {
        showToast('A server error occurred', 'error');
        btn.disabled = false;
    }
}

// ── Delete ──
async function deleteUser(id, btn) {
    if (!confirm('Are you sure you want to DELETE this user? This cannot be undone.')) return;
    btn.disabled = true;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('user_id', id);
    fd.append('csrf_token', CSRF);

    try {
        const res  = await fetch(API, { method: 'POST', body: fd });
        const data = await res.json();
        showToast(data.message || (data.success ? 'Success' : 'Error'), data.success ? 'success' : 'error');
        if (data.success) setTimeout(() => location.reload(), 800);
        else btn.disabled = false;
    } catch (e) {
        showToast('A server error occurred', 'error');
        btn.disabled = false;
    }
}

// ── Helpers ──
function esc(s) { 
    if (!s) return '—';
    const d = document.createElement('div'); 
    d.textContent = String(s); 
    return d.innerHTML; 
}

function openModal(id)  { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

function showToast(msg, type = 'success') {
    const c = document.getElementById('toastContainer');
    const t = document.createElement('div');
    t.className = `toast toast-${type}`;
    t.innerHTML = `<span class="material-icons-round">${type==='success'?'check_circle':'error'}</span>${esc(msg)}`;
    c.appendChild(t);
    setTimeout(() => t.remove(), 3500);
}

// Close modals on overlay click
document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', e => { if (e.target === el) el.classList.remove('active'); });
});
</script>
</body>
</html>
