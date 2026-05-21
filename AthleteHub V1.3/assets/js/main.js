/**
 * AthleteHub — Global JavaScript
 * Navigation, dropdown, utility functions, CSRF protection
 */

/* ── CSRF Token — auto-inject on all POST/PUT/DELETE fetch calls ── */
function getCSRFToken() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta ? meta.getAttribute('content') : '';
}

// Monkey-patch fetch to auto-include CSRF header on mutating requests
(function() {
  const originalFetch = window.fetch;
  window.fetch = function(url, options = {}) {
    const method = (options.method || 'GET').toUpperCase();
    if (method !== 'GET' && method !== 'HEAD') {
      options.headers = options.headers || {};
      // Support both Headers object and plain object
      if (options.headers instanceof Headers) {
        if (!options.headers.has('X-CSRF-Token')) {
          options.headers.set('X-CSRF-Token', getCSRFToken());
        }
      } else {
        if (!options.headers['X-CSRF-Token']) {
          options.headers['X-CSRF-Token'] = getCSRFToken();
        }
      }
    }
    return originalFetch.call(this, url, options);
  };
})();

document.addEventListener('DOMContentLoaded', () => {

  /* ── Scroll shadow on nav ── */
  const nav = document.getElementById('mainNav');
  if (nav) {
    window.addEventListener('scroll', () => {
      nav.classList.toggle('scrolled', window.scrollY > 8);
    });
  }

  /* ── User dropdown toggle ── */
  const avatarBtn  = document.getElementById('userAvatarBtn');
  const dropdown   = document.getElementById('userDropdown');

  if (avatarBtn && dropdown) {
    avatarBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      dropdown.classList.toggle('open');
      
      // Auto-collapse live search layout to prevent UI collision
      const searchDropdown = document.getElementById('searchDropdown');
      if (searchDropdown && searchDropdown.classList.contains('open')) {
        searchDropdown.classList.remove('open');
      }
    });

    document.addEventListener('click', (e) => {
      if (!dropdown.contains(e.target)) {
        dropdown.classList.remove('open');
      }
    });
  }

  /* ── Mobile hamburger ── */
  const hamburger = document.getElementById('navHamburger');
  const navLinks  = document.getElementById('navLinks');

  if (hamburger && navLinks) {
    hamburger.addEventListener('click', () => {
      navLinks.classList.toggle('open');
      const icon = hamburger.querySelector('.material-icons-round');
      icon.textContent = navLinks.classList.contains('open') ? 'close' : 'menu';
    });
  }

  /* ── Check Active Livestreams ── */
  const liveDot = document.getElementById('liveDotIndicator');
  if (liveDot) {
    fetch(`${window.AthleteHubBaseUrl || ''}/api/livestream.php?action=fetch`)
      .then(res => res.json())
      .then(data => {
        if (data.success && data.streams && data.streams.length > 0) {
          liveDot.style.display = 'block';
        } else {
          liveDot.style.display = 'none';
        }
      })
      .catch(e => { /* Stream check failed silently */ });
  }

  /* ── Global Search ── */
  const searchInput = document.getElementById('globalSearchInput');
  const searchDropdown = document.getElementById('searchDropdown');
  const searchList = document.getElementById('searchResultsList');
  const searchLoading = document.getElementById('searchLoading');

  if (searchInput && searchDropdown) {
    const handleSearch = () => {
      const q = searchInput.value.trim();
      if (q.length < 2) {
        searchDropdown.classList.remove('open');
        return;
      }
      
      searchDropdown.classList.add('open');
      searchList.innerHTML = '';
      searchLoading.style.display = 'block';

      fetch(`${window.AthleteHubBaseUrl || ''}/api/search.php?q=${encodeURIComponent(q)}`)
        .then(res => res.json())
        .then(data => {
          searchLoading.style.display = 'none';
          searchList.innerHTML = '';
          
          if (!data.results || data.results.length === 0) {
            searchList.innerHTML = '<div class="search-loading">No results found</div>';
            return;
          }

          data.results.forEach(user => {
            const a = document.createElement('a');
            a.className = 'search-result-item';
            a.href = `${window.AthleteHubBaseUrl || ''}/pages/profile.php?id=${parseInt(user.id)}`;
            
            if (user.profile_pic) {
              const img = document.createElement('img');
              img.src = `${window.AthleteHubBaseUrl || ''}/uploads/profile_pics/${user.profile_pic}`;
              img.style.width = '32px';
              img.style.height = '32px';
              img.style.borderRadius = '50%';
              img.style.objectFit = 'cover';
              img.alt = '';
              a.appendChild(img);
            } else {
              const div = document.createElement('div');
              div.className = 'avatar-circle';
              div.style.width = '32px';
              div.style.height = '32px';
              div.style.fontSize = '12px';
              div.textContent = user.name ? user.name.charAt(0) : '?';
              a.appendChild(div);
            }
              
            const infoDiv = document.createElement('div');
            const nameSpan = document.createElement('span');
            nameSpan.className = 'search-result-name';
            nameSpan.textContent = user.name;
            const roleSpan = document.createElement('span');
            roleSpan.className = 'search-result-role';
            roleSpan.textContent = user.role ? (user.role.charAt(0).toUpperCase() + user.role.slice(1)) : '';
            
            infoDiv.appendChild(nameSpan);
            infoDiv.appendChild(roleSpan);
            
            a.appendChild(infoDiv);
            searchList.appendChild(a);
          });
        })
        .catch(() => {
          searchLoading.style.display = 'none';
          searchList.innerHTML = '<div class="search-loading">Search failed</div>';
        });
    };

    searchInput.addEventListener('input', debounce(handleSearch, 300));
    
    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
      if (!searchInput.contains(e.target) && !searchDropdown.contains(e.target)) {
        searchDropdown.classList.remove('open');
      }
    });
    
    // Show previous results if input is focused again
    searchInput.addEventListener('focus', () => {
      if (searchInput.value.trim().length >= 2 && searchList.innerHTML !== '') {
        searchDropdown.classList.add('open');
      }
    });
  }

});


