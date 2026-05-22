/**
 * AthleteHub — Users Search & Filter Logic
 */

document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('searchInput');
    const sortSelect = document.getElementById('sortSelect');
    const roleTabs = document.querySelectorAll('.role-tab');
    const grid = document.getElementById('usersGrid');
    const loadingState = document.getElementById('usersLoading');
    const emptyState = document.getElementById('usersEmpty');
    const paginationContainer = document.getElementById('paginationContainer');
    const clearFiltersBtn = document.getElementById('clearFiltersBtn');
    
    // Check if AthleteHubBaseUrl is defined (it should be in header.php)
    const BASE_URL = window.AthleteHubBaseUrl || '';
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    let activeRoleTab = 'all';
    let debounceTimer;
    let currentPage = 1;

    // Initialize
    fetchUsers();

    // ── Event Listeners ──

    // Debounce search input
    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        currentPage = 1;
        debounceTimer = setTimeout(() => fetchUsers(), 400);
    });

    // Sort select
    sortSelect.addEventListener('change', () => {
        currentPage = 1;
        fetchUsers();
    });

    // Role tabs
    roleTabs.forEach(tab => {
        tab.addEventListener('click', () => {
            roleTabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            activeRoleTab = tab.dataset.role;
            currentPage = 1;
            fetchUsers();
        });
    });

    // Clear filters
    clearFiltersBtn.addEventListener('click', () => {
        searchInput.value = '';
        sortSelect.value = 'newest';
        roleTabs.forEach(t => t.classList.remove('active'));
        document.querySelector('.role-tab[data-role="all"]').classList.add('active');
        activeRoleTab = 'all';
        currentPage = 1;
        fetchUsers();
    });

    // ── Core Functions ──

    async function fetchUsers(page = 1) {
        currentPage = page;
        const q    = searchInput.value.trim();
        const role = activeRoleTab;
        const sort = sortSelect.value;

        showLoader();

        try {
            const endpoint = `${BASE_URL}/api/search_users.php?q=${encodeURIComponent(q)}&role=${role}&sort=${sort}&page=${page}`;
            const res = await fetch(endpoint, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();

            if (data.success) {
                renderCards(data.users);
                renderPagination(data.page, data.pages);
            } else {
                showEmpty();
            }
        } catch (err) {
            showToast('Failed to load users. Please try again.', 'error');
            showEmpty(); // Could be a specific showError state in future
        } finally {
            hideLoader();
        }
    }

    function renderCards(users) {
        if (!users || !users.length) {
            return showEmpty();
        }
        
        showGrid();

        grid.innerHTML = users.map(u => {
            // Generate initials from full name
            const nameParts = (u.full_name || 'U').split(' ');
            const initials = nameParts.length >= 2
                ? (nameParts[0][0] + nameParts[1][0]).toUpperCase()
                : nameParts[0][0].toUpperCase();

            // Generate a consistent vibrant gradient based on name hash
            const gradients = [
                'linear-gradient(135deg, #ec4899, #8b5cf6)', // pink-purple
                'linear-gradient(135deg, #3b82f6, #06b6d4)', // blue-cyan
                'linear-gradient(135deg, #10b981, #3b82f6)', // green-blue
                'linear-gradient(135deg, #f97316, #eab308)'  // orange-yellow
            ];
            const colorIndex = (u.full_name || '').split('').reduce((a, c) => a + c.charCodeAt(0), 0) % gradients.length;
            const bgGradient = gradients[colorIndex];

            // Avatar: show image if exists, otherwise show initials
            const avatarHtml = u.avatar
                ? `<img src="${BASE_URL}/uploads/profile_pics/${e(u.avatar)}" class="user-card__avatar" alt="${e(u.full_name)}" onerror="this.outerHTML='<div class=\\'user-card__avatar user-card__initials\\' style=\\'background:${bgGradient}\\'>${initials}</div>'">`
                : `<div class="user-card__avatar user-card__initials" style="background:${bgGradient}">${initials}</div>`;

            return `
            <div class="user-card compact-glass" onclick="window.location.href='${BASE_URL}/pages/profile.php?id=${u.id}'" style="cursor:pointer;">
                <div class="compact-left">
                    ${avatarHtml}
                    <div class="compact-info">
                        <div class="compact-name">${e(u.full_name)} ${u.is_verified ? '<iconify-icon icon="solar:verified-check-bold" class="verified"></iconify-icon>' : ''}</div>
                        <div class="compact-role">${e(u.role)}</div>
                    </div>
                </div>
                <div class="compact-right">
                    <button class="btn-minimal-follow follow-btn" data-id="${u.id}" onclick="event.stopPropagation()">Follow</button>
                </div>
            </div>
        `}).join('');

        attachFollowListeners();
    }

    function renderPagination(page, totalPages) {
        paginationContainer.innerHTML = '';
        if (totalPages <= 1) {
            paginationContainer.classList.add('hidden');
            return;
        }

        paginationContainer.classList.remove('hidden');

        // Prev
        if (page > 1) {
            const btn = document.createElement('button');
            btn.className = 'pagination-btn';
            btn.innerHTML = '<span class="material-icons-round">chevron_left</span>';
            btn.addEventListener('click', () => fetchUsers(page - 1));
            paginationContainer.appendChild(btn);
        }

        // Pages (simplified)
        for (let i = 1; i <= totalPages; i++) {
            const btn = document.createElement('button');
            btn.className = `pagination-btn ${i === page ? 'active' : ''}`;
            btn.textContent = i;
            btn.addEventListener('click', () => fetchUsers(i));
            paginationContainer.appendChild(btn);
        }

        // Next
        if (page < totalPages) {
            const btn = document.createElement('button');
            btn.className = 'pagination-btn';
            btn.innerHTML = '<span class="material-icons-round">chevron_right</span>';
            btn.addEventListener('click', () => fetchUsers(page + 1));
            paginationContainer.appendChild(btn);
        }
    }

    // ── Follow Logic ──
    function attachFollowListeners() {
        const btns = document.querySelectorAll('.follow-btn');
        btns.forEach(btn => {
            btn.addEventListener('click', async (e) => {
                const targetId = e.target.dataset.id;
                // Assuming api/follow.php exists as per standard
                try {
                    const res = await fetch(`${BASE_URL}/api/follow.php`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ following_id: targetId })
                    });
                    
                    if (!res.ok) throw new Error('Network error');
                    const data = await res.json();
                    
                    if (data.success) {
                        e.target.textContent = data.following ? 'Following' : 'Follow';
                        e.target.classList.toggle('btn-outline', data.following);
                        e.target.classList.toggle('btn--primary', !data.following);
                    } else {
                        alert(data.error || data.message || 'Failed to toggle follow');
                    }
                } catch (err) {
                    showToast('Action failed. Please try again.', 'error');
                }
            });
        });
    }

    // ── UI State Helpers ──
    function showLoader() {
        grid.classList.add('hidden');
        emptyState.classList.add('hidden');
        paginationContainer.classList.add('hidden');
        loadingState.classList.remove('hidden');
    }

    function hideLoader() {
        loadingState.classList.add('hidden');
    }

    function showEmpty() {
        grid.classList.add('hidden');
        paginationContainer.classList.add('hidden');
        emptyState.classList.remove('hidden');
    }

    function showGrid() {
        emptyState.classList.add('hidden');
        grid.classList.remove('hidden');
    }

    // Simple XSS escaping for JS
    function e(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
});
