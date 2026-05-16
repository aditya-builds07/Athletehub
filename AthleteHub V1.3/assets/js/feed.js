/**
 * AthleteHub — Feed Page JavaScript
 * Handles: likes, comments, create post, follow, delete, infinite scroll
 */

document.addEventListener('DOMContentLoaded', () => {
  const basePath = window.AthleteHubBaseUrl || '';

  // ═══════════════════════════════════════
  // 1. TIME AGO — Convert timestamps
  // ═══════════════════════════════════════
  document.querySelectorAll('.time-ago[data-time]').forEach(el => {
    el.textContent = timeAgo(el.dataset.time);
  });


  // ═══════════════════════════════════════
  // 2. LIKE TOGGLE
  // ═══════════════════════════════════════
  document.addEventListener('click', function (e) {
    const likeBtn = e.target.closest('.btn-like');
    if (!likeBtn || likeBtn.disabled) return;

    const postCard = likeBtn.closest('.post-card');
    const postId = postCard.dataset.postId;

    likeBtn.disabled = true;

    fetch(`${basePath}/api/post.php?action=like`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ post_id: postId })
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          const icon = likeBtn.querySelector('.like-icon');
          const label = likeBtn.querySelector('.like-label');

          if (data.liked) {
            likeBtn.classList.add('liked');
            icon.textContent = 'favorite';
            label.textContent = 'Liked';
          } else {
            likeBtn.classList.remove('liked');
            icon.textContent = 'favorite_border';
            label.textContent = 'Like';
          }

          // Update stats bar
          const statsBar = postCard.querySelector('.post-stats-bar');
          if (statsBar) {
            const likeStat = statsBar.querySelector('.post-stat');
            if (likeStat) {
              likeStat.innerHTML = `<span class="material-icons-round" style="font-size:14px;color:var(--accent)">favorite</span> ${data.like_count}`;
            }
          }
        }
      })
      .catch(() => showToast('Failed to update like', 'error'))
      .finally(() => { likeBtn.disabled = false; });
  });


  // ═══════════════════════════════════════
  // 3. COMMENT EXPAND / COLLAPSE
  // ═══════════════════════════════════════
  document.addEventListener('click', function (e) {
    const commentBtn = e.target.closest('.btn-comment');
    if (!commentBtn) return;

    const postCard = commentBtn.closest('.post-card');
    const postId = postCard.dataset.postId;
    const section = document.getElementById(`comments-${postId}`);

    if (!section) return;

    const isExpanded = section.classList.contains('expanded');

    if (isExpanded) {
      section.classList.remove('expanded');
      return;
    }

    section.classList.add('expanded');

    // Load comments if not already loaded
    if (section.dataset.loaded === 'false') {
      loadComments(postId, section);
    }

    // Focus input
    const input = section.querySelector('.comment-input');
    if (input) setTimeout(() => input.focus(), 300);
  });

  function loadComments(postId, section) {
    const list = section.querySelector('.comments-list');
    list.innerHTML = '<div class="text-center text-muted text-sm">Loading comments...</div>';

    fetch(`${basePath}/api/post.php?action=get_comments&post_id=${postId}`)
      .then(res => res.json())
      .then(data => {
        list.innerHTML = '';
        if (data.comments && data.comments.length > 0) {
          data.comments.forEach(c => {
            list.appendChild(createCommentElement(c));
          });
        } else {
          list.innerHTML = '<div class="text-center text-muted text-sm" style="padding:8px 0">No comments yet. Be the first!</div>';
        }
        section.dataset.loaded = 'true';
      })
      .catch(() => {
        list.innerHTML = '<div class="text-center text-muted text-sm">Failed to load comments.</div>';
      });
  }

  function createCommentElement(comment) {
    const div = document.createElement('div');
    div.className = 'comment-item';

    const initials = getInitials(comment.name);
    const time = typeof timeAgo === 'function' ? timeAgo(comment.created_at) : comment.created_at;

    div.innerHTML = `
      <div class="avatar-circle avatar-sm">
        <span class="avatar-initials" style="font-size:10px">${initials}</span>
      </div>
      <div class="comment-bubble">
        <span class="comment-author">${escapeHtml(comment.name)}</span>
        <p class="comment-text">${escapeHtml(comment.comment_text)}</p>
        <span class="comment-time">${time}</span>
      </div>
    `;
    return div;
  }

  function getInitials(name) {
    const parts = name.split(' ');
    let init = parts[0][0].toUpperCase();
    if (parts.length > 1) init += parts[1][0].toUpperCase();
    return init;
  }


  // ═══════════════════════════════════════
  // 4. SUBMIT COMMENT
  // ═══════════════════════════════════════
  document.addEventListener('submit', function (e) {
    if (!e.target.classList.contains('comment-form')) return;
    e.preventDefault();

    const form = e.target;
    const postId = form.dataset.postId;
    const input = form.querySelector('.comment-input');
    const text = input.value.trim();

    if (!text) return;

    const btn = form.querySelector('.btn-send-comment');
    btn.disabled = true;

    fetch(`${basePath}/api/post.php?action=comment`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ post_id: postId, comment_text: text })
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          const section = document.getElementById(`comments-${postId}`);
          const list = section.querySelector('.comments-list');

          // Remove "No comments" placeholder if present
          const placeholder = list.querySelector('.text-muted');
          if (placeholder) placeholder.remove();

          list.appendChild(createCommentElement({
            name: data.user_name,
            comment_text: text,
            created_at: data.created_at
          }));

          input.value = '';

          // Update comment count in post card
          const postCard = form.closest('.post-card');
          const commentBtn = postCard.querySelector('.btn-comment');
          // Increment visual count if needed

          showToast('Comment added!', 'success', 2000);
        }
      })
      .catch(() => showToast('Failed to add comment', 'error'))
      .finally(() => { btn.disabled = false; });
  });


  // ═══════════════════════════════════════
  // 5. CREATE POST MODAL
  // ═══════════════════════════════════════
  const modal = document.getElementById('createPostModal');
  const openBtns = ['openCreateModal', 'openCreateModalBtn', 'openCreateModalPhoto', 'emptyStatePost'];
  const closeBtn = document.getElementById('closeCreateModal');
  const cancelBtn = document.getElementById('cancelCreatePost');
  const createForm = document.getElementById('createPostForm');
  
  let currentBase64Image = null;
  const imageFileInput = document.getElementById('postImageFile');
  const imagePreview = document.getElementById('imagePreview');
  const imagePreviewImg = document.getElementById('imagePreviewImg');

  if (imageFileInput) {
    imageFileInput.addEventListener('change', function(e) {
      const file = e.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = function(evt) {
          const img = new Image();
          img.onload = function() {
            const canvas = document.createElement('canvas');
            const MAX_WIDTH = 1200;
            const MAX_HEIGHT = 1200;
            let width = img.width;
            let height = img.height;

            if (width > height && width > MAX_WIDTH) {
              height *= MAX_WIDTH / width;
              width = MAX_WIDTH;
            } else if (height > MAX_HEIGHT) {
              width *= MAX_HEIGHT / height;
              height = MAX_HEIGHT;
            }

            canvas.width = width;
            canvas.height = height;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, width, height);

            currentBase64Image = canvas.toDataURL('image/jpeg', 0.85);

            if (imagePreview) imagePreview.style.display = 'block';
            if (imagePreviewImg) imagePreviewImg.src = currentBase64Image;
          };
          img.src = evt.target.result;
        };
        reader.readAsDataURL(file);
      } else {
        currentBase64Image = null;
        if (imagePreview) imagePreview.style.display = 'none';
        if (imagePreviewImg) imagePreviewImg.src = '';
      }
    });
  }

  openBtns.forEach(id => {
    const el = document.getElementById(id);
    if (el) {
      el.addEventListener('click', () => {
        if (modal) modal.classList.add('open');
        // Focus textarea
        const textarea = document.getElementById('postContent');
        if (textarea) setTimeout(() => textarea.focus(), 300);
      });
    }
  });

  function closeModal() {
    if (modal) modal.classList.remove('open');
  }

  if (closeBtn) closeBtn.addEventListener('click', closeModal);
  if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

  // Close on overlay click
  if (modal) {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeModal();
    });
  }

  // Submit create post
  if (createForm) {
    createForm.addEventListener('submit', (e) => {
      e.preventDefault();

      const content = document.getElementById('postContent').value.trim();
      const imageUrl = currentBase64Image;

      if (!content) {
        showToast('Please write something!', 'warning');
        return;
      }

      const submitBtn = document.getElementById('submitCreatePost');
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="material-icons-round" style="font-size:16px">hourglass_empty</span> Posting...';

      fetch(`${basePath}/api/post.php?action=create`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ content, image_url: imageUrl || null })
      })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            showToast('Post created successfully! 🎉', 'success');
            closeModal();
            createForm.reset();
            currentBase64Image = null;
            if (imagePreview) imagePreview.style.display = 'none';
            if (imagePreviewImg) imagePreviewImg.src = '';

            // Fetch the newly created post HTML
            fetch(`${basePath}/pages/feed.php?ajax=1&single_post_id=${data.post_id}`)
              .then(res => res.text())
              .then(html => {
                if (html.trim() !== '') {
                  const temp = document.createElement('div');
                  temp.innerHTML = html;
                  const newPostCard = temp.firstElementChild;

                  const feedPosts = document.getElementById('feedPosts');
                  const emptyState = feedPosts.querySelector('.empty-state');
                  if (emptyState) emptyState.remove();

                  feedPosts.prepend(newPostCard);
                }
              });
          } else {
            showToast(data.message || 'Failed to create post', 'error');
          }
        })
        .catch(() => showToast('Network error. Try again.', 'error'))
        .finally(() => {
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<span class="material-icons-round" style="font-size:16px">send</span> Post';
        });
    });
  }


  // ═══════════════════════════════════════
  // 6. FOLLOW BUTTON
  // ═══════════════════════════════════════
  document.addEventListener('click', function (e) {
    const followBtn = e.target.closest('.btn-follow');
    if (!followBtn || followBtn.classList.contains('following')) return;

    const userId = followBtn.dataset.userId;
    followBtn.disabled = true;

    fetch(`${basePath}/api/follow.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ following_id: userId })
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          followBtn.classList.add('following');
          followBtn.textContent = 'Following ✓';
          showToast('Followed successfully!', 'success', 2000);
        }
      })
      .catch(() => showToast('Failed to follow', 'error'))
      .finally(() => { followBtn.disabled = false; });
  });


  // ═══════════════════════════════════════
  // 7. POST DELETE (3-dot menu)
  // ═══════════════════════════════════════

  // Toggle post dropdown menu
  document.addEventListener('click', function (e) {
    const menuBtn = e.target.closest('.post-menu-btn');

    // Close all open dropdowns first
    document.querySelectorAll('.post-dropdown.open').forEach(d => {
      if (!menuBtn || !menuBtn.parentElement.contains(d)) {
        d.classList.remove('open');
      }
    });

    if (!menuBtn) return;

    const dropdown = menuBtn.nextElementSibling;
    if (dropdown) {
      dropdown.classList.toggle('open');
      e.stopPropagation();
    }
  });

  // Delete post
  document.addEventListener('click', function (e) {
    const deleteBtn = e.target.closest('.btn-delete-post');
    if (!deleteBtn) return;

    const postId = deleteBtn.dataset.postId;

    if (!confirm('Are you sure you want to delete this post?')) return;

    fetch(`${basePath}/api/post.php?action=delete`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ post_id: postId })
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          const postCard = deleteBtn.closest('.post-card');
          postCard.classList.add('removing');
          postCard.addEventListener('animationend', () => postCard.remove());
          showToast('Post deleted', 'success', 2000);
        }
      })
      .catch(() => showToast('Failed to delete post', 'error'));
  });

  // Close dropdown on outside click
  document.addEventListener('click', function () {
    document.querySelectorAll('.post-dropdown.open').forEach(d => d.classList.remove('open'));
  });


  // ═══════════════════════════════════════
  // 8. INFINITE SCROLL
  // ═══════════════════════════════════════
  let currentPage = 1;
  let isLoading = false;
  let hasMore = true;

  const feedPosts = document.getElementById('feedPosts');
  const feedLoader = document.getElementById('feedLoader');

  window.addEventListener('scroll', debounce(() => {
    if (isLoading || !hasMore) return;

    const scrollBottom = window.innerHeight + window.scrollY;
    const docHeight = document.documentElement.scrollHeight;

    if (scrollBottom >= docHeight - 400) {
      loadMorePosts();
    }
  }, 150));

  function loadMorePosts() {
    isLoading = true;
    currentPage++;

    if (feedLoader) feedLoader.style.display = 'flex';

    fetch(`${basePath}/pages/feed.php?page=${currentPage}&ajax=1`)
      .then(res => res.text())
      .then(html => {
        if (html.trim() === '') {
          hasMore = false;
        } else {
          const temp = document.createElement('div');
          temp.innerHTML = html;
          while (temp.firstChild) {
            feedPosts.appendChild(temp.firstChild);
          }
        }
      })
      .catch(() => showToast('Failed to load more posts', 'error'))
      .finally(() => {
        isLoading = false;
        if (feedLoader) feedLoader.style.display = 'none';
      });
  }


  // ═══════════════════════════════════════
  // UTILITY: Escape HTML
  // ═══════════════════════════════════════
  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

});
