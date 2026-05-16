document.addEventListener('DOMContentLoaded', () => {
    // UI Elements
    const grid = document.getElementById('streamsGrid');
    
    // Go Live Modal
    const goLiveModal = document.getElementById('goLiveModal');
    const btnGoLive = document.getElementById('btnGoLive');
    const btnCloseGoLive = document.getElementById('closeGoLiveModal');
    const goLiveForm = document.getElementById('goLiveForm');

    // Watch Modal
    const watchModal = document.getElementById('watchModal');
    const btnCloseWatch = document.getElementById('closeWatchModal');
    const watchIframe = document.getElementById('watchIframe');
    const watchTitle = document.getElementById('watchTitle');
    const watchHost = document.getElementById('watchHost');

    // 1. Fetch Streams
    const fetchStreams = async () => {
        try {
            const res = await fetch('../api/livestream.php?action=fetch');
            const json = await res.json();

            if (json.success) {
                renderStreams(json.streams);
            }
        } catch (err) {
            console.error('Failed to fetch streams', err);
        }
    };

    const renderStreams = (streams) => {
        if (!grid) return;
        
        if (streams.length === 0) {
            grid.innerHTML = `
                <div class="col-span-full text-center text-muted py-10 glass-card">
                    <span class="material-icons-round text-5xl mb-2 opacity-50">videocam_off</span>
                    <p class="text-sm font-semibold">No live streams at the moment.</p>
                </div>
            `;
            return;
        }

        grid.innerHTML = streams.map(s => {
            const ytId = extractYoutubeId(s.youtube_url);
            const thumbUrl = ytId ? `https://img.youtube.com/vi/${ytId}/mqdefault.jpg` : '../assets/images/default-stream.jpg';
            const isHost = s.is_host === true;

            return `
                <div class="glass-card flex flex-col overflow-hidden" data-id="${s.id}" style="padding: 0;">
                    <div class="relative cursor-pointer play-stream-trigger group" data-yt="${ytId}" data-title="${escapeHtml(s.title)}" data-host="${escapeHtml(s.host_name)}">
                        <img src="${thumbUrl}" alt="Thumbnail" class="w-full h-48 object-cover">
                        <div class="absolute top-2 left-2 bg-red-600 text-white text-xs font-bold px-2 py-1 rounded flex items-center gap-1 shadow-glow animate-pulse">
                            <span class="material-icons-round text-[12px]">sensors</span> LIVE
                        </div>
                        <div class="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                            <span class="material-icons-round text-white text-5xl drop-shadow-lg">play_circle</span>
                        </div>
                    </div>
                    <div class="p-4 flex-1 flex flex-col">
                        <h4 class="font-bold text-md mb-1 line-clamp-2">${escapeHtml(s.title)}</h4>
                        <p class="text-xs font-semibold text-primary mb-2 flex items-center gap-1">
                            <span class="material-icons-round text-[14px]">verified</span> ${escapeHtml(s.host_name)}
                        </p>
                        ${s.tournament_name ? `<p class="text-xs text-muted mb-3 flex items-center gap-1"><span class="material-icons-round text-[14px]">emoji_events</span> ${escapeHtml(s.tournament_name)}</p>` : ''}
                        
                        <div class="mt-auto pt-3 border-t border-gray-200 border-opacity-10 flex gap-2">
                            <button class="btn btn-primary btn-sm flex-1 play-stream-trigger" data-yt="${ytId}" data-title="${escapeHtml(s.title)}" data-host="${escapeHtml(s.host_name)}">
                                Watch Now
                            </button>
                            ${isHost ? `<button class="btn btn-sm btn-end-stream" data-id="${s.id}" style="background:var(--bg-glass); color:var(--red-500); border: 1px solid var(--red-500);">End</button>` : ''}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    };

    const extractYoutubeId = (url) => {
        const match = url.match(/(?:https?:\/\/)?(?:www\.)?youtube\.com\/watch\?.*v=([^&\s]+)|(?:https?:\/\/)?(?:www\.)?youtu\.be\/([^?\s]+)|(?:https?:\/\/)?(?:www\.)?youtube\.com\/live\/([^?\s]+)|(?:https?:\/\/)?(?:www\.)?youtube\.com\/embed\/([^?\s]+)/i);
        return match ? (match[1] || match[2] || match[3] || match[4]) : null;
    };

    function escapeHtml(unsafe) {
        if (!unsafe) return '';
        return String(unsafe).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    // 2. Go Live Modal Logic
    if (goLiveModal && btnGoLive) {
        btnGoLive.addEventListener('click', () => goLiveModal.classList.add('open'));
        const closeGL = () => goLiveModal.classList.remove('open');
        btnCloseGoLive?.addEventListener('click', closeGL);
        goLiveModal.addEventListener('click', (e) => { if (e.target === goLiveModal) closeGL(); });

        goLiveForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = goLiveForm.querySelector('button[type="submit"]');
            const origText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="material-icons-round rotate-anim">sync</span> Starting...';
            submitBtn.disabled = true;

            const formData = new FormData(goLiveForm);
            const dataObj = {};
            formData.forEach((val, key) => dataObj[key] = val);

            try {
                const res = await fetch('../api/livestream.php?action=create', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(dataObj)
                });
                const json = await res.json();
                
                if (json.success) {
                    if (window.showToast) showToast('You are now live!', 'success');
                    else alert('You are now live!');
                    
                    closeGL();
                    goLiveForm.reset();
                    fetchStreams(); 
                } else {
                    if (window.showToast) showToast(json.error, 'error');
                    else alert(json.error);
                }
            } catch (err) {
                if (window.showToast) showToast('Network error', 'error');
            } finally {
                submitBtn.innerHTML = origText;
                submitBtn.disabled = false;
            }
        });
    }

    // 3. Watch Modal Logic
    if (watchModal) {
        const closeWatch = () => {
            watchModal.classList.remove('open');
            watchIframe.src = ''; 
        };
        btnCloseWatch?.addEventListener('click', closeWatch);
        watchModal.addEventListener('click', (e) => { if (e.target === watchModal) closeWatch(); });

        document.body.addEventListener('click', (e) => {
            const trigger = e.target.closest('.play-stream-trigger');
            if (trigger) {
                const ytId = trigger.getAttribute('data-yt');
                const title = trigger.getAttribute('data-title');
                const host = trigger.getAttribute('data-host');
                
                if (ytId && ytId !== 'null') {
                    watchTitle.textContent = title;
                    const safeHost = escapeHtml(host);
                    watchHost.innerHTML = `<span class="material-icons-round text-sm">verified</span> <span>${safeHost}</span>`;
                    watchIframe.src = `https://www.youtube.com/embed/${ytId}?autoplay=1`;
                    watchModal.classList.add('open');
                } else {
                    if (window.showToast) showToast('Stream not available.', 'error');
                }
            }
            
            const endBtn = e.target.closest('.btn-end-stream');
            if (endBtn) {
                const streamId = endBtn.getAttribute('data-id');
                if (confirm('Are you sure you want to end this stream?')) {
                    endStream(streamId);
                }
            }
        });
    }

    const endStream = async (streamId) => {
        try {
            const res = await fetch('../api/livestream.php?action=end', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ stream_id: streamId })
            });
            const json = await res.json();
            if (json.success) {
                if (window.showToast) showToast('Stream ended.', 'success');
                fetchStreams();
            } else {
                if (window.showToast) showToast(json.error, 'error');
            }
        } catch (err) {
            if (window.showToast) showToast('Network error', 'error');
        }
    };

    // 4. Init & Polling
    fetchStreams();
    setInterval(fetchStreams, 60000); // Poll every 60s
});
