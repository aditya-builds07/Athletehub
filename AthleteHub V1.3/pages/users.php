<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';
require_once '../includes/session.php';
require_login(BASE_URL . '/index.php');

$pageTitle = 'People';
$pageCSS = ['users'];
require_once '../includes/header.php';
?>

<div class="users-container">
    <form style="display:none"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"></form>
    <div class="users-header glass-card">
        <div class="users-header__top">
            <h1>Explore Athletes & Professionals</h1>
            <p class="text-muted">Discover and connect with the sports community.</p>
        </div>

        <div class="users-header__controls">
            <!-- Search Bar -->
            <div class="search-wrapper relative">
                <span class="material-icons-round search-icon" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted);">search</span>
                <input type="text" id="searchInput" class="glass-input" placeholder="Search by name, sport, or location..." style="padding-left: 40px; width: 100%;">
            </div>

            <!-- Sort Dropdown -->
            <select id="sortSelect" class="glass-input sort-select">
                <option value="newest">Sort by: Newest</option>
                <option value="name_asc">Sort by: Name A-Z</option>
                <option value="most_followed">Sort by: Most Followed</option>
            </select>
        </div>

        <!-- Role Filter Tabs -->
        <div class="role-tabs">
            <button class="role-tab active" data-role="all">All</button>
            <button class="role-tab" data-role="athlete">Athletes</button>
            <button class="role-tab" data-role="coach">Coaches</button>
            <button class="role-tab" data-role="recruiter">Recruiters</button>
            <button class="role-tab" data-role="club">Clubs</button>
        </div>
    </div>

    <!-- Users Grid -->
    <div id="usersGrid" class="users-grid">
        <!-- User cards injected here via JS -->
    </div>
    
    <!-- Loading State -->
    <div id="usersLoading" class="users-loading hidden glass-card" style="text-align: center; padding: var(--space-8);">
        <span class="material-icons-round" style="animation: spin 1s linear infinite; font-size: 32px; color: var(--primary);">refresh</span>
        <p class="mt-2 text-muted">Fetching users...</p>
    </div>

    <!-- Empty State -->
    <div id="usersEmpty" class="users-empty hidden glass-card" style="text-align: center; padding: var(--space-8);">
        <span class="material-icons-round empty-icon" style="font-size: 48px; color: var(--text-light);">person_off</span>
        <h3 class="mt-4">No users found</h3>
        <p class="text-muted">Try adjusting your search or filters to find what you're looking for.</p>
        <button id="clearFiltersBtn" class="btn btn-outline mt-4">Clear Filters</button>
    </div>

    <!-- Pagination -->
    <div id="paginationContainer" class="pagination-container hidden" style="display: flex; justify-content: center; gap: var(--space-2); margin-top: var(--space-6);">
        <!-- Pagination buttons injected here -->
    </div>
</div>

<script src="<?= ASSETS ?>/js/users.js?v=<?= time() ?>" defer></script>

<?php require_once '../includes/footer.php'; ?>
