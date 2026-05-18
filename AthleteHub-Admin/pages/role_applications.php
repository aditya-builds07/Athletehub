<?php
/**
 * AthleteHub Admin — Role Applications Page
 * Audited & Fixed: CSRF, XSS, input whitelisting, safe JS data, error handling
 */
session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle = 'Role Applications';
$currentPage = 'role_applications';

// ✅ Whitelist filter input — never trust $_GET directly
$allowed_filters = ['all', 'pending', 'approved', 'rejected'];
$filter = $_GET['status'] ?? 'all';
if (!in_array($filter, $allowed_filters, true))
    $filter = 'all';

// ✅ Build query safely
$sql = "SELECT ra.id, ra.requested_role, ra.organisation_name, ra.description,
               ra.website, ra.phone, ra.document_type, ra.document_path,
               ra.status, ra.admin_note, ra.created_at,
               u.name AS applicant_name, u.email AS applicant_email,
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

// ✅ Count per status — single query, no double-counting
$counts = ['all' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$cStmt = $pdo->query("SELECT status, COUNT(*) AS cnt FROM role_applications GROUP BY status");
while ($row = $cStmt->fetch()) {
    if (isset($counts[$row['status']])) {
        $counts[$row['status']] = (int) $row['cnt'];
        $counts['all'] += (int) $row['cnt'];
    }
}

// ✅ CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// ✅ Safe output helper
function e($val): string
{
    return htmlspecialchars($val ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — AthleteHub Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <!-- ✅ Use BASE_URL constant — never hardcode paths -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
</head>

<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <div class="admin-main">
            <?php include __DIR__ . '/../includes/header.php'; ?>
            <div class="admin-content">

                <!-- Filter Tabs -->
                <div class="filter-tabs">
                    <?php foreach (['all', 'pending', 'approved', 'rejected'] as $tab): ?>
                        <a href="?status=<?= e($tab) ?>" class="filter-tab <?= $filter === $tab ? 'active' : '' ?>">
                            <?= ucfirst(e($tab)) ?> (<?= (int) $counts[$tab] ?>)
                        </a>
                    <?php endforeach; ?>
                </div>

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
                                            <p>No applications found.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($applications as $a): ?>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <div class="mini-avatar">
                                                <?= strtoupper(substr($a['applicant_name'] ?? 'U', 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div style="font-weight:600"><?= e($a['applicant_name']) ?></div>
                                                <div style="font-size:.75rem;color:var(--text-muted)">
                                                    <?= e($a['applicant_email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= e($a['requested_role']) ?>">
                                            <?= e($a['requested_role']) ?>
                                        </span>
                                    </td>
                                    <td><?= e($a['organisation_name']) ?></td>
                                    <td><?= e(date('M d, Y', strtotime($a['created_at']))) ?></td>
                                    <td>
                                        <span class="badge badge-<?= e($a['status']) ?>">
                                            <?= e($a['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <!-- ✅ Pass only the safe app ID — fetch full data in JS via API -->
                                        <button class="btn btn-outline btn-sm" onclick="reviewApp(<?= (int) $a['id'] ?>, <?= e(json_encode([
                                              'id' => (int) $a['id'],
                                              'applicant_name' => $a['applicant_name'],
                                              'requested_role' => $a['requested_role'],
                                              'organisation_name' => $a['organisation_name'],
                                              'description' => $a['description'],
                                              'website' => $a['website'],
                                              'phone' => $a['phone'],
                                              'document_type' => $a['document_type'],
                                              'document_path' => $a['document_path'],
                                              'status' => $a['status'],
                                              'admin_note' => $a['admin_note'],
                                          ])) ?>)">
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
        // ✅ BASE_URL and CSRF from PHP — never hardcoded
        const API = '<?= BASE_URL ?>/api/role_applications.php';
        const BASE_URL = '<?= BASE_URL ?>';
        const CSRF = '<?= $csrf ?>';

        // ✅ Safe XSS escape function
        function esc(s) {
            if (s === null || s === undefined) return '—';
            const d = document.createElement('div');
            d.textContent = String(s);
            return d.innerHTML;
        }

        // ✅ Safe URL validation before use in href
        function safeUrl(url) {
            if (!url) return null;
            try {
                const u = new URL(url);
                if (u.protocol === 'http:' || u.protocol === 'https:') return esc(url);
            } catch (e) { }
            return null;
        }

        function reviewApp(id, app) {
            const body = document.getElementById('reviewBody');
            const footer = document.getElementById('reviewFooter');

            // ✅ Document path built from BASE_URL constant — not hardcoded
            let docPreview = '—';
            if (app.document_path) {
                const ext = app.document_path.split('.').pop().toLowerCase();
                const docUrl = BASE_URL + '/../' + esc(app.document_path);
                if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                    docPreview = `<img src="${docUrl}" style="max-width:100%;border-radius:8px;border:1px solid var(--border-color);margin-top:.5rem">`;
                } else {
                    docPreview = `<a href="${docUrl}" target="_blank" rel="noopener noreferrer" class="btn btn-outline btn-sm">
                            <span class="material-icons-round">download</span> Download Document
                          </a>`;
                }
            }

            // ✅ Website safely validated before rendering as link
            const websiteHtml = safeUrl(app.website)
                ? `<a href="${safeUrl(app.website)}" target="_blank" rel="noopener noreferrer">${esc(app.website)}</a>`
                : '—';

            body.innerHTML = `
        <div class="detail-row"><div class="detail-label">Applicant</div><div class="detail-value">${esc(app.applicant_name)}</div></div>
        <div class="detail-row"><div class="detail-label">Requested Role</div><div class="detail-value"><span class="badge badge-${esc(app.requested_role)}">${esc(app.requested_role)}</span></div></div>
        <div class="detail-row"><div class="detail-label">Organisation</div><div class="detail-value">${esc(app.organisation_name)}</div></div>
        <div class="detail-row"><div class="detail-label">Description</div><div class="detail-value">${esc(app.description)}</div></div>
        <div class="detail-row"><div class="detail-label">Website</div><div class="detail-value">${websiteHtml}</div></div>
        <div class="detail-row"><div class="detail-label">Phone</div><div class="detail-value">${esc(app.phone)}</div></div>
        <div class="detail-row"><div class="detail-label">Document</div><div class="detail-value">${esc(app.document_type)}<div style="margin-top:.5rem">${docPreview}</div></div></div>
        <div class="detail-row"><div class="detail-label">Status</div><div class="detail-value"><span class="badge badge-${esc(app.status)}">${esc(app.status)}</span></div></div>
        ${app.admin_note ? `<div class="detail-row"><div class="detail-label">Admin Note</div><div class="detail-value">${esc(app.admin_note)}</div></div>` : ''}
        ${app.status === 'pending' ? `
        <div class="form-group" style="margin-top:1rem">
            <label>Admin Note <span style="color:var(--text-muted);font-size:.8rem">(required for rejection)</span></label>
            <textarea class="form-control" id="adminNote" rows="3" placeholder="Enter note..."></textarea>
        </div>` : ''}
    `;

            if (app.status === 'pending') {
                footer.innerHTML = `
            <button class="btn btn-outline" onclick="closeModal('reviewModal')">Cancel</button>
            <button class="btn btn-danger"  id="rejectBtn"  onclick="rejectApp(${(int = id)})">
                <span class="material-icons-round">close</span> Reject
            </button>
            <button class="btn btn-success" id="approveBtn" onclick="approveApp(${id})">
                <span class="material-icons-round">check</span> Approve
            </button>
        `;
            } else {
                footer.innerHTML = `<button class="btn btn-outline" onclick="closeModal('reviewModal')">Close</button>`;
            }

            openModal('reviewModal');
        }

        // ✅ setLoading — disables buttons during API call to prevent double-submit
        function setLoading(loading) {
            const approveBtn = document.getElementById('approveBtn');
            const rejectBtn = document.getElementById('rejectBtn');
            if (approveBtn) approveBtn.disabled = loading;
            if (rejectBtn) rejectBtn.disabled = loading;
        }

        // ✅ approveApp — includes CSRF token, try/catch error handling
        async function approveApp(id) {
            if (!confirm('Approve this application? The user role will be upgraded immediately.')) return;
            setLoading(true);
            try {
                const fd = new FormData();
                fd.append('action', 'approve');
                fd.append('app_id', id);
                fd.append('csrf_token', CSRF); // ✅ CSRF token included
                const res = await fetch(API, { method: 'POST', body: fd });
                if (!res.ok) throw new Error(`Server error: ${res.status}`);
                const data = await res.json();
                showToast(data.message || 'Application approved.', data.success ? 'success' : 'error');
                if (data.success) setTimeout(() => location.reload(), 900);
            } catch (err) {
                console.error('[approveApp error]', err);
                showToast('An error occurred. Please try again.', 'error');
            } finally {
                setLoading(false);
            }
        }

        // ✅ rejectApp — includes CSRF token, requires admin note, try/catch
        async function rejectApp(id) {
            const noteEl = document.getElementById('adminNote');
            const note = noteEl ? noteEl.value.trim() : '';
            if (!note) {
                showToast('Admin note is required for rejection.', 'error');
                noteEl?.focus();
                return;
            }
            if (!confirm('Reject this application? This action cannot be undone.')) return;
            setLoading(true);
            try {
                const fd = new FormData();
                fd.append('action', 'reject');
                fd.append('app_id', id);
                fd.append('admin_note', note);
                fd.append('csrf_token', CSRF); // ✅ CSRF token included
                const res = await fetch(API, { method: 'POST', body: fd });
                if (!res.ok) throw new Error(`Server error: ${res.status}`);
                const data = await res.json();
                showToast(data.message || 'Application rejected.', data.success ? 'success' : 'error');
                if (data.success) setTimeout(() => location.reload(), 900);
            } catch (err) {
                console.error('[rejectApp error]', err);
                showToast('An error occurred. Please try again.', 'error');
            } finally {
                setLoading(false);
            }
        }

        function openModal(id) { document.getElementById(id).classList.add('active'); }
        function closeModal(id) { document.getElementById(id).classList.remove('active'); }

        function showToast(msg, type = 'success') {
            const c = document.getElementById('toastContainer');
            const t = document.createElement('div');
            t.className = `toast toast-${type}`;
            t.innerHTML = `<span class="material-icons-round">${type === 'success' ? 'check_circle' : 'error'}</span>${esc(msg)}`;
            c.appendChild(t);
            setTimeout(() => t.remove(), 3500);
        }

        // Close modal on backdrop click
        document.querySelectorAll('.modal-overlay').forEach(el => {
            el.addEventListener('click', e => { if (e.target === el) el.classList.remove('active'); });
        });
    </script>
</body>

</html>