/* ═══════════════════════════════════════
   Utility Functions
   ═══════════════════════════════════════ */

/**
 * Show a toast notification
 * @param {string} message
 * @param {string} type - 'success' | 'error' | 'info' | 'warning'
 * @param {number} duration - ms (default 3000)
 */
function showToast(message, type = 'info', duration = 3000) {
  let container = document.querySelector('.toast-container');
  if (!container) {
    container = document.createElement('div');
    container.className = 'toast-container';
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;

  const icons = {
    success: 'check_circle',
    error:   'error',
    info:    'info',
    warning: 'warning'
  };

  const iconSpan = document.createElement('span');
  iconSpan.className = 'material-icons-round';
  iconSpan.textContent = icons[type] || 'info';

  const textSpan = document.createElement('span');
  textSpan.textContent = String(message);

  toast.appendChild(iconSpan);
  toast.appendChild(textSpan);

  container.appendChild(toast);

  setTimeout(() => {
    toast.classList.add('toast-exit');
    toast.addEventListener('animationend', () => toast.remove());
  }, duration);
}


/**
 * Format a date string to relative time (e.g. "3 min ago")
 * @param {string} dateStr
 * @returns {string}
 */
function timeAgo(dateStr) {
  const now  = new Date();
  const date = new Date(dateStr);
  const diff = Math.floor((now - date) / 1000);

  if (diff < 60)    return 'Just now';
  if (diff < 3600)  return `${Math.floor(diff / 60)}m ago`;
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
  if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}


function debounce(fn, delay = 300) {
  let timer;
  return function(...args) {
    clearTimeout(timer);
    timer = setTimeout(() => fn.apply(this, args), delay);
  };
}


/**
 * Global HTML escape utility — prevents XSS in dynamic content.
 * @param {string} str
 * @returns {string}
 */
function escapeHtml(str) {
  if (!str) return '';
  const div = document.createElement('div');
  div.textContent = String(str);
  return div.innerHTML;
}
