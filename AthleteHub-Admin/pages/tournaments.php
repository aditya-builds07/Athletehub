<?php
/**
 * AthleteHub Admin — Tournaments Page
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle   = 'Tournaments';
$currentPage = 'tournaments';

$tournaments = $pdo->query("
    SELECT t.*, u.name AS host_name,
           (SELECT COUNT(*) FROM tournament_registrations WHERE tournament_id = t.id) AS reg_count
    FROM tournaments t
    JOIN users u ON t.created_by = u.id
    ORDER BY t.start_date DESC
")->fetchAll();
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
            <div class="card">
                <table class="data-table">
                    <thead><tr><th>Title</th><th>Host</th><th>Location</th><th>Dates</th><th>Status</th><th>Registrations</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if (empty($tournaments)): ?>
                        <tr><td colspan="7"><div class="empty-state"><span class="material-icons-round">emoji_events</span><p>No tournaments.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($tournaments as $t): ?>
                    <tr>
                        <td style="font-weight:600"><?php echo htmlspecialchars($t['name']); ?></td>
                        <td><?php echo htmlspecialchars($t['host_name']); ?></td>
                        <td><?php echo htmlspecialchars($t['location'] ?? '—'); ?></td>
                        <td><?php echo date('M d', strtotime($t['start_date'])) . ' – ' . date('M d, Y', strtotime($t['end_date'])); ?></td>
                        <td><span class="badge badge-<?php echo $t['status']; ?>"><?php echo $t['status']; ?></span></td>
                        <td style="text-align:center;font-weight:700"><?php echo $t['reg_count']; ?></td>
                        <td>
                            <div class="action-btns">
                                <button class="btn btn-outline btn-sm" onclick="viewParticipants(<?php echo $t['id']; ?>)" title="Participants"><span class="material-icons-round">groups</span></button>
                                <button class="btn btn-outline btn-sm" onclick="openStatusModal(<?php echo $t['id']; ?>,'<?php echo $t['status']; ?>')" title="Status"><span class="material-icons-round">swap_horiz</span></button>
                                <button class="btn btn-danger btn-sm" onclick="deleteTournament(<?php echo $t['id']; ?>)" title="Delete"><span class="material-icons-round">delete</span></button>
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

<!-- Participants Modal -->
<div class="modal-overlay" id="participantsModal">
    <div class="modal"><div class="modal-header"><h3>Registered Participants</h3><button class="modal-close" onclick="closeModal('participantsModal')">&times;</button></div><div class="modal-body" id="participantsBody">Loading...</div></div>
</div>

<!-- Status Modal -->
<div class="modal-overlay" id="statusModal">
    <div class="modal" style="max-width:380px;">
        <div class="modal-header"><h3>Change Status</h3><button class="modal-close" onclick="closeModal('statusModal')">&times;</button></div>
        <div class="modal-body">
            <input type="hidden" id="statusTournamentId">
            <div class="form-group"><label>Status</label>
                <select class="form-control" id="newStatus"><option value="upcoming">Upcoming</option><option value="open">Open</option><option value="closed">Closed</option></select>
            </div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline" onclick="closeModal('statusModal')">Cancel</button><button class="btn btn-primary" onclick="saveStatus()">Save</button></div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
const API = '<?php echo BASE_URL; ?>/api/tournaments.php';

async function viewParticipants(id) {
    const body = document.getElementById('participantsBody');
    body.innerHTML = 'Loading...';
    openModal('participantsModal');
    const fd = new FormData(); fd.append('action','participants'); fd.append('id',id);
    const r = await fetch(API,{method:'POST',body:fd}); const d = await r.json();
    if (d.success && d.participants.length) {
        let html = '<table class="data-table"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Sport</th><th>Registered</th></tr></thead><tbody>';
        d.participants.forEach(p => {
            html += `<tr><td>${esc(p.name)}</td><td>${esc(p.email)}</td><td><span class="badge badge-${p.role}">${p.role}</span></td><td>${esc(p.sport||'—')}</td><td>${p.registered_at}</td></tr>`;
        });
        html += '</tbody></table>';
        body.innerHTML = html;
    } else { body.innerHTML = '<div class="empty-state"><p>No participants registered.</p></div>'; }
}

function openStatusModal(id, current) {
    document.getElementById('statusTournamentId').value = id;
    document.getElementById('newStatus').value = current;
    openModal('statusModal');
}

async function saveStatus() {
    const fd = new FormData();
    fd.append('action','change_status');
    fd.append('id', document.getElementById('statusTournamentId').value);
    fd.append('status', document.getElementById('newStatus').value);
    const r = await fetch(API,{method:'POST',body:fd}); const d = await r.json();
    showToast(d.message, d.success?'success':'error');
    if (d.success) setTimeout(()=>location.reload(),800);
    closeModal('statusModal');
}

async function deleteTournament(id) {
    if (!confirm('Delete this tournament? All registrations will also be removed.')) return;
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
