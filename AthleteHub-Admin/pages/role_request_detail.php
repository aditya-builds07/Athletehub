<?php
/**
 * AthleteHub Admin — Role Application Detail Page
 * Full review page for a single role application.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle   = 'Application Review';
$currentPage = 'role_applications';

// Fetch application
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT ra.*, u.name AS applicant_name, u.email AS applicant_email,
           u.profile_pic, u.role AS current_role, u.sport, u.location,
           r.name AS reviewer_name
    FROM role_applications ra
    JOIN users u ON ra.user_id = u.id
    LEFT JOIN users r ON ra.reviewed_by = r.id
    WHERE ra.id = ?
");
$stmt->execute([$id]);
$app = $stmt->fetch();

if (!$app) {
    header('Location: ' . BASE_URL . '/pages/role_applications.php');
    exit();
}

// Generate initials
$parts = explode(' ', $app['applicant_name']);
$initials = strtoupper(substr($parts[0], 0, 1));
if (isset($parts[1])) $initials .= strtoupper(substr($parts[1], 0, 1));
$colors = ['#6366f1', '#8b5cf6', '#06b6d4', '#10b981', '#f59e0b', '#ef4444', '#ec4899'];
$ci = array_sum(array_map('ord', str_split($app['applicant_name']))) % count($colors);

// CSRF token for forms
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review #<?php echo $id; ?> — AthleteHub Admin</title>
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

            <!-- Back link -->
            <a href="<?php echo BASE_URL; ?>/pages/role_applications.php" class="back-link">
                <span class="material-icons-round">arrow_back</span> Back to Applications
            </a>

            <!-- Page Header -->
            <div class="detail-page-header">
                <h2>Application #<?php echo $id; ?></h2>
                <span class="badge badge-<?php echo htmlspecialchars($app['status']); ?> badge-lg">
                    <?php echo ucfirst(htmlspecialchars($app['status'])); ?>
                </span>
            </div>

            <div class="detail-grid">
                <!-- Left Column: Applicant Info -->
                <div class="card detail-card">
                    <div class="card-title">Applicant Info</div>
                    <div class="applicant-profile">
                        <div class="detail-avatar" style="background: <?php echo $colors[$ci]; ?>">
                            <?php echo $initials; ?>
                        </div>
                        <div class="applicant-name-block">
                            <h3><?php echo htmlspecialchars($app['applicant_name']); ?></h3>
                            <span class="text-muted"><?php echo htmlspecialchars($app['applicant_email']); ?></span>
                            <span class="badge badge-outline" style="margin-top: .25rem;">Current: <?php echo ucfirst(htmlspecialchars($app['current_role'])); ?></span>
                        </div>
                    </div>

                    <div class="detail-list">
                        <div class="detail-row">
                            <div class="detail-label"><span class="material-icons-round">phone</span> Phone</div>
                            <div class="detail-value"><?php echo htmlspecialchars($app['phone']); ?></div>
                        </div>
                        <?php if (!empty($app['sport'])): ?>
                        <div class="detail-row">
                            <div class="detail-label"><span class="material-icons-round">sports</span> Sport</div>
                            <div class="detail-value"><?php echo htmlspecialchars($app['sport']); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($app['location'])): ?>
                        <div class="detail-row">
                            <div class="detail-label"><span class="material-icons-round">location_on</span> Location</div>
                            <div class="detail-value"><?php echo htmlspecialchars($app['location']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Column: Application Details -->
                <div class="card detail-card">
                    <div class="card-title">Application Details</div>
                    <div class="detail-list">
                        <div class="detail-row">
                            <div class="detail-label"><span class="material-icons-round">badge</span> Requested Role</div>
                            <div class="detail-value">
                                <span class="badge badge-<?php echo htmlspecialchars($app['requested_role']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($app['requested_role'])); ?>
                                </span>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label"><span class="material-icons-round">business</span> Organisation</div>
                            <div class="detail-value"><?php echo htmlspecialchars($app['organisation_name']); ?></div>
                        </div>
                        <?php if (!empty($app['website'])): ?>
                        <div class="detail-row">
                            <div class="detail-label"><span class="material-icons-round">language</span> Website</div>
                            <div class="detail-value"><a href="<?php echo htmlspecialchars($app['website']); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($app['website']); ?></a></div>
                        </div>
                        <?php endif; ?>
                        <div class="detail-row">
                            <div class="detail-label"><span class="material-icons-round">description</span> Description</div>
                            <div class="detail-value detail-description"><?php echo nl2br(htmlspecialchars($app['description'])); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label"><span class="material-icons-round">calendar_today</span> Submitted</div>
                            <div class="detail-value"><?php echo date('M d, Y \a\t h:i A', strtotime($app['created_at'])); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Document Section -->
            <?php if (!empty($app['document_path'])): ?>
            <div class="card detail-card">
                <div class="card-title">Submitted Document</div>
                <div class="detail-list">
                    <div class="detail-row">
                        <div class="detail-label"><span class="material-icons-round">article</span> Type</div>
                        <div class="detail-value"><?php echo htmlspecialchars($app['document_type']); ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label"><span class="material-icons-round">attach_file</span> File</div>
                        <div class="detail-value">
                            <?php
                            $ext = strtolower(pathinfo($app['document_path'], PATHINFO_EXTENSION));
                            $docUrl = MAIN_APP_URL . '/' . htmlspecialchars($app['document_path']);
                            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                                <img src="<?php echo $docUrl; ?>" class="doc-preview-img" alt="Document">
                            <?php else: ?>
                                <a href="<?php echo $docUrl; ?>" target="_blank" class="btn btn-outline btn-sm">
                                    <span class="material-icons-round">open_in_new</span> View Document
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Admin Decision Section -->
            <?php if ($app['status'] === 'pending'): ?>
            <div class="card detail-card decision-card">
                <div class="card-title">Admin Decision</div>
                <div class="form-group">
                    <label for="adminNote">Admin Note <span class="text-muted">(required for rejection, optional for approval)</span></label>
                    <textarea class="form-control" id="adminNote" rows="3" placeholder="Enter your notes about this application..."></textarea>
                </div>
                <input type="hidden" id="csrfToken" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="decision-actions">
                    <button class="btn btn-success btn-lg" id="btnApprove" data-id="<?php echo $id; ?>">
                        <span class="material-icons-round">check_circle</span> Approve Application
                    </button>
                    <button class="btn btn-danger btn-lg" id="btnReject" data-id="<?php echo $id; ?>">
                        <span class="material-icons-round">cancel</span> Reject Application
                    </button>
                </div>
            </div>
            <?php else: ?>
            <div class="card detail-card reviewed-card reviewed-<?php echo htmlspecialchars($app['status']); ?>">
                <div class="reviewed-header">
                    <span class="material-icons-round"><?php echo $app['status'] === 'approved' ? 'check_circle' : 'cancel'; ?></span>
                    <div>
                        <strong><?php echo ucfirst(htmlspecialchars($app['status'])); ?></strong>
                        by <?php echo htmlspecialchars($app['reviewer_name'] ?? 'Admin'); ?>
                        on <?php echo $app['reviewed_at'] ? date('M d, Y', strtotime($app['reviewed_at'])) : '—'; ?>
                    </div>
                </div>
                <?php if (!empty($app['admin_note'])): ?>
                <div class="reviewed-note">
                    <strong>Note:</strong> "<?php echo htmlspecialchars($app['admin_note']); ?>"
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- Confirm Modal -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal" style="max-width:450px;">
        <div class="modal-header">
            <h3 id="confirmTitle">Confirm Action</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="confirmBody"></div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal()">Cancel</button>
            <button class="btn" id="confirmBtn" onclick="submitDecision()">Confirm</button>
        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
const API = '<?php echo BASE_URL; ?>/api/role_applications.php';
let pendingAction = null;
let pendingId = null;

document.addEventListener('DOMContentLoaded', () => {
    const approveBtn = document.getElementById('btnApprove');
    const rejectBtn  = document.getElementById('btnReject');

    if (approveBtn) {
        approveBtn.addEventListener('click', () => {
            pendingAction = 'approve';
            pendingId = approveBtn.dataset.id;
            document.getElementById('confirmTitle').textContent = '✅ Confirm Approval';
            document.getElementById('confirmBody').innerHTML = `
                <p>This will <strong>promote the user</strong> to <strong><?php echo ucfirst(htmlspecialchars($app['requested_role'])); ?></strong> role permanently and mark them as verified.</p>
                <p style="margin-top:.5rem;color:var(--text-muted)">This action cannot be undone.</p>
            `;
            const btn = document.getElementById('confirmBtn');
            btn.textContent = 'Yes, Approve';
            btn.className = 'btn btn-success';
            openModal();
        });
    }

    if (rejectBtn) {
        rejectBtn.addEventListener('click', () => {
            const note = document.getElementById('adminNote').value.trim();
            if (!note) {
                showToast('Admin note is required for rejection', 'error');
                document.getElementById('adminNote').focus();
                return;
            }
            pendingAction = 'reject';
            pendingId = rejectBtn.dataset.id;
            document.getElementById('confirmTitle').textContent = '❌ Confirm Rejection';
            document.getElementById('confirmBody').innerHTML = `
                <p>This will <strong>reject</strong> the application. The user will be notified with your reason.</p>
            `;
            const btn = document.getElementById('confirmBtn');
            btn.textContent = 'Yes, Reject';
            btn.className = 'btn btn-danger';
            openModal();
        });
    }
});

async function submitDecision() {
    closeModal();
    const note  = document.getElementById('adminNote')?.value.trim() || '';
    const csrf  = document.getElementById('csrfToken')?.value || '';

    const fd = new FormData();
    fd.append('action', pendingAction);
    fd.append('app_id', pendingId);
    fd.append('admin_note', note);
    fd.append('csrf_token', csrf);

    try {
        const res = await fetch(API, { method: 'POST', body: fd });
        const data = await res.json();
        showToast(data.message, data.success ? 'success' : 'error');
        if (data.success) {
            setTimeout(() => window.location.href = '<?php echo BASE_URL; ?>/pages/role_applications.php', 1200);
        }
    } catch (err) {
        showToast('Network error. Please try again.', 'error');
    }
}

function openModal()  { document.getElementById('confirmModal').classList.add('active'); }
function closeModal() { document.getElementById('confirmModal').classList.remove('active'); }

function showToast(msg, type = 'success') {
    const c = document.getElementById('toastContainer');
    const t = document.createElement('div');
    t.className = `toast toast-${type}`;
    const icon = type === 'success' ? 'check_circle' : 'error';
    t.innerHTML = `<span class="material-icons-round">${icon}</span>${esc(msg)}`;
    c.appendChild(t);
    setTimeout(() => t.remove(), 3500);
}

function esc(s) {
    if (!s) return '';
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

// Close modal on overlay click
document.getElementById('confirmModal')?.addEventListener('click', e => {
    if (e.target === e.currentTarget) closeModal();
});
</script>
</body>
</html>
