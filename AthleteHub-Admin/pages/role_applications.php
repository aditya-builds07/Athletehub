<?php
/**
 * AthleteHub Admin — Role Applications Page
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle   = 'Role Applications';
$currentPage = 'role_applications';

$filter = $_GET['status'] ?? 'all';

$sql = "SELECT ra.*, u.name AS applicant_name, u.email AS applicant_email, u.profile_pic,
               r.name AS reviewer_name
        FROM role_applications ra
        JOIN users u ON ra.user_id = u.id
        LEFT JOIN users r ON ra.reviewed_by = r.id";

$params = [];
if ($filter !== 'all') {
    $sql .= " WHERE ra.status = ?";
    $params[] = $filter;
}
$sql .= " ORDER BY ra.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$applications = $stmt->fetchAll();

// Counts for tabs
$counts = ['all' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$cStmt = $pdo->query("SELECT status, COUNT(*) as cnt FROM role_applications GROUP BY status");
while ($row = $cStmt->fetch()) {
    $counts[$row['status']] = $row['cnt'];
    $counts['all'] += $row['cnt'];
}
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

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <?php foreach (['all','pending','approved','rejected'] as $tab): ?>
                    <a href="?status=<?php echo $tab; ?>"
                       class="filter-tab <?php echo $filter===$tab?'active':''; ?>">
                        <?php echo ucfirst($tab); ?> (<?php echo $counts[$tab]; ?>)
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Applicant</th><th>Requested Role</th><th>Organisation</th>
                            <th>Submitted</th><th>Status</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($applications)): ?>
                        <tr><td colspan="6"><div class="empty-state"><span class="material-icons-round">inbox</span><p>No applications found.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($applications as $a): ?>
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <div class="mini-avatar"><?php echo strtoupper(substr($a['applicant_name'],0,1)); ?></div>
                                    <div>
                                        <div style="font-weight:600"><?php echo htmlspecialchars($a['applicant_name']); ?></div>
                                        <div style="font-size:.75rem;color:var(--text-muted)"><?php echo htmlspecialchars($a['applicant_email']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge badge-<?php echo $a['requested_role']; ?>"><?php echo $a['requested_role']; ?></span></td>
                            <td><?php echo htmlspecialchars($a['organisation_name']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($a['created_at'])); ?></td>
                            <td><span class="badge badge-<?php echo $a['status']; ?>"><?php echo $a['status']; ?></span></td>
                            <td>
                                <button class="btn btn-outline btn-sm" onclick='reviewApp(<?php echo json_encode($a); ?>)'>
                                    <span class="material-icons-round">rate_review</span> Review
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Review Modal -->
<div class="modal-overlay" id="reviewModal">
    <div class="modal" style="max-width:650px;">
        <div class="modal-header">
            <h3>Review Application</h3>
            <button class="modal-close" onclick="closeModal('reviewModal')">&times;</button>
        </div>
        <div class="modal-body" id="reviewBody"></div>
        <div class="modal-footer" id="reviewFooter"></div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
const API = '<?php echo BASE_URL; ?>/api/role_applications.php';

function reviewApp(app) {
    const body = document.getElementById('reviewBody');
    const footer = document.getElementById('reviewFooter');

    let docPreview = '';
    if (app.document_path) {
        const ext = app.document_path.split('.').pop().toLowerCase();
        const docUrl = '/dashboard/AthleteHub V1.3/' + app.document_path;
        if (['jpg','jpeg','png','gif','webp'].includes(ext)) {
            docPreview = `<div style="margin-top:.5rem"><img src="${docUrl}" style="max-width:100%;border-radius:8px;border:1px solid var(--border-color)"></div>`;
        } else {
            docPreview = `<div style="margin-top:.5rem"><a href="${docUrl}" target="_blank" class="btn btn-outline btn-sm"><span class="material-icons-round">download</span> Download Document</a></div>`;
        }
    }

    body.innerHTML = `
        <div class="detail-row"><div class="detail-label">Applicant</div><div class="detail-value">${esc(app.applicant_name)}</div></div>
        <div class="detail-row"><div class="detail-label">Email</div><div class="detail-value">${esc(app.applicant_email)}</div></div>
        <div class="detail-row"><div class="detail-label">Requested Role</div><div class="detail-value"><span class="badge badge-${app.requested_role}">${app.requested_role}</span></div></div>
        <div class="detail-row"><div class="detail-label">Organisation</div><div class="detail-value">${esc(app.organisation_name)}</div></div>
        <div class="detail-row"><div class="detail-label">Description</div><div class="detail-value">${esc(app.description)}</div></div>
        <div class="detail-row"><div class="detail-label">Website</div><div class="detail-value">${app.website ? `<a href="${esc(app.website)}" target="_blank">${esc(app.website)}</a>` : '—'}</div></div>
        <div class="detail-row"><div class="detail-label">Phone</div><div class="detail-value">${esc(app.phone)}</div></div>
        <div class="detail-row"><div class="detail-label">Document</div><div class="detail-value">${esc(app.document_type || '—')}${docPreview}</div></div>
        <div class="detail-row"><div class="detail-label">Status</div><div class="detail-value"><span class="badge badge-${app.status}">${app.status}</span></div></div>
        ${app.admin_note ? `<div class="detail-row"><div class="detail-label">Admin Note</div><div class="detail-value">${esc(app.admin_note)}</div></div>` : ''}
        ${app.status === 'pending' ? `
        <div class="form-group" style="margin-top:1rem">
            <label>Admin Note (required for rejection)</label>
            <textarea class="form-control" id="adminNote" placeholder="Enter note..."></textarea>
        </div>` : ''}
    `;

    if (app.status === 'pending') {
        footer.innerHTML = `
            <button class="btn btn-outline" onclick="closeModal('reviewModal')">Cancel</button>
            <button class="btn btn-danger" onclick="rejectApp(${app.id})"><span class="material-icons-round">close</span> Reject</button>
            <button class="btn btn-success" onclick="approveApp(${app.id})"><span class="material-icons-round">check</span> Approve</button>
        `;
    } else {
        footer.innerHTML = `<button class="btn btn-outline" onclick="closeModal('reviewModal')">Close</button>`;
    }

    openModal('reviewModal');
}

async function approveApp(id) {
    if (!confirm('Approve this application? The user role will be upgraded.')) return;
    const fd = new FormData();
    fd.append('action', 'approve');
    fd.append('app_id', id);
    const res = await fetch(API, { method: 'POST', body: fd });
    const data = await res.json();
    showToast(data.message, data.success ? 'success' : 'error');
    if (data.success) setTimeout(() => location.reload(), 800);
}

async function rejectApp(id) {
    const note = document.getElementById('adminNote').value.trim();
    if (!note) { showToast('Admin note is required for rejection', 'error'); return; }

    const fd = new FormData();
    fd.append('action', 'reject');
    fd.append('app_id', id);
    fd.append('admin_note', note);
    const res = await fetch(API, { method: 'POST', body: fd });
    const data = await res.json();
    showToast(data.message, data.success ? 'success' : 'error');
    if (data.success) setTimeout(() => location.reload(), 800);
}

function esc(s) { if (!s) return ''; const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
function openModal(id)  { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
function showToast(msg, type='success') {
    const c = document.getElementById('toastContainer');
    const t = document.createElement('div');
    t.className = `toast toast-${type}`;
    t.innerHTML = `<span class="material-icons-round">${type==='success'?'check_circle':'error'}</span>${esc(msg)}`;
    c.appendChild(t);
    setTimeout(() => t.remove(), 3500);
}
document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', e => { if (e.target === el) el.classList.remove('active'); });
});
</script>
</body>
</html>
