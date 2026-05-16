/**
 * AthleteHub — Recruitment Page JavaScript
 * Handles: apply, post-job, type tabs, read-more, filter pills,
 *          deadline countdown, delete listing, search debounce
 */

document.addEventListener('DOMContentLoaded', () => {
  const basePath = window.AthleteHubBaseUrl || '';


  // ═══════════════════════════════════════
  // 1. APPLY BUTTON → Open Apply Modal
  // ═══════════════════════════════════════
  const applyModal     = document.getElementById('applyModal');
  const closeApplyBtn  = document.getElementById('closeApplyModal');
  const cancelApplyBtn = document.getElementById('cancelApply');
  const confirmApplyBtn = document.getElementById('confirmApply');
  const applyMessage   = document.getElementById('applyMessage');

  function openApplyModal() { if (applyModal) applyModal.classList.add('open'); }
  function closeApplyModal() {
    if (applyModal) applyModal.classList.remove('open');
    if (applyMessage) applyMessage.value = '';
  }

  if (closeApplyBtn) closeApplyBtn.addEventListener('click', closeApplyModal);
  if (cancelApplyBtn) cancelApplyBtn.addEventListener('click', closeApplyModal);
  if (applyModal) {
    applyModal.addEventListener('click', (e) => {
      if (e.target === applyModal) closeApplyModal();
    });
  }

  // Click on Apply Now button
  document.addEventListener('click', (e) => {
    const applyBtn = e.target.closest('.btn-apply');
    if (!applyBtn) return;

    const jobCard  = applyBtn.closest('.job-card');
    const jobId    = applyBtn.dataset.jobId;
    const jobTitle = jobCard.dataset.title || 'this opportunity';
    const poster   = jobCard.dataset.poster || 'Organization';

    // Update modal
    const titleEl = document.getElementById('applyModalTitle');
    const subEl   = document.getElementById('applyModalSub');
    if (titleEl) titleEl.textContent = `Apply for ${jobTitle}`;
    if (subEl)   subEl.textContent = `Posted by ${poster}`;
    if (confirmApplyBtn) confirmApplyBtn.dataset.jobId = jobId;

    // Store reference to the apply button for later updates
    applyModal._triggerBtn = applyBtn;
    applyModal._jobCard = jobCard;

    openApplyModal();
  });

  // Confirm application
  if (confirmApplyBtn) {
    confirmApplyBtn.addEventListener('click', () => {
      const jobId  = confirmApplyBtn.dataset.jobId;
      const msg    = applyMessage ? applyMessage.value.trim() : '';

      confirmApplyBtn.disabled = true;
      confirmApplyBtn.innerHTML = '<span class="material-icons-round" style="font-size:16px">hourglass_empty</span> Submitting...';

      fetch(`${basePath}/api/recruitment.php?action=apply`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ job_id: jobId, message: msg })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          closeApplyModal();
          showToast(data.message || 'Application submitted!', 'success');

          // Update the Apply button to "✓ Applied"
          const triggerBtn = applyModal._triggerBtn;
          if (triggerBtn) {
            triggerBtn.classList.remove('btn-primary', 'btn-apply');
            triggerBtn.classList.add('btn-applied');
            triggerBtn.textContent = '✓ Applied';
            triggerBtn.disabled = true;
          }

          // Update applicant count
          const jobCard = applyModal._jobCard;
          if (jobCard && data.applicant_count !== undefined) {
            const countEl = jobCard.querySelector('.applicant-count');
            if (countEl) countEl.textContent = data.applicant_count;
          }
        } else {
          showToast(data.error || 'Application failed.', 'error');
        }
      })
      .catch(() => showToast('Network error. Try again.', 'error'))
      .finally(() => {
        confirmApplyBtn.disabled = false;
        confirmApplyBtn.innerHTML = '<span class="material-icons-round" style="font-size:16px">check_circle</span> Confirm Application';
      });
    });
  }


  // ═══════════════════════════════════════
  // 2. POST JOB MODAL
  // ═══════════════════════════════════════
  const postJobModal    = document.getElementById('postJobModal');
  const openPostJobBtn  = document.getElementById('openPostJobModal');
  const closePostJobBtn = document.getElementById('closePostJobModal');
  const cancelPostJob   = document.getElementById('cancelPostJob');
  const postJobForm     = document.getElementById('postJobForm');
  const descCharCount   = document.getElementById('descCharCount');
  const descInput       = document.getElementById('jobDescription');

  function openPostJob() { if (postJobModal) postJobModal.classList.add('open'); }
  function closePostJob() { if (postJobModal) postJobModal.classList.remove('open'); }

  if (openPostJobBtn)  openPostJobBtn.addEventListener('click', openPostJob);
  if (closePostJobBtn) closePostJobBtn.addEventListener('click', closePostJob);
  if (cancelPostJob)   cancelPostJob.addEventListener('click', closePostJob);
  if (postJobModal) {
    postJobModal.addEventListener('click', (e) => {
      if (e.target === postJobModal) closePostJob();
    });
  }

  // Description character count
  if (descInput && descCharCount) {
    descInput.addEventListener('input', () => {
      const len = descInput.value.trim().length;
      descCharCount.textContent = `${len} / 30 minimum characters`;
      descCharCount.classList.toggle('valid', len >= 30);
    });
  }

  // Submit post job
  if (postJobForm) {
    postJobForm.addEventListener('submit', (e) => {
      e.preventDefault();

      const title       = document.getElementById('jobTitle').value.trim();
      const sport       = document.getElementById('jobSport').value.trim();
      const location    = document.getElementById('jobLocationInput').value.trim();
      const type        = document.getElementById('jobType').value;
      const description = document.getElementById('jobDescription').value.trim();
      const deadline    = document.getElementById('jobDeadline').value;

      if (!title || !sport || !location || !type || !description || !deadline) {
        showToast('All fields are required.', 'warning');
        return;
      }
      if (description.length < 30) {
        showToast('Description must be at least 30 characters.', 'warning');
        return;
      }

      const submitBtn = document.getElementById('submitPostJob');
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="material-icons-round" style="font-size:16px">hourglass_empty</span> Posting...';

      fetch(window.location.pathname, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'post_job',
          title, sport, location, type, description, deadline
        })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          closePostJob();
          postJobForm.reset();
          showToast(data.message || 'Listing posted!', 'success');
          // Reload to show new listing
          setTimeout(() => window.location.reload(), 800);
        } else {
          showToast(data.error || 'Failed to post.', 'error');
        }
      })
      .catch(() => showToast('Network error. Try again.', 'error'))
      .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span class="material-icons-round" style="font-size:16px">send</span> Post Opportunity';
      });
    });
  }


  // ═══════════════════════════════════════
  // 3. READ MORE / SHOW LESS
  // ═══════════════════════════════════════
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.read-more-link');
    if (!btn) return;

    const descDiv = btn.closest('.job-desc');
    const textEl  = descDiv.querySelector('.job-desc-text');
    const fullText = textEl.dataset.full;

    if (btn.textContent.trim() === 'read more') {
      textEl.innerHTML = fullText.replace(/\n/g, '<br>');
      btn.textContent = 'show less';
    } else {
      const truncated = fullText.substring(0, 180);
      textEl.innerHTML = escapeHtml(truncated) + (fullText.length > 180 ? '<span class="desc-ellipsis">...</span>' : '');
      btn.textContent = 'read more';
    }
  });


  // ═══════════════════════════════════════
  // 4. CLEAR FILTER PILL
  // ═══════════════════════════════════════
  document.addEventListener('click', (e) => {
    const clearBtn = e.target.closest('.pill-clear');
    if (!clearBtn) return;

    const filterKey = clearBtn.dataset.filter;
    const url = new URL(window.location.href);
    url.searchParams.delete(filterKey);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
  });


  // ═══════════════════════════════════════
  // 5. DELETE LISTING (own)
  // ═══════════════════════════════════════
  document.addEventListener('click', (e) => {
    const deleteBtn = e.target.closest('.btn-delete-listing');
    if (!deleteBtn) return;

    const jobId = deleteBtn.dataset.jobId;
    if (!confirm('Delete this listing?')) return;

    fetch(`${basePath}/api/recruitment.php?action=delete`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ job_id: jobId })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        const card = deleteBtn.closest('.job-card');
        card.classList.add('removing');
        card.addEventListener('animationend', () => card.remove());
        showToast('Listing deleted', 'success', 2000);
      } else {
        showToast(data.error || 'Failed to delete', 'error');
      }
    })
    .catch(() => showToast('Network error', 'error'));
  });


  // ═══════════════════════════════════════
  // 6. SEARCH INPUT DEBOUNCE
  // ═══════════════════════════════════════
  const searchInput = document.getElementById('filterSearch');
  const filterForm  = document.getElementById('filterForm');
  let searchTimer;

  if (searchInput && filterForm) {
    searchInput.addEventListener('input', () => {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(() => {
        filterForm.submit();
      }, 600);
    });
  }


  // ═══════════════════════════════════════
  // 7. DEADLINE COUNTDOWN (dynamic text)
  // ═══════════════════════════════════════
  document.querySelectorAll('.deadline-warn').forEach(el => {
    // Already rendered server-side, no extra work needed
  });


  // ═══════════════════════════════════════
  // UTILITY
  // ═══════════════════════════════════════
  function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

});
