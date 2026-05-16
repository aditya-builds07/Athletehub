/**
 * AthleteHub — Messages JS
 */

document.addEventListener('DOMContentLoaded', () => {

    const chatBox = document.getElementById('chatBox');
    const msgInput = document.getElementById('messageInput');
    const btnSend = document.getElementById('btnSend');
    
    // Parse from DOM safely to prevent any async racing from footer inline scripts
    const activeUserIdObj = document.getElementById('activeReceiverId');
    const activeUserId = activeUserIdObj ? parseInt(activeUserIdObj.value) : (window.chatConfig?.activeUserId || null);
    
    let lastMsgId = window.chatConfig?.lastMsgId || 0;

    // ──────────────────────────────────────────
    // 1. SCROLL TO BOTTOM
    // ──────────────────────────────────────────
    const scrollToBottom = (smooth = false) => {
        if (!chatBox) return;
        chatBox.scrollTo({
            top: chatBox.scrollHeight,
            behavior: smooth ? 'smooth' : 'auto'
        });
    };
    
    // Initial scroll
    scrollToBottom(false);

    // ──────────────────────────────────────────
    // 2. SEND MESSAGE LOGIC
    // ──────────────────────────────────────────
    const sendMessage = async () => {
        if (!activeUserId || !msgInput) return;
        const text = msgInput.value.trim();
        if (!text) return;

        // Disable UI
        msgInput.disabled = true;
        btnSend.disabled = true;
        btnSend.style.opacity = '0.7';

        // Use application/x-www-form-urlencoded as explicitly evaluated
        const payload = `action=send_message&receiver_id=${activeUserId}&message_text=${encodeURIComponent(text)}`;

        try {
            const res = await fetch('messages.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: payload
            });
            
            const textResponse = await res.text();
            let json;
            try {
                json = JSON.parse(textResponse);
            } catch(e) {
                console.error("Failed to parse JSON PHP output:", textResponse);
                throw new Error("Invalid Server Output");
            }

            if (json.success) {
                const now = new Date();
                let hrs = now.getHours();
                const ampm = hrs >= 12 ? 'PM' : 'AM';
                hrs = hrs % 12 || 12;
                const mns = now.getMinutes().toString().padStart(2, '0');
                const timeStr = `${hrs}:${mns} ${ampm}`;

                const row = document.createElement('div');
                row.className = 'msg-row sent';
                row.dataset.msgId = json.message_id;
                
                if (json.message_id > lastMsgId) lastMsgId = json.message_id;

                const sessionAv = document.querySelector('.header-avatar img')?.src || '';
                const avHtml = sessionAv 
                    ? `<img src="${sessionAv}" alt="">` 
                    : `<div class="conv-av-initials" style="background:#0057FF">O</div>`; 

                row.innerHTML = `
                    <div class="msg-av-sm">${avHtml}</div>
                    <div class="bubble sent">
                        <div class="bubble-text">${escapeHtml(text)}</div>
                        <div class="bubble-time">
                            ${timeStr}
                            <span class="read-tick">
                                <iconify-icon icon="solar:check-bold" style="color:rgba(255,255,255,0.65); font-size:12px; margin-left:2px;"></iconify-icon>
                            </span>
                        </div>
                        <button class="btn-delete-msg" title="Delete message" data-id="${json.message_id}">
                            <iconify-icon icon="solar:trash-bin-bold-duotone" style="font-size:14px"></iconify-icon>
                        </button>
                    </div>
                `;

                const typingObj = document.getElementById('typingIndicator');
                if (typingObj) chatBox.insertBefore(row, typingObj);
                else chatBox.appendChild(row);

                msgInput.value = '';
                scrollToBottom(true);
            } else {
                showToast(json.error || 'Failed to send', 'error');
            }
        } catch (err) {
            showToast('Network error sending message.', 'error');
            console.error(err);
        } finally {
            msgInput.disabled = false;
            btnSend.disabled = false;
            btnSend.style.opacity = '1';
            msgInput.focus();
        }
    };

    if (btnSend) btnSend.addEventListener('click', sendMessage);
    if (msgInput) {
        msgInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
    }

    // ──────────────────────────────────────────
    // 3. DELETE MESSAGE (Event Delegation)
    // ──────────────────────────────────────────
    if (chatBox) {
        chatBox.addEventListener('click', async (e) => {
            const btn = e.target.closest('.btn-delete-msg');
            if (!btn) return;
            
            const msgId = btn.getAttribute('data-id');
            if (confirm("Delete this message?")) {
                const formData = new FormData();
                formData.append('action', 'delete_message');
                formData.append('message_id', msgId);

                try {
                    const res = await fetch('messages.php', { method: 'POST', body: formData });
                    const json = await res.json();
                    if (json.success) {
                        const row = btn.closest('.msg-row');
                        row.style.opacity = '0';
                        setTimeout(() => row.remove(), 200);
                    }
                } catch(e) {
                    showToast('Failed to delete.', 'error');
                }
            }
        });
    }

    // ──────────────────────────────────────────
    // 4. EMOJI PICKER
    // ──────────────────────────────────────────
    const btnEmojiToggle = document.getElementById('btnEmojiToggle');
    const emojiPicker = document.getElementById('emojiPicker');

    if (btnEmojiToggle && emojiPicker && msgInput) {
        btnEmojiToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            emojiPicker.style.display = emojiPicker.style.display === 'none' ? 'grid' : 'none';
        });

        document.addEventListener('click', (e) => {
            if (!emojiPicker.contains(e.target) && e.target !== btnEmojiToggle) {
                emojiPicker.style.display = 'none';
            }
        });

        emojiPicker.addEventListener('click', (e) => {
            if (e.target.classList.contains('ep-emoji')) {
                msgInput.value += e.target.textContent;
                msgInput.focus();
                emojiPicker.style.display = 'none';
            }
        });
    }

    // ──────────────────────────────────────────
    // 5. NEW MESSAGE MODAL & FILTER
    // ──────────────────────────────────────────
    const newMsgModal = document.getElementById('newMsgModal');
    const btnNewMsg = document.getElementById('btnNewMsg');
    const bgClose = document.getElementById('closeNewMsgModal');
    const searchUser = document.getElementById('searchUserInput');
    const userNodes = document.querySelectorAll('.modal-user-item');

    if (newMsgModal && btnNewMsg) {
        const toggleNew = () => newMsgModal.classList.toggle('open');
        btnNewMsg.addEventListener('click', toggleNew);
        if (bgClose) bgClose.addEventListener('click', toggleNew);
        
        newMsgModal.addEventListener('click', e => {
            if (e.target === newMsgModal) toggleNew();
        });

        if (searchUser) {
            searchUser.addEventListener('input', (e) => {
                const term = e.target.value.toLowerCase().trim();
                userNodes.forEach(node => {
                    const n = node.getAttribute('data-name');
                    const s = node.getAttribute('data-sport');
                    if (n.includes(term) || s.includes(term)) node.style.display = 'flex';
                    else node.style.display = 'none';
                });
            });
        }
    }

    // ──────────────────────────────────────────
    // 6. AUTO REFRESH (POLLING)
    // ──────────────────────────────────────────
    if (activeUserId && chatBox) {
        setInterval(async () => {
            try {
                const res = await fetch(`messages.php?ajax=1&to=${activeUserId}&last_id=${lastMsgId}`);
                if (!res.ok) return;
                const json = await res.json();
                
                if (json.success && json.messages.length > 0) {
                    json.messages.forEach(m => {
                        if (m.id > lastMsgId) lastMsgId = m.id;
                        
                        // Parse time
                        const tDate = new Date(m.created_at);
                        let hrs = tDate.getHours();
                        const ampm = hrs >= 12 ? 'PM' : 'AM';
                        hrs = hrs % 12 || 12;
                        const mns = tDate.getMinutes().toString().padStart(2,'0');
                        const ts = `${hrs}:${mns} ${ampm}`;

                        const safeSenderName = escapeHtml(m.sender_name);
                        const safeSenderPic  = m.sender_pic ? escapeHtml(m.sender_pic) : '';

                        // Received logic because we poll to get messages from others mostly (or cross-tab syncs)
                        // In real life, determine sent vs received logic based on m.sender_id
                        const row = document.createElement('div');
                        row.className = 'msg-row received';
                        row.dataset.msgId = m.id;
                        
                        const avHtml = safeSenderPic 
                            ? `<img src="${safeSenderPic}" alt="">` 
                            : `<div class="conv-av-initials" style="background:#0F172A">${safeSenderName.charAt(0).toUpperCase()}</div>`;

                        row.innerHTML = `
                            <div class="msg-av-sm">${avHtml}</div>
                            <div class="bubble received">
                                <div class="bubble-text">${escapeHtml(m.message_text)}</div>
                                <div class="bubble-time">${ts}</div>
                            </div>
                        `;

                        const typingObj = document.getElementById('typingIndicator');
                        if (typingObj) chatBox.insertBefore(row, typingObj);
                        else chatBox.appendChild(row);
                    });
                    scrollToBottom(true);
                }
            } catch(e) {}
        }, 5000);
    }

    // ──────────────────────────────────────────
    // 7. ATTACHMENT TOAST
    // ──────────────────────────────────────────
    const btnAtt = document.getElementById('btnAttach');
    if (btnAtt) {
        btnAtt.addEventListener('click', () => {
            showToast('File sharing coming in V2!', 'success');
        });
    }
    const searchBtn = document.querySelector('.search-chat-btn');
    if (searchBtn) {
        searchBtn.addEventListener('click', () => {
            showToast('Search chat coming in V2!', 'success');
        });
    }

    // ──────────────────────────────────────────
    // 8. CONVERSATION CLICK ROUTING
    // ──────────────────────────────────────────
    const convItems = document.querySelectorAll('.conv-item');
    convItems.forEach(item => {
        item.addEventListener('click', () => {
            const userId = item.getAttribute('data-user-id');
            if (userId) {
                window.location.href = `messages.php?to=${userId}`;
            }
        });
    });

    // Util
    function escapeHtml(unsafe) {
        return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
                     .replace(/"/g, "&quot;").replace(/'/g, "&#039;")
                     .replace(/\n/g, "<br>");
    }
});
