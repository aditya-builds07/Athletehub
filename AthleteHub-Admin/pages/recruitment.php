<?php
/**
 * AthleteHub Admin — Recruitment Posts Page
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle   = 'Recruitment Posts';
$currentPage = 'recruitment';

$posts = $pdo->query("SELECT r.*, u.name AS poster_name FROM recruitment r JOIN users u ON r.posted_by = u.id ORDER BY r.created_at DESC")->fetchAll();

// Stats
$totalPosts  = count($posts);
$activePosts = 0; $jobs = 0; $scholarships = 0; $tryouts = 0; $training = 0;
foreach ($posts as $p) {
    if ($p['is_active']) $activePosts++;
    switch ($p['type']) {
        case 'job': $jobs++; break;
        case 'scholarship': $scholarships++; break;
        case 'tryout': $tryouts++; break;
        case 'training': $training++; break;
    }
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

            <div class="stats-grid" style="margin-bottom:1.5rem;">
                <div class="stat-card" style="--card-accent:#6366f1"><div class="stat-value"><?php echo $totalPosts; ?></div><div class="stat-label">Total Posts</div></div>
                <div class="stat-card" style="--card-accent:#22c55e"><div class="stat-value"><?php echo $activePosts; ?></div><div class="stat-label">Active</div></div>
                <div class="stat-card" style="--card-accent:#06b6d4"><div class="stat-value"><?php echo $jobs; ?></div><div class="stat-label">Jobs</div></div>
                <div class="stat-card" style="--card-accent:#eab308"><div class="stat-value"><?php echo $scholarships; ?></div><div class="stat-label">Scholarships</div></div>
                <div class="stat-card" style="--card-accent:#f97316"><div class="stat-value"><?php echo $tryouts; ?></div><div class="stat-label">Tryouts</div></div>
                <div class="stat-card" style="--card-accent:#ec4899"><div class="stat-value"><?php echo $training; ?></div><div class="stat-label">Training</div></div>
            </div>

            <div class="card">
                <table class="data-table">
                    <thead><tr><th>Title</th><th>Posted By</th><th>Type</th><th>Location</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if (empty($posts)): ?>
                        <tr><td colspan="7"><div class="empty-state"><span class="material-icons-round">work_off</span><p>No recruitment posts.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($posts as $p): ?>
                    <tr>
                        <td style="font-weight:600;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($p['title']); ?></td>
                        <td><?php echo htmlspecialchars($p['poster_name']); ?></td>
                        <td><span class="badge badge-upcoming"><?php echo $p['type']; ?></span></td>
                        <td><?php echo htmlspecialchars($p['location'] ?? '—'); ?></td>
                        <td><?php echo $p['is_active'] ? '<span class="badge badge-active">Active</span>' : '<span class="badge badge-inactive">Inactive</span>'; ?></td>
                        <td><?php echo date('M d, Y', strtotime($p['created_at'])); ?></td>
                        <td>
                            <div class="action-btns">
                                <button class="btn btn-outline btn-sm" onclick='viewPost(<?php echo json_encode($p); ?>)'><span class="material-icons-round">visibility</span></button>
                                <?php if ($p['is_active']): ?>
                                <button class="btn btn-warning btn-sm" onclick="deactivatePost(<?php echo $p['id']; ?>)"><span class="material-icons-round">pause_circle</span></button>
                                <?php endif; ?>
                                <button class="btn btn-danger btn-sm" onclick="deletePost(<?php echo $p['id']; ?>)"><span class="material-icons-round">delete</span></button>
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

<div class="modal-overlay" id="viewModal">
    <div class="modal"><div class="modal-header"><h3>Recruitment Details</h3><button class="modal-close" onclick="closeModal('viewModal')">&times;</button></div><div class="modal-body" id="viewBody"></div></div>
</div>
<div class="toast-container" id="toastContainer"></div>

<script>
const API = '<?php echo BASE_URL; ?>/api/recruitment.php';

function viewPost(p) {
    document.getElementById('viewBody').innerHTML = `
        <div class="detail-row"><div class="detail-label">Title</div><div class="detail-value">${esc(p.title)}</div></div>
        <div class="detail-row"><div class="detail-label">Sport</div><div class="detail-value">${esc(p.sport)}</div></div>
        <div class="detail-row"><div class="detail-label">Type</div><div class="detail-value">${esc(p.type)}</div></div>
        <div class="detail-row"><div class="detail-label">Location</div><div class="detail-value">${esc(p.location||'—')}</div></div>
        <div class="detail-row"><div class="detail-label">Deadline</div><div class="detail-value">${esc(p.deadline||'—')}</div></div>
        <div class="detail-row"><div class="detail-label">Description</div><div class="detail-value">${esc(p.description)}</div></div>`;
    openModal('viewModal');
}

async function deactivatePost(id) {
    if (!confirm('Deactivate this listing?')) return;
    const fd = new FormData(); fd.append('action','deactivate'); fd.append('id',id);
    const r = await fetch(API,{method:'POST',body:fd}); const d = await r.json();
    showToast(d.message, d.success?'success':'error');
    if (d.success) setTimeout(()=>location.reload(),800);
}

async function deletePost(id) {
    if (!confirm('Delete this listing permanently?')) return;
    const fd = new FormData(); fd.append('action','delete'); fd.append('id',id);
    const r = await fetch(API,{method:'POST',body:fd}); const d = await r.json();
    showToast(d.message, d.success?'success':'error');
    if (d.success) setTimeout(()=>location.reload(),800);
}

function esc(s){if(!s)return'';const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
function openModal(id){document.getElementById(id).classList.add('active');}
function closeModal(id){document.getElementById(id).classList.remove('active');}
function showToast(m,t='success'){const c=document.getElementById('toastContainer');const e=document.createElement('div');e.className=`toast toast-${t}`;e.innerHTML=`<span class="material-icons-round">${t==='success'?'check_circle':'error'}</span>${esc(m)}`;c.appendChild(e);setTimeout(()=>e.remove(),3500);}
document.querySelectorAll('.modal-overlay').forEach(el=>{el.addEventListener('click',e=>{if(e.target===el)el.classList.remove('active');});});
</script>
</body>
</html>
