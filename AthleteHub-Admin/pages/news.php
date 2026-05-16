<?php
/**
 * AthleteHub Admin — News Management Page
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle   = 'News Management';
$currentPage = 'news';

$articles = $pdo->query("SELECT n.*, u.name AS author_name FROM news n JOIN users u ON n.posted_by = u.id ORDER BY n.created_at DESC")->fetchAll();
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

            <div class="toolbar">
                <button class="btn btn-primary" onclick="openAddModal()">
                    <span class="material-icons-round">add</span> Add News
                </button>
                <div class="toolbar-spacer"></div>
                <span style="color:var(--text-muted);font-size:.85rem;"><?php echo count($articles); ?> articles</span>
            </div>

            <div class="card">
                <table class="data-table">
                    <thead><tr><th>Title</th><th>Author</th><th>Published</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if (empty($articles)): ?>
                        <tr><td colspan="4"><div class="empty-state"><span class="material-icons-round">article</span><p>No news articles.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($articles as $a): ?>
                    <tr>
                        <td style="font-weight:600;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($a['title']); ?></td>
                        <td><?php echo htmlspecialchars($a['author_name']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($a['created_at'])); ?></td>
                        <td>
                            <div class="action-btns">
                                <button class="btn btn-outline btn-sm" onclick='editNews(<?php echo json_encode($a); ?>)' title="Edit"><span class="material-icons-round">edit</span></button>
                                <button class="btn btn-danger btn-sm" onclick="deleteNews(<?php echo $a['id']; ?>)" title="Delete"><span class="material-icons-round">delete</span></button>
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

<!-- Add/Edit News Modal -->
<div class="modal-overlay" id="newsModal">
    <div class="modal" style="max-width:600px;">
        <div class="modal-header"><h3 id="newsModalTitle">Add News</h3><button class="modal-close" onclick="closeModal('newsModal')">&times;</button></div>
        <div class="modal-body">
            <input type="hidden" id="newsId">
            <div class="form-group"><label>Title</label><input type="text" class="form-control" id="newsTitle" placeholder="Article title"></div>
            <div class="form-group"><label>Content</label><textarea class="form-control" id="newsContent" rows="6" placeholder="Article content..."></textarea></div>
            <div class="form-group"><label>Header Image URL (optional)</label><input type="text" class="form-control" id="newsImage" placeholder="https://..."></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('newsModal')">Cancel</button>
            <button class="btn btn-primary" id="newsSaveBtn" onclick="saveNews()">Publish</button>
        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
const API = '<?php echo BASE_URL; ?>/api/news.php';

function openAddModal() {
    document.getElementById('newsModalTitle').textContent = 'Add News';
    document.getElementById('newsId').value = '';
    document.getElementById('newsTitle').value = '';
    document.getElementById('newsContent').value = '';
    document.getElementById('newsImage').value = '';
    document.getElementById('newsSaveBtn').textContent = 'Publish';
    openModal('newsModal');
}

function editNews(a) {
    document.getElementById('newsModalTitle').textContent = 'Edit News';
    document.getElementById('newsId').value = a.id;
    document.getElementById('newsTitle').value = a.title;
    document.getElementById('newsContent').value = a.content;
    document.getElementById('newsImage').value = a.image_url || '';
    document.getElementById('newsSaveBtn').textContent = 'Update';
    openModal('newsModal');
}

async function saveNews() {
    const id = document.getElementById('newsId').value;
    const fd = new FormData();
    fd.append('action', id ? 'update' : 'create');
    if (id) fd.append('id', id);
    fd.append('title', document.getElementById('newsTitle').value);
    fd.append('content', document.getElementById('newsContent').value);
    fd.append('image_url', document.getElementById('newsImage').value);

    const r = await fetch(API, {method:'POST', body:fd});
    const d = await r.json();
    showToast(d.message, d.success?'success':'error');
    if (d.success) setTimeout(()=>location.reload(), 800);
    closeModal('newsModal');
}

async function deleteNews(id) {
    if (!confirm('Delete this news article?')) return;
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
