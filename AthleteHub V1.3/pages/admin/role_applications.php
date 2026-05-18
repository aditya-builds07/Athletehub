<?php
/**
 * AthleteHub — Admin Role Applications
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_role('admin', BASE_URL . '/index.php');

$statusFilter = $_GET['status'] ?? 'all';

$query = "
    SELECT 
        ra.id, ra.user_id, ra.requested_role, ra.organisation_name, ra.description,
        ra.website, ra.phone, ra.document_path, ra.document_type, ra.status,
        ra.admin_note, ra.reviewed_by, ra.reviewed_at, ra.created_at,
        ra.profile_photo, ra.years_experience, ra.team_player_count,
        ra.city, ra.country, ra.instagram, ra.twitter, ra.linkedin,
        ra.facebook, ra.youtube, ra.submitted_at,
        u.name as applicant_name, u.role as current_role, u.email
    FROM role_applications ra
    JOIN users u ON ra.user_id = u.id
";
$params = [];
if ($statusFilter !== 'all' && in_array($statusFilter, ['pending', 'approved', 'rejected'])) {
    $query .= " WHERE ra.status = ?";
    $params[] = $statusFilter;
}
$query .= " ORDER BY ra.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Role Applications | Admin";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="layout-wrapper pt-20 pb-12">
    <div class="container mx-auto px-4 max-w-6xl">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold flex items-center gap-2">
                <span class="material-icons-round text-primary">admin_panel_settings</span>
                Role Applications
            </h2>
        </div>

        <!-- Filter Tabs -->
        <div class="flex gap-2 mb-6 overflow-x-auto">
            <a href="?status=all" class="btn <?= $statusFilter === 'all' ? 'btn-primary shadow-glow' : 'btn-outline' ?> btn-sm">All</a>
            <a href="?status=pending" class="btn <?= $statusFilter === 'pending' ? 'btn-primary shadow-glow' : 'btn-outline' ?> btn-sm">Pending</a>
            <a href="?status=approved" class="btn <?= $statusFilter === 'approved' ? 'btn-primary shadow-glow' : 'btn-outline' ?> btn-sm">Approved</a>
            <a href="?status=rejected" class="btn <?= $statusFilter === 'rejected' ? 'btn-primary shadow-glow' : 'btn-outline' ?> btn-sm">Rejected</a>
        </div>

        <div class="glass-card overflow-hidden" style="padding:0;">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-white bg-opacity-5 text-sm uppercase text-muted">
                            <th class="p-4 border-b border-white border-opacity-10">Applicant</th>
                            <th class="p-4 border-b border-white border-opacity-10">Roles</th>
                            <th class="p-4 border-b border-white border-opacity-10">Organisation</th>
                            <th class="p-4 border-b border-white border-opacity-10">Submitted</th>
                            <th class="p-4 border-b border-white border-opacity-10">Status</th>
                            <th class="p-4 border-b border-white border-opacity-10 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($applications)): ?>
                            <tr>
                                <td colspan="6" class="p-6 text-center text-muted">No applications found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($applications as $app): ?>
                                <tr class="hover:bg-white hover:bg-opacity-5 transition-colors">
                                    <td class="p-4 border-b border-white border-opacity-5">
                                        <a href="<?= BASE_URL ?>/pages/profile.php?id=<?= e($app['user_id']) ?>" class="font-bold text-primary hover:underline flex items-center gap-1">
                                            <?= e($app['applicant_name']) ?>
                                        </a>
                                        <div class="text-xs text-muted mt-1"><?= e($app['phone']) ?></div>
                                    </td>
                                    <td class="p-4 border-b border-white border-opacity-5">
                                        <div class="text-xs font-semibold">Current: <span class="uppercase text-muted"><?= $app['current_role'] ?></span></div>
                                        <div class="text-xs font-bold mt-1">Req: <span class="uppercase <?= $app['requested_role'] === 'club' ? 'text-primary' : 'text-blue-400' ?>"><?= $app['requested_role'] ?></span></div>
                                    </td>
                                    <td class="p-4 border-b border-white border-opacity-5">
                                        <div class="font-semibold text-sm line-clamp-1"><?= htmlspecialchars($app['organisation_name']) ?></div>
                                    </td>
                                    <td class="p-4 border-b border-white border-opacity-5 text-sm text-muted">
                                        <?= date('d M Y, H:i', strtotime($app['created_at'])) ?>
                                    </td>
                                    <td class="p-4 border-b border-white border-opacity-5">
                                        <?php if ($app['status'] === 'pending'): ?>
                                            <span class="bg-yellow-500 bg-opacity-20 text-yellow-300 px-2 py-1 rounded text-xs font-bold">Pending</span>
                                        <?php elseif ($app['status'] === 'approved'): ?>
                                            <span class="bg-green-500 bg-opacity-20 text-green-300 px-2 py-1 rounded text-xs font-bold">Approved</span>
                                        <?php else: ?>
                                            <span class="bg-red-500 bg-opacity-20 text-red-300 px-2 py-1 rounded text-xs font-bold">Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 border-b border-white border-opacity-5 text-right">
                                        <button class="btn btn-outline btn-sm" onclick='openReviewModal(<?= json_encode($app) ?>)'>Review</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════ REVIEW MODAL ═══════════════ -->
<div class="modal-overlay" id="reviewModal">
    <div class="glass-modal" style="position:relative; max-width: 600px; width: 100%; max-height: 90vh; overflow-y: auto;">
        <button class="modal-close" onclick="closeReviewModal()" aria-label="Close">
            <span class="material-icons-round">close</span>
        </button>
        <h3 class="modal-title mb-4 flex items-center gap-2">
            <span class="material-icons-round text-primary">fact_check</span> Review Application
        </h3>
        
        <div id="reviewContent"></div>

        <div class="mt-4 border-t border-white border-opacity-10 pt-4">
            <label class="form-label text-xs font-semibold text-red-300">Admin Note (Required for Rejection)</label>
            <textarea id="adminNote" class="glass-input w-full min-h-[60px]" placeholder="Explain reason for rejection or add an approval note..."></textarea>
            
            <div class="flex gap-2 mt-4" id="reviewActions">
                <button id="approveBtn" class="btn btn-sm flex-1 bg-green-600 hover:bg-green-500 border-green-500 text-white" onclick="processApplication('approve')">
                    <span class="material-icons-round text-sm">check_circle</span> Approve
                </button>
                <button id="rejectBtn" class="btn btn-sm flex-1 bg-red-600 hover:bg-red-500 border-red-500 text-white" onclick="processApplication('reject')">
                    <span class="material-icons-round text-sm">cancel</span> Reject
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentAppId = null;

function openReviewModal(app) {
    currentAppId = app.id;
    document.getElementById('adminNote').value = app.admin_note || '';
    
    // Hide actions if already processed
    const actionsDiv = document.getElementById('reviewActions');
    const noteInput = document.getElementById('adminNote');
    if (app.status !== 'pending') {
        actionsDiv.style.display = 'none';
        noteInput.readOnly = true;
    } else {
        actionsDiv.style.display = 'flex';
        noteInput.readOnly = false;
    }

    const docExt = app.document_path.split('.').pop().toLowerCase();
    const isImage = ['jpg', 'jpeg', 'png'].includes(docExt);
    const basePath = window.AthleteHubBaseUrl || '/dashboard/AthleteHub V1.3/';


    let docPreview = '';
    if (isImage) {
        docPreview = `<img src="${basePath}${app.document_path}" alt="Document" class="w-full rounded mt-2 border border-white border-opacity-10">`;
    } else {
        docPreview = `<a href="${basePath}${app.document_path}" target="_blank" class="btn btn-outline btn-sm mt-2 flex items-center justify-center gap-2">
            <span class="material-icons-round">picture_as_pdf</span> View PDF Document
        </a>`;
    }

    const html = `
        <div class="grid grid-cols-2 gap-4 mb-4 text-sm">
            <div>
                <p class="text-xs text-muted font-bold uppercase mb-1">Applicant</p>
                <p class="font-semibold">${app.applicant_name}</p>
                <p class="text-xs text-muted">${app.email}</p>
            </div>
            <div>
                <p class="text-xs text-muted font-bold uppercase mb-1">Role Request</p>
                <p class="font-bold text-primary uppercase">${app.requested_role}</p>
                <p class="text-xs text-muted">Currently: ${app.current_role}</p>
            </div>
        </div>
        
        <div class="bg-black bg-opacity-20 p-4 rounded-lg border border-white border-opacity-5 mb-4">
            <p class="text-xs text-muted font-bold uppercase mb-1">Organisation</p>
            <p class="font-bold text-lg mb-1">${app.organisation_name}</p>
            <p class="text-xs mb-2">📞 ${app.phone} ${app.website ? ` | 🌐 <a href="${app.website}" target="_blank" class="text-primary hover:underline">${app.website}</a>` : ''}</p>
            <p class="text-sm text-gray-300 mt-2">${app.description}</p>
        </div>

        <div class="mb-4">
            <p class="text-xs text-muted font-bold uppercase mb-1">Document: ${app.document_type}</p>
            ${docPreview}
        </div>
    `;

    document.getElementById('reviewContent').innerHTML = html;
    document.getElementById('reviewModal').classList.add('open');
}

function closeReviewModal() {
    document.getElementById('reviewModal').classList.remove('open');
    currentAppId = null;
}

async function processApplication(action) {
    if (!currentAppId) return;
    
    const note = document.getElementById('adminNote').value.trim();
    if (action === 'reject' && !note) {
        alert('An admin note is required for rejection.');
        return;
    }

    if (!confirm(`Are you sure you want to ${action} this application?`)) return;

    const approveBtn = document.getElementById('approveBtn');
    const rejectBtn = document.getElementById('rejectBtn');
    if (approveBtn) approveBtn.disabled = true;
    if (rejectBtn) rejectBtn.disabled = true;

    const formData = new FormData();
    formData.append('application_id', currentAppId);
    formData.append('admin_note', note);
    
    // Attach CSRF token
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta) {
        formData.append('csrf_token', csrfMeta.content);
    }

    try {
        const basePath = window.AthleteHubBaseUrl || '';
        const res = await fetch(`${basePath}/api/role_application.php?action=${action}`, {
            method: 'POST',
            body: formData
        });
        const json = await res.json();

        if (json.success) {
            if (window.showToast) showToast(`Application ${action}d!`, 'success');
            else alert(`Application ${action}d!`);
            setTimeout(() => window.location.reload(), 1000);
        } else {
            alert(json.error || 'Failed to process application.');
        }
    } catch (err) {
        console.error(err);
        alert('Network error occurred.');
    } finally {
        if (approveBtn) approveBtn.disabled = false;
        if (rejectBtn) rejectBtn.disabled = false;
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
