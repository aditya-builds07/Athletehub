<?php
/**
 * AthleteHub Admin — Live Streams Page
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle   = 'Live Streams';
$currentPage = 'livestreams';

$streams = $pdo->query("SELECT ls.*, u.name AS host_name FROM live_streams ls JOIN users u ON ls.host_user_id = u.id ORDER BY ls.created_at DESC")->fetchAll();

$liveCount = 0; $todayCount = 0; $today = date('Y-m-d');
foreach ($streams as $s) {
    if ($s['status'] === 'live') $liveCount++;
    if (substr($s['created_at'],0,10) === $today) $todayCount++;
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
                <div class="stat-card" style="--card-accent:#ef4444"><div class="stat-value"><?php echo $liveCount; ?></div><div class="stat-label">Currently Live</div></div>
                <div class="stat-card" style="--card-accent:#6366f1"><div class="stat-value"><?php echo $todayCount; ?></div><div class="stat-label">Streams Today</div></div>
                <div class="stat-card" style="--card-accent:#06b6d4"><div class="stat-value"><?php echo count($streams); ?></div><div class="stat-label">Total Streams</div></div>
            </div>

            <div class="card">
                <table class="data-table">
                    <thead><tr><th>Title</th><th>Host</th><th>YouTube URL</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if (empty($streams)): ?>
                        <tr><td colspan="6"><div class="empty-state"><span class="material-icons-round">sensors_off</span><p>No streams found.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($streams as $s): ?>
                    <tr>
                        <td style="font-weight:600"><?php echo htmlspecialchars($s['title']); ?></td>
                        <td><?php echo htmlspecialchars($s['host_name']); ?></td>
                        <td><a href="<?php echo htmlspecialchars($s['youtube_url']); ?>" target="_blank" style="font-size:.78rem;"><?php echo htmlspecialchars(substr($s['youtube_url'],0,40)); ?>…</a></td>
                        <td><span class="badge badge-<?php echo $s['status']; ?>"><?php echo $s['status']; ?></span></td>
                        <td><?php echo date('M d, Y H:i', strtotime($s['created_at'])); ?></td>
                        <td>
                            <div class="action-btns">
                                <a href="<?php echo htmlspecialchars($s['youtube_url']); ?>" target="_blank" class="btn btn-outline btn-sm" title="Watch"><span class="material-icons-round">play_arrow</span></a>
                                <?php if ($s['status'] === 'live'): ?>
                                <button class="btn btn-warning btn-sm" onclick="forceEnd(<?php echo $s['id']; ?>)" title="Force End"><span class="material-icons-round">stop_circle</span></button>
                                <?php endif; ?>
                                <button class="btn btn-danger btn-sm" onclick="deleteStream(<?php echo $s['id']; ?>)" title="Delete"><span class="material-icons-round">delete</span></button>
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
<div class="toast-container" id="toastContainer"></div>
<script>
const API = '<?php echo BASE_URL; ?>/api/livestreams.php';

async function forceEnd(id) {
    if (!confirm('Force end this stream?')) return;
    const fd = new FormData(); fd.append('action','force_end'); fd.append('id',id);
    const r = await fetch(API,{method:'POST',body:fd}); const d = await r.json();
    showToast(d.message, d.success?'success':'error');
    if (d.success) setTimeout(()=>location.reload(),800);
}

async function deleteStream(id) {
    if (!confirm('Delete this stream record?')) return;
    const fd = new FormData(); fd.append('action','delete'); fd.append('id',id);
    const r = await fetch(API,{method:'POST',body:fd}); const d = await r.json();
    showToast(d.message, d.success?'success':'error');
    if (d.success) setTimeout(()=>location.reload(),800);
}

function esc(s){if(!s)return'';const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
function showToast(m,t='success'){const c=document.getElementById('toastContainer');const e=document.createElement('div');e.className=`toast toast-${t}`;e.innerHTML=`<span class="material-icons-round">${t==='success'?'check_circle':'error'}</span>${esc(m)}`;c.appendChild(e);setTimeout(()=>e.remove(),3500);}
</script>
</body>
</html>
