/**
 * AthleteHub — Sports News Page JS
 */

document.addEventListener('DOMContentLoaded', () => {

    // ──────────────────────────────────────────
    // 1. ARTICLE CARD CLICKS (Card wide links)
    // ──────────────────────────────────────────
    const articleCards = document.querySelectorAll('.view-article');
    articleCards.forEach(card => {
        card.addEventListener('click', (e) => {
            // Prevent if they clicked the share button inside the card or a specific link
            if (e.target.closest('button') || e.target.closest('a')) return;
            
            const articleId = card.getAttribute('data-article-id');
            if (articleId) {
                window.location.href = `news.php?article=${articleId}`;
            }
        });
    });

    // ──────────────────────────────────────────
    // 2. SHARE BUTTONS
    // ──────────────────────────────────────────
    const shareBtns = document.querySelectorAll('.btn-share-icon, .btn-share');
    shareBtns.forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.stopPropagation(); // prevent card click
            const urlToCopy = btn.getAttribute('data-url');
            if (!urlToCopy) return;

            try {
                await navigator.clipboard.writeText(urlToCopy);
                showToast('Link copied to clipboard!', 'success');
            } catch (err) {
                showToast('Failed to copy link.', 'error');
            }
        });
    });

    // ──────────────────────────────────────────
    // 3. SEARCH DEBOUNCE
    // ──────────────────────────────────────────
    const searchForm = document.getElementById('searchForm');
    if (searchForm) {
        const searchInput = searchForm.querySelector('.search-input');
        let dTimeout;

        if (searchInput) {
            searchInput.addEventListener('input', () => {
                clearTimeout(dTimeout);
                dTimeout = setTimeout(() => {
                    searchForm.submit();
                }, 400);
            });
        }
    }

    // ──────────────────────────────────────────
    // 4. ADMIN WRITE MODAL
    // ──────────────────────────────────────────
    const writeModal = document.getElementById('writeModal');
    const btnOpenWrite = document.getElementById('btnOpenWrite');
    const btnCloseWrite = document.getElementById('closeWriteModal');
    const btnCancelWrite = document.getElementById('cancelWriteModal');
    const writeForm = document.getElementById('writeForm');
    const writeContent = document.getElementById('writeContent');
    const charCount = document.getElementById('charCount');

    if (writeModal && btnOpenWrite) {
        const closeModal = () => {
            writeModal.classList.remove('open');
            if (writeForm) writeForm.reset();
            if (charCount) charCount.textContent = '0';
        };

        btnOpenWrite.addEventListener('click', () => writeModal.classList.add('open'));
        btnCloseWrite?.addEventListener('click', closeModal);
        btnCancelWrite?.addEventListener('click', closeModal);

        writeModal.addEventListener('click', (e) => {
            if (e.target === writeModal) closeModal();
        });

        if (writeContent && charCount) {
            writeContent.addEventListener('input', () => {
                charCount.textContent = writeContent.value.length;
                if (writeContent.value.length > 2000) {
                    charCount.style.color = 'var(--danger)';
                } else {
                    charCount.style.color = '';
                }
            });
        }

        if (writeForm) {
            writeForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const btnPub = document.getElementById('btnPublish');
                const prevText = btnPub.innerHTML;
                btnPub.innerHTML = 'Publishing...';
                btnPub.disabled = true;

                const formData = new FormData(writeForm);

                try {
                    const res = await fetch('news.php', {
                        method: 'POST',
                        body: formData
                    });
                    const json = await res.json();

                    if (json.success) {
                        showToast(json.message, 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        showToast(json.error, 'error');
                        btnPub.innerHTML = prevText;
                        btnPub.disabled = false;
                    }
                } catch (err) {
                    showToast('Network error publishing article.', 'error');
                    btnPub.innerHTML = prevText;
                    btnPub.disabled = false;
                }
            });
        }
    }

    // ──────────────────────────────────────────
    // 5. FETCH LIVE NEWS API
    // ──────────────────────────────────────────
    async function fetchLiveNews() {
        const liveNewsCards = document.getElementById('liveNewsCards');
        const liveNewsError = document.getElementById('liveNewsError');
        const loadingIndicator = document.getElementById('liveNewsLoading');
        
        if (!liveNewsCards) return;

        try {
            // Fetch Times of India Sports RSS via rss2json API
            const apiUrl = 'https://api.rss2json.com/v1/api.json?rss_url=https://timesofindia.indiatimes.com/rssfeeds/4719148.cms';
            const response = await fetch(apiUrl);
            
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.json();
            
            if (data.status !== 'ok' || !data.items || data.items.length === 0) {
                throw new Error('API returned invalid or empty data');
            }
            
            if (loadingIndicator) loadingIndicator.remove();
            
            let html = '';
            const articles = data.items.slice(0, 10); // Top 10 articles
            
            articles.forEach(article => {
                const safeTitle = escapeHtml(article.title);
                const safeLink = encodeURI(article.link);
                const dateRaw = new Date(article.pubDate);
                
                // Formulate 'Time Ago'
                const diff = (new Date() - dateRaw) / 1000;
                let timeAgoVal = '';
                if (diff < 60) timeAgoVal = 'Just now';
                else if (diff < 3600) timeAgoVal = Math.floor(diff / 60) + 'm ago';
                else if (diff < 86400) timeAgoVal = Math.floor(diff / 3600) + 'h ago';
                else timeAgoVal = Math.floor(diff / 86400) + 'd ago';
                
                const imageStr = article.thumbnail || article.enclosure?.link;
                const safeImage = imageStr ? escapeHtml(imageStr) : '';
                
                let imageHtml = `<div class="list-img" style="background:var(--surface-hover); color:var(--text-muted);">📰</div>`;
                if (safeImage) {
                     imageHtml = `
                     <div class="list-img" style="padding:0; overflow:hidden; width:180px; min-height:140px; flex-shrink:0;">
                        <img src="${safeImage}" alt="News" style="width:100%; height:100%; object-fit:cover;">
                     </div>`;
                }

                html += `
                    <div class="list-card" data-url="${safeLink}" style="cursor:pointer; padding:0; border:1px solid rgba(0,0,0,0.06);">
                        ${imageHtml}
                        <div class="list-body">
                            <div class="list-inner" style="flex:1; justify-content: center;">
                                <span class="micro-pill" style="background:rgba(22, 163, 74, 0.1); color:#16A34A; margin-bottom: 4px;">Live Update</span>
                                <h4 class="list-title" style="margin-bottom: 6px;">${safeTitle}</h4>
                                <span class="list-time" style="display:flex; align-items:center; gap:4px;">
                                    <span class="material-icons-round" style="font-size:12px;">schedule</span> ${timeAgoVal}
                                </span>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            liveNewsCards.innerHTML = html;

            // Attach safe click handlers (no inline onclick)
            liveNewsCards.querySelectorAll('.list-card[data-url]').forEach(card => {
                card.addEventListener('click', () => {
                    const url = card.getAttribute('data-url');
                    if (url) window.open(url, '_blank', 'noopener,noreferrer');
                });
            });
            
        } catch (error) {
            console.error('Error fetching live news:', error);
            if (loadingIndicator) loadingIndicator.remove();
            if (liveNewsError) liveNewsError.style.display = 'inline';
        }
    }
    
    // Initialize Fetch
    fetchLiveNews();
});
