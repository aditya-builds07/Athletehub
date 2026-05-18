/**
 * AthleteHub Admin — Role Requests JS
 * Handles: live search filter, confirm modal, approve/reject async fetch.
 * Used by: role_requests.php and role_request_detail.php
 */

'use strict';

/* ── Helpers ─────────────────────────────────────────────── */
function esc(s) {
    if (s == null) return '';
    const d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
}

function getCsrf() {
    const meta = document.querySelector('meta[name="admin-csrf"]');
    return meta ? meta.content : '';
}

function showToast(msg, type = 'success') {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        container.id = 'toastContainer';
        document.body.appendChild(container);
    }
    const t = document.createElement('div');
    t.className = `toast toast-${type}`;
    const icon = type === 'success' ? 'check_circle' : 'error';
    t.innerHTML = `<span class="material-icons-round">${icon}</span>${esc(msg)}`;
    container.appendChild(t);
    setTimeout(() => t.remove(), 3800);
}

/* ── Search Live Filter ──────────────────────────────────── */
function initSearchFilter() {
    const searchInput = document.getElementById('rrSearchInput');
    if (!searchInput) return;

    searchInput.addEventListener('input', () => {
        const q = searchInput.value.toLowerCase().trim();
        const rows = document.querySelectorAll('.rr-table-row');
        let visibleCount = 0;

        rows.forEach(row => {
            const text = row.dataset.search || '';
            const match = !q || text.includes(q);
            row.style.display = match ? '' : 'none';
            if (match) visibleCount++;
        });

        const emptyState = document.getElementById('rrEmptyState');
        if (emptyState) {
            emptyState.style.display = visibleCount === 0 ? '' : 'none';
        }
    });
}

/* ── Confirm Modal ───────────────────────────────────────── */
let _pendingAction  = null;
let _pendingAppId   = null;
let _pendingNote    = '';

function openConfirmModal(action, appId, note = '') {
    _pendingAction = action;
    _pendingAppId  = appId;
    _pendingNote   = note;

    const modal    = document.getElementById('rrConfirmModal');
    const iconEl   = document.getElementById('rrConfirmIcon');
    const titleEl  = document.getElementById('rrConfirmTitle');
    const subEl    = document.getElementById('rrConfirmSubtitle');
    const confirmBtn = document.getElementById('rrConfirmBtn');

    if (!modal) return;

    if (action === 'approve') {
        iconEl.className   = 'rr-confirm-modal-icon approve';
        iconEl.innerHTML   = '<span class="material-icons-round">check_circle</span>';
        titleEl.textContent = 'Approve Application?';
        subEl.textContent   = "The user's role will be upgraded and they will be notified.";
        confirmBtn.className = 'btn btn-success';
        confirmBtn.innerHTML = '<span class="material-icons-round">check</span> Yes, Approve';
    } else {
        iconEl.className   = 'rr-confirm-modal-icon reject';
        iconEl.innerHTML   = '<span class="material-icons-round">cancel</span>';
        titleEl.textContent = 'Reject Application?';
        subEl.textContent   = 'The applicant will be notified with the reason you provided.';
        confirmBtn.className = 'btn btn-danger';
        confirmBtn.innerHTML = '<span class="material-icons-round">close</span> Yes, Reject';
    }

    modal.classList.add('active');
}

function closeConfirmModal() {
    const modal = document.getElementById('rrConfirmModal');
    if (modal) modal.classList.remove('active');
    _pendingAction = null;
    _pendingAppId  = null;
    _pendingNote   = '';
}

/* ── Approve Handler ─────────────────────────────────────── */
function handleApprove(appId) {
    openConfirmModal('approve', appId);
}

/* ── Reject Handler ──────────────────────────────────────── */
function handleReject(appId) {
    const noteEl = document.getElementById('adminNoteInput');
    if (!noteEl) { showToast('Admin note field not found', 'error'); return; }

    const note = noteEl.value.trim();
    if (!note) {
        noteEl.focus();
        noteEl.style.borderColor = 'var(--red)';
        showToast('Admin note is required for rejection', 'error');
        return;
    }
    noteEl.style.borderColor = '';
    openConfirmModal('reject', appId, note);
}

/* ── Submit Confirmed Action ─────────────────────────────── */
async function submitConfirmedAction() {
    if (!_pendingAction || !_pendingAppId) return;

    const confirmBtn = document.getElementById('rrConfirmBtn');
    if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<span class="material-icons-round" style="animation:spin .8s linear infinite">refresh</span> Processing…';
    }

    const apiUrl = document.querySelector('meta[name="rr-api-url"]')?.content
        || (window.RR_API_URL || '');

    const fd = new FormData();
    fd.append('action',     _pendingAction);
    fd.append('app_id',     _pendingAppId);
    fd.append('csrf_token', getCsrf());
    if (_pendingAction === 'reject' && _pendingNote) {
        fd.append('admin_note', _pendingNote);
    }

    try {
        const res  = await fetch(apiUrl, { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            showToast(data.message || 'Done!', 'success');
            closeConfirmModal();
            setTimeout(() => window.location.reload(), 900);
        } else {
            showToast(data.message || 'Something went wrong', 'error');
            if (confirmBtn) {
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = _pendingAction === 'approve'
                    ? '<span class="material-icons-round">check</span> Yes, Approve'
                    : '<span class="material-icons-round">close</span> Yes, Reject';
            }
        }
    } catch (err) {
        console.error('[role_requests.js]', err);
        showToast('Network error. Please try again.', 'error');
        if (confirmBtn) confirmBtn.disabled = false;
    }
}

/* ── Modal Overlay Click to Close ───────────────────────── */
function initModalDismiss() {
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', e => {
            if (e.target === overlay) overlay.classList.remove('active');
        });
    });
}

/* ── Spin animation (for loading button) ─────────────────── */
const spinStyle = document.createElement('style');
spinStyle.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
document.head.appendChild(spinStyle);

/* ── Init ────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    initSearchFilter();
    initModalDismiss();

    // Confirm button inside modal
    const confirmBtn = document.getElementById('rrConfirmBtn');
    if (confirmBtn) confirmBtn.addEventListener('click', submitConfirmedAction);

    // Cancel button inside modal
    const cancelBtn = document.getElementById('rrCancelBtn');
    if (cancelBtn) cancelBtn.addEventListener('click', closeConfirmModal);
});
