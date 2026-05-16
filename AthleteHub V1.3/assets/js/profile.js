/**
 * AthleteHub — Profile Page JavaScript
 * Handles: follow/unfollow, verify modal, read-more, post delete,
 *          load-more, followers/following modal, time-ago
 */

document.addEventListener('DOMContentLoaded', () => {
  const basePath = window.AthleteHubBaseUrl || '';


  // ═══════════════════════════════════════
  // 1. FOLLOW / UNFOLLOW
  // ═══════════════════════════════════════
  const followBtn = document.getElementById('followToggleBtn');
  if (followBtn) {
    followBtn.addEventListener('click', () => {
      const userId = followBtn.dataset.userId;
      const currentlyFollowing = followBtn.classList.contains('btn-following');

      followBtn.disabled = true;

      fetch(`${basePath}/api/follow.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ following_id: userId })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          if (data.following) {
            followBtn.classList.remove('btn-follow');
            followBtn.classList.add('btn-following');
            followBtn.textContent = '✓ Following';
            followBtn.dataset.action = 'unfollow';
            showToast('Followed successfully!', 'success', 2000);
          } else {
            followBtn.classList.remove('btn-following');
            followBtn.classList.add('btn-follow');
            followBtn.textContent = '+ Follow';
            followBtn.dataset.action = 'follow';
            showToast('Unfollowed', 'info', 2000);
          }

          // Update stat cards
          const followersEl = document.getElementById('followersCount');
          const followingEl = document.getElementById('followingCount');
          if (followersEl && data.followers_count !== undefined) {
            followersEl.textContent = data.followers_count;
          }
          if (followingEl && data.following_count !== undefined) {
            followingEl.textContent = data.following_count;
          }
        }
      })
      .catch(() => showToast('Failed to update follow status', 'error'))
      .finally(() => { followBtn.disabled = false; });
    });

    // Hover effect for Following button
    followBtn.addEventListener('mouseenter', () => {
      if (followBtn.classList.contains('btn-following')) {
        followBtn.textContent = '✕ Unfollow';
      }
    });
    followBtn.addEventListener('mouseleave', () => {
      if (followBtn.classList.contains('btn-following')) {
        followBtn.textContent = '✓ Following';
      }
    });
  }


  // ═══════════════════════════════════════
  // 2. VERIFICATION MODAL
  // ═══════════════════════════════════════
  const verifyModal   = document.getElementById('verifyModal');
  const openVerifyBtn = document.getElementById('openVerifyModal');
  const closeVerifyBtn = document.getElementById('closeVerifyModal');
  const cancelVerifyBtn = document.getElementById('cancelVerify');
  const verifyForm    = document.getElementById('verifyForm');
  const verifyReason  = document.getElementById('verifyReason');
  const charCount     = document.getElementById('verifyCharCount');

  function openVerify() {
    if (verifyModal) verifyModal.classList.add('open');
  }
  function closeVerify() {
    if (verifyModal) verifyModal.classList.remove('open');
  }

  if (openVerifyBtn)  openVerifyBtn.addEventListener('click', openVerify);
  if (closeVerifyBtn) closeVerifyBtn.addEventListener('click', closeVerify);
  if (cancelVerifyBtn) cancelVerifyBtn.addEventListener('click', closeVerify);
  if (verifyModal) {
    verifyModal.addEventListener('click', (e) => {
      if (e.target === verifyModal) closeVerify();
    });
  }

  // Character counter
  if (verifyReason && charCount) {
    verifyReason.addEventListener('input', () => {
      const len = verifyReason.value.trim().length;
      charCount.textContent = `${len} / 50 minimum characters`;
      charCount.classList.toggle('valid', len >= 50);
    });
  }

  // Submit verification
  if (verifyForm) {
    verifyForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const reason = verifyReason.value.trim();

      if (reason.length < 50) {
        showToast('Please write at least 50 characters.', 'warning');
        return;
      }

      const submitBtn = document.getElementById('submitVerify');
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="material-icons-round" style="font-size:16px">hourglass_empty</span> Submitting...';

      fetch(window.location.pathname, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'apply_verification', reason })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          closeVerify();
          showToast(data.message, 'success', 4000);

          // Update status bar to pending
          const statusWrap = document.getElementById('verifyStatusWrap');
          if (statusWrap) {
            statusWrap.innerHTML = `
              <div class="verify-status-bar verify-bar--pending">
                <span class="material-icons-round">hourglass_top</span>
                Verification Pending — Admin will review your request soon
              </div>
            `;
          }
        } else {
          showToast(data.message || 'Submission failed.', 'error');
        }
      })
      .catch(() => showToast('Network error. Try again.', 'error'))
      .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span class="material-icons-round" style="font-size:16px">verified_user</span> Submit Application';
      });
    });
  }


  // ═══════════════════════════════════════
  // 3. POST READ MORE / SHOW LESS
  // ═══════════════════════════════════════
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.post-read-more');
    if (!btn) return;

    const content = btn.closest('.post-item-content');
    const textEl  = content.querySelector('.post-truncated');

    if (textEl.classList.contains('expanded')) {
      textEl.classList.remove('expanded');
      btn.textContent = 'read more';
    } else {
      textEl.classList.add('expanded');
      btn.textContent = 'show less';
    }
  });


  // ═══════════════════════════════════════
  // 4. POST DELETE (own profile)
  // ═══════════════════════════════════════
  document.addEventListener('click', (e) => {
    const deleteBtn = e.target.closest('.btn-delete-post-profile');
    if (!deleteBtn) return;

    const postId = deleteBtn.dataset.postId;
    if (!confirm('Delete this post?')) return;

    fetch(`${basePath}/api/post.php?action=delete`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ post_id: postId })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        const postItem = deleteBtn.closest('.post-item');
        postItem.classList.add('removing');
        postItem.addEventListener('animationend', () => postItem.remove());

        // Decrement post count
        const countEl = document.getElementById('postCountStat');
        if (countEl) {
          const current = parseInt(countEl.textContent) || 0;
          countEl.textContent = Math.max(0, current - 1);
        }
        showToast('Post deleted', 'success', 2000);
      }
    })
    .catch(() => showToast('Failed to delete post', 'error'));
  });


  // ═══════════════════════════════════════
  // 5. LOAD MORE POSTS
  // ═══════════════════════════════════════
  const loadMoreBtn = document.getElementById('loadMorePosts');
  if (loadMoreBtn) {
    loadMoreBtn.addEventListener('click', () => {
      const profileId = loadMoreBtn.dataset.profileId;
      let currentPage = parseInt(loadMoreBtn.dataset.page) || 1;
      currentPage++;
      loadMoreBtn.dataset.page = currentPage;

      loadMoreBtn.disabled = true;
      loadMoreBtn.textContent = 'Loading...';

      fetch(`${basePath}/pages/profile.php?id=${profileId}&page=${currentPage}&ajax=1`)
        .then(res => res.text())
        .then(html => {
          if (html.trim() === '') {
            loadMoreBtn.textContent = 'No more posts';
            loadMoreBtn.disabled = true;
            loadMoreBtn.style.opacity = '0.5';
          } else {
            const container = document.getElementById('postsListContainer');
            const temp = document.createElement('div');
            temp.innerHTML = html;
            while (temp.firstChild) {
              container.appendChild(temp.firstChild);
            }
            loadMoreBtn.disabled = false;
            loadMoreBtn.textContent = 'Load more →';

            // If fewer than 10 returned, no more pages
            const newItems = html.match(/class="post-item"/g);
            if (!newItems || newItems.length < 10) {
              loadMoreBtn.textContent = 'No more posts';
              loadMoreBtn.disabled = true;
              loadMoreBtn.style.opacity = '0.5';
            }
          }
        })
        .catch(() => {
          showToast('Failed to load more posts', 'error');
          loadMoreBtn.disabled = false;
          loadMoreBtn.textContent = 'Load more →';
        });
    });
  }


  // ═══════════════════════════════════════
  // 6. FOLLOWERS / FOLLOWING MODAL
  // ═══════════════════════════════════════
  const userListModal    = document.getElementById('userListModal');
  const closeUserListBtn = document.getElementById('closeUserListModal');
  const modalTitle       = document.getElementById('userListModalTitle');
  const modalContent     = document.getElementById('userListContent');

  function closeUserListModal() {
    if (userListModal) userListModal.classList.remove('open');
  }

  if (closeUserListBtn) closeUserListBtn.addEventListener('click', closeUserListModal);
  if (userListModal) {
    userListModal.addEventListener('click', (e) => {
      if (e.target === userListModal) closeUserListModal();
    });
  }

  // Click on followers/following/posts stat
  document.querySelectorAll('.stat-card.clickable').forEach(card => {
    card.addEventListener('click', () => {
      const statType = card.dataset.stat; // 'followers', 'following', or 'posts'
      if (!statType) return;

      if (statType === 'posts') {
        const postsCard = document.getElementById('recentPostsCard');
        if (postsCard) {
            postsCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
            postsCard.style.transition = 'box-shadow 0.4s ease';
            postsCard.style.boxShadow = '0 0 0 2px var(--primary), 0 8px 24px rgba(0, 87, 255, 0.25)';
            setTimeout(() => {
                postsCard.style.boxShadow = '';
            }, 1500);
        }
        return;
      }

      if (!userListModal) return;

      modalTitle.textContent = statType === 'followers' ? 'Followers' : 'Following';
      modalContent.innerHTML = '<div class="text-center text-muted text-sm p-4">Loading...</div>';
      userListModal.classList.add('open');

      // Get profile ID from URL
      const urlParams = new URLSearchParams(window.location.search);
      const profileId = urlParams.get('id') || '';

      fetch(`${basePath}/api/follow.php?list=${statType}&user_id=${profileId}`)
        .then(res => res.json())
        .then(data => {
          if (data.success && data.users && data.users.length > 0) {
            modalContent.innerHTML = data.users.map(u => {
              const initials = getInitials(u.name);
              return `
                <div class="user-list-item">
                  <div class="avatar-circle avatar-sm">
                    ${u.profile_pic
                      ? `<img src="${basePath}/uploads/profile_pics/${escapeHtml(u.profile_pic)}" alt="">`
                      : `<span class="avatar-initials" style="font-size:10px">${initials}</span>`
                    }
                  </div>
                  <div class="user-list-info">
                    <a href="${basePath}/pages/profile.php?id=${u.id}">${escapeHtml(u.name)}</a>
                    <span>${escapeHtml(u.sport || u.role)}</span>
                  </div>
                </div>
              `;
            }).join('');
          } else {
            modalContent.innerHTML = '<div class="text-center text-muted text-sm p-4">No users found.</div>';
          }
        })
        .catch(() => {
          modalContent.innerHTML = '<div class="text-center text-muted text-sm p-4">Failed to load.</div>';
        });
    });
  });


  // ═══════════════════════════════════════
  // 7. TIME AGO — Convert timestamps
  // ═══════════════════════════════════════
  document.querySelectorAll('.time-ago[data-time]').forEach(el => {
    if (typeof timeAgo === 'function') {
      el.textContent = timeAgo(el.dataset.time);
    }
  });


  // ═══════════════════════════════════════
  // UTILITIES
  // ═══════════════════════════════════════
  function getInitials(name) {
    const parts = name.split(' ');
    let init = parts[0][0].toUpperCase();
    if (parts.length > 1) init += parts[1][0].toUpperCase();
    return init;
  }

  function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

});
