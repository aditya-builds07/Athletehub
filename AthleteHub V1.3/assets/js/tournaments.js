/**
 * AthleteHub — Tournaments Page JS
 * Handles Register modals, calendars, filtering.
 */

document.addEventListener('DOMContentLoaded', () => {

    // ──────────────────────────────────────────
    // 1. REGISTER MODAL LOGIC
    // ──────────────────────────────────────────
    const registerBtns = document.querySelectorAll('.btn-register');
    const modal = document.getElementById('registerModal');
    const btnCancel = document.getElementById('cancelRegister');
    const btnClose = document.getElementById('closeRegisterModal');
    const btnConfirm = document.getElementById('btn-confirm-register');

    let currentActionBtn = null;

    if (modal) {
        // Open Modal
        registerBtns.forEach(btn => {
            btn.addEventListener('click', async (e) => {
                const tId = btn.getAttribute('data-tournament-id');
                const tName = btn.getAttribute('data-name');
                currentActionBtn = btn;
                
                // Show basic info while loading
                document.getElementById('modTName').textContent = tName;
                document.getElementById('modTLoc').textContent = 'Loading...';
                document.getElementById('modTDates').textContent = 'Loading...';
                document.getElementById('modTPrize').textContent = 'Loading...';
                document.getElementById('modTRegs').textContent = '...';

                modal.classList.add('open');
                
                // Fetch Details
                try {
                    const res = await fetch(`../api/tournament.php?action=details&tournament_id=${tId}`);
                    const json = await res.json();
                    
                    if (json.success && json.data) {
                        const d = json.data;
                        document.getElementById('modTLoc').textContent = d.location;
                        document.getElementById('modTDates').textContent = `${formatShortDate(d.start_date)} – ${formatShortDate(d.end_date)}`;
                        document.getElementById('modTPrize').textContent = d.prize_info;
                        document.getElementById('modTRegs').textContent = `${d.reg_count} athletes already registered`;
                        btnConfirm.setAttribute('data-tournament-id', tId);
                        btnConfirm.disabled = false;
                    } else {
                        showToast(json.error || 'Failed to load details', 'error');
                        modal.classList.remove('open');
                    }
                } catch (err) {
                    showToast('Network error loading details', 'error');
                    modal.classList.remove('open');
                }
            });
        });

        // Close logic
        const closeModal = () => modal.classList.remove('open');
        btnCancel?.addEventListener('click', closeModal);
        btnClose?.addEventListener('click', closeModal);
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });

        // Confirm Registration
        btnConfirm?.addEventListener('click', async () => {
            const tId = btnConfirm.getAttribute('data-tournament-id');
            const originalText = btnConfirm.innerHTML;
            btnConfirm.innerHTML = '<span class="material-icons-round rotate-anim">sync</span> Registering...';
            btnConfirm.disabled = true;

            try {
                const res = await fetch('../api/tournament.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'register', tournament_id: tId })
                });
                const json = await res.json();

                if (json.success) {
                    closeModal();
                    showToast(json.message, 'success');
                    
                    if (currentActionBtn) {
                        // Change button to registered stat
                        currentActionBtn.disabled = true;
                        currentActionBtn.className = 'btn btn-sm registered-btn';
                        currentActionBtn.style.cssText = 'background:transparent; color:var(--success); border:1px solid var(--success);';
                        currentActionBtn.textContent = '✓ Registered';

                        // update reg count on card
                        const card = currentActionBtn.closest('.glass-card');
                        if (card) {
                            const rb = card.querySelector('.reg-val');
                            if (rb) rb.textContent = json.reg_count;
                        }
                    }
                } else {
                    showToast(json.error, 'error');
                    btnConfirm.innerHTML = originalText;
                    btnConfirm.disabled = false;
                }
            } catch (err) {
                showToast('Network error processing registration', 'error');
                btnConfirm.innerHTML = originalText;
                btnConfirm.disabled = false;
            }
        });
    }

    // ──────────────────────────────────────────
    // 2. NOTIFY ME BUTTONS
    // ──────────────────────────────────────────
    document.querySelectorAll('.notify-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            showToast("We'll remind you when registration opens!", 'success');
            btn.textContent = '✓ Notified';
            btn.disabled = true;
        });
    });

    // ──────────────────────────────────────────
    // 3. AUTO SUBMIT ON DROPDOWN CHANGE & SEARCH
    // ──────────────────────────────────────────
    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        const selects = filterForm.querySelectorAll('select');
        selects.forEach(sel => {
            sel.addEventListener('change', () => filterForm.submit());
        });

        const searchInput = filterForm.querySelector('input[name="search"]');
        let dTimeout;
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                clearTimeout(dTimeout);
                dTimeout = setTimeout(() => filterForm.submit(), 400);
            });
        }
    }

    // ──────────────────────────────────────────
    // 4. ACTIVE FILTER CLEARING
    // ──────────────────────────────────────────
    document.querySelectorAll('.pill-clear').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const param = e.currentTarget.getAttribute('data-key');
            const url = new URL(window.location.href);
            url.searchParams.delete(param);
            window.location.href = url.toString();
        });
    });

    // ──────────────────────────────────────────
    // 5. SIDEBAR WIDGET CLICKS
    // ──────────────────────────────────────────
    document.querySelectorAll('.sport-item').forEach(item => {
        item.addEventListener('click', () => {
            const sport = item.getAttribute('data-sport');
            const url = new URL(window.location.href);
            url.searchParams.set('sport', sport);
            window.location.href = url.toString();
        });
    });

    document.querySelectorAll('.upcoming-item').forEach(item => {
        item.addEventListener('click', () => {
            const tId = item.getAttribute('data-id');
            const targetCard = document.querySelector(`.glass-card[data-id="${tId}"]`);
            if (targetCard) {
                targetCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                targetCard.style.outline = '2px solid var(--primary)';
                setTimeout(() => { targetCard.style.outline = 'none'; }, 1500);
            }
        });
    });


    // ──────────────────────────────────────────
    // 6. MINI CALENDAR LOGIC
    // ──────────────────────────────────────────
    const calContainer = document.getElementById('miniCalendar');
    if (calContainer) {
        // tDates from global window scope (PHP passed)
        // Set dates to YYYY-MM-DD
        const eventDatesMap = {};
        if (window.tDates && Array.isArray(window.tDates)) {
            window.tDates.forEach(dStr => {
                const dt = new Date(dStr);
                if (!isNaN(dt)) {
                    const key = `${dt.getFullYear()}-${String(dt.getMonth()+1).padStart(2,'0')}-${String(dt.getDate()).padStart(2,'0')}`;
                    eventDatesMap[key] = true;
                }
            });
        }

        let currDate = new Date();
        const calTitleParams = document.getElementById('calTitle'); // the span inside H5

        function renderCalendar() {
            const y = currDate.getFullYear();
            const m = currDate.getMonth();
            const firstDay = new Date(y, m, 1).getDay();
            const daysInMonth = new Date(y, m + 1, 0).getDate();
            const daysInPrevMonth = new Date(y, m, 0).getDate();
            
            // Re-label title
            const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
            if (calTitleParams) {
                calTitleParams.innerHTML = `${monthNames[m]} ${y}`;
            }

            let html = `
                <div class="cal-header">
                    <button type="button" class="cal-nav-btn" id="calPrev">◀</button>
                    <span style="font-size:12px; font-weight:700;">${monthNames[m]} ${y}</span>
                    <button type="button" class="cal-nav-btn" id="calNext">▶</button>
                </div>
                <div class="cal-grid">
                    <div class="cal-day-name">Su</div>
                    <div class="cal-day-name">Mo</div>
                    <div class="cal-day-name">Tu</div>
                    <div class="cal-day-name">We</div>
                    <div class="cal-day-name">Th</div>
                    <div class="cal-day-name">Fr</div>
                    <div class="cal-day-name">Sa</div>
            `;

            // fill grid
            const today = new Date();
            const isTodayMonth = (m === today.getMonth() && y === today.getFullYear());
            const todayDateStr = `${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`;

            let dayCounter = 1;
            let nextMonthDay = 1;
            let cells = '';

            for (let i = 0; i < 42; i++) {
                if (i < firstDay) {
                    // prev month days
                    let dNum = daysInPrevMonth - firstDay + i + 1;
                    cells += `<div class="cal-day other-month">${dNum}</div>`;
                } else if (dayCounter > daysInMonth) {
                    // next month days
                    cells += `<div class="cal-day other-month">${nextMonthDay++}</div>`;
                } else {
                    // current month
                    const currentLoopDateStr = `${y}-${String(m+1).padStart(2,'0')}-${String(dayCounter).padStart(2,'0')}`;
                    let cls = 'cal-day';
                    
                    if (isTodayMonth && dayCounter === today.getDate()) {
                        cls += ' today';
                    } else if (eventDatesMap[currentLoopDateStr]) {
                        cls += ' has-event';
                    }

                    cells += `<div class="${cls}">${dayCounter}</div>`;
                    dayCounter++;
                }
            }
            html += cells + `</div>`;
            calContainer.innerHTML = html;

            document.getElementById('calPrev').addEventListener('click', () => {
                currDate.setMonth(currDate.getMonth() - 1);
                renderCalendar();
            });
            document.getElementById('calNext').addEventListener('click', () => {
                currDate.setMonth(currDate.getMonth() + 1);
                renderCalendar();
            });
        }
        
        renderCalendar();
    }


    // ──────────────────────────────────────────
    // 7. CLUB ACTIONS (Create & View Participants)
    // ──────────────────────────────────────────
    const createModal = document.getElementById('createTournamentModal');
    const btnOpenCreate = document.getElementById('openCreateTournamentModal');
    const btnCloseCreate = document.getElementById('closeCreateTournamentModal');
    const createForm = document.getElementById('createTournamentForm');

    if (createModal && btnOpenCreate) {
        btnOpenCreate.addEventListener('click', () => {
            createModal.classList.add('open');
        });

        const closeCreate = () => createModal.classList.remove('open');
        btnCloseCreate?.addEventListener('click', closeCreate);
        createModal.addEventListener('click', (e) => {
            if (e.target === createModal) closeCreate();
        });

        createForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = createForm.querySelector('button[type="submit"]');
            const origText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="material-icons-round rotate-anim">sync</span> Creating...';
            submitBtn.disabled = true;

            const formData = new FormData(createForm);
            const dataObj = {};
            formData.forEach((val, key) => { dataObj[key] = val; });

            try {
                const res = await fetch('../api/tournament.php?action=create', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(dataObj)
                });
                const json = await res.json();

                if (json.success) {
                    showToast(json.message, 'success');
                    closeCreate();
                    createForm.reset();
                    setTimeout(() => { window.location.reload(); }, 1000);
                } else {
                    showToast(json.error || 'Failed to create tournament', 'error');
                    submitBtn.innerHTML = origText;
                    submitBtn.disabled = false;
                }
            } catch (err) {
                showToast('Network error while creating tournament', 'error');
                submitBtn.innerHTML = origText;
                submitBtn.disabled = false;
            }
        });
    }

    // View Participants Modal handling
    const partModal = document.getElementById('viewParticipantsModal');
    const btnClosePart = document.getElementById('closeViewParticipantsModal');
    const partListContent = document.getElementById('participantsListContent');
    const partModalSub = document.getElementById('partModalSub');

    if (partModal) {
        const closePart = () => partModal.classList.remove('open');
        btnClosePart?.addEventListener('click', closePart);
        partModal.addEventListener('click', (e) => {
            if (e.target === partModal) closePart();
        });

        document.querySelectorAll('.btn-view-participants').forEach(btn => {
            btn.addEventListener('click', async () => {
                const tId = btn.getAttribute('data-tournament-id');
                const tName = btn.getAttribute('data-name');
                
                if (partModalSub) partModalSub.textContent = `Loading participant list for "${tName}"...`;
                if (partListContent) partListContent.innerHTML = '<div class="text-center py-4 text-muted"><span class="material-icons-round rotate-anim">sync</span></div>';
                partModal.classList.add('open');

                try {
                    const res = await fetch(`../api/get_tournament_participants.php?tournament_id=${tId}`);
                    if (!res.ok) {
                        throw new Error('Unauthorized or Server Error');
                    }
                    const json = await res.json();

                    if (json.success) {
                        const parts = json.participants || [];
                        if (partModalSub) partModalSub.textContent = `Total registered: ${parts.length} athlete(s)`;

                        if (parts.length === 0) {
                            partListContent.innerHTML = `
                                <div class="glass-card text-center py-4">
                                    <p class="text-sm text-muted">No athletes have registered yet.</p>
                                </div>
                            `;
                        } else {
                            partListContent.innerHTML = parts.map(p => {
                                const safeName  = escapeHtml(p.name || 'A');
                                const safeSport = escapeHtml(p.sport || 'Athlete');
                                const safeRole  = escapeHtml(p.role || 'athlete');
                                const regDate   = new Date(p.registered_at).toLocaleDateString();
                                return `
                                <div class="glass-card flex items-center justify-between p-2 mb-2" style="border-left: 3px solid var(--primary);">
                                    <div class="flex items-center gap-2">
                                        <div class="avatar-circle avatar-sm" style="width:32px;height:32px;">
                                            <span class="avatar-initials" style="font-size:11px;">${safeName.charAt(0).toUpperCase()}</span>
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold" style="margin:0;">${safeName}</p>
                                            <p class="text-xs text-muted" style="margin:0;">${safeSport} • Registered: ${regDate}</p>
                                        </div>
                                    </div>
                                    <span class="text-xs font-semibold px-2 py-1 rounded" style="background:var(--bg-glass); color:var(--primary);">${safeRole}</span>
                                </div>
                            `}).join('');
                        }
                    } else {
                        throw new Error(json.error || 'Failed to load participants');
                    }
                } catch (err) {
                    if (partModalSub) partModalSub.textContent = 'Error loading list.';
                    if (partListContent) partListContent.innerHTML = `<div class="text-center py-3 text-red-500 text-sm">${err.message}</div>`;
                }
            });
        });

        document.querySelectorAll('.btn-manage-tournament').forEach(btn => {
            btn.addEventListener('click', () => {
                showToast('Tournament management options (Update/Close) will be available soon.', 'success');
            });
        });
    }


    // ──────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────
    function formatShortDate(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr);
        if (isNaN(d)) return dateStr;
        const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        return `${months[d.getMonth()]} ${d.getDate()}`;
    }

});
