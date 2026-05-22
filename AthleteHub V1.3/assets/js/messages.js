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
                // JSON parsing failed
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

                formatSystemMessages();
                msgInput.value = '';
                scrollToBottom(true);
            } else {
                showToast(json.error || 'Failed to send', 'error');
            }
        } catch (err) {
            showToast('Network error sending message.', 'error');
            // Error handled by toast
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
                    
                    formatSystemMessages();
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

    // ──────────────────────────────────────────
    // 9. SYSTEM MESSAGE FORMATTER (OFFICIAL LETTER)
    // ──────────────────────────────────────────
    function formatSystemMessages() {
        document.querySelectorAll('.bubble-text').forEach(el => {
            if (el.dataset.formatted) return;
            
            let htmlText = el.innerHTML;
            let text = el.innerText || el.textContent;
            
            if (text.includes('application for the') && text.includes('role')) {
                let isApproved = text.includes('approved') && !text.includes('not approved');
                let isRejected = text.includes('not approved') || text.includes('rejected');
                
                if (isApproved || isRejected) {
                    el.dataset.formatted = "true";
                    
                    let roleMatch = text.match(/application for the (.*?) role/i);
                    let role = roleMatch ? roleMatch[1].trim() : 'Requested';
                    
                    let today = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
                    let refId = Math.floor(Math.random() * 90000) + 10000;
                    let userName = "Applicant";
                    let chatNameObj = document.querySelector('.chat-name');
                    if (chatNameObj) {
                        let cn = chatNameObj.textContent.trim();
                        if (cn.toLowerCase() !== 'admin') {
                            userName = cn;
                        }
                    }
                    
                    let bodyHtml = '';
                    if (isApproved) {
                        bodyHtml = `
                            Dear ${userName},<br><br>
                            We are pleased to inform you that your application for the <b>${role}</b> role has been successfully approved after review by the AthleteHub administration team.<br><br>
                            Your account permissions have now been updated.<br><br>
                            <b>New permissions:</b><br>
                            &bull; Browse athlete profiles<br>
                            &bull; Post recruitment opportunities<br>
                            &bull; Send recruitment offers<br>
                            &bull; Access advanced filters<br><br>
                            <b>Rules & Conditions:</b><br>
                            &bull; Maintain professional conduct.<br>
                            &bull; Follow community guidelines.<br>
                            &bull; Misuse of privileges may result in suspension.<br><br>
                            If you need assistance, contact the administration team.
                        `;
                    } else {
                        let reasonMatch = text.split(/Reason:/i);
                        let reason = reasonMatch.length > 1 ? reasonMatch[1].trim() : "Not specified.";
                        
                        bodyHtml = `
                            Dear ${userName},<br><br>
                            We regret to inform you that your application for the <b>${role}</b> role has been reviewed and was not approved at this time.<br><br>
                            <b>Reason for rejection:</b><br>
                            ${reason}<br><br>
                            Please review your application details and ensure all provided information is accurate and complete before submitting a new request.<br><br>
                            If you believe this was a mistake or need further assistance, please contact the administration team.
                        `;
                    }
                    
                    let cardHtml = `
                        <div style="background:#fff; border-radius:12px; border:1px solid #e2e8f0; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05); padding:24px; max-width:700px; color:#1e293b; font-family:sans-serif; text-align:left; line-height:1.7; width:100%; box-sizing:border-box;">
                            <div style="border-bottom:1px solid #f1f5f9; padding-bottom:16px; margin-bottom:16px;">
                                <div style="font-size:12px; text-transform:uppercase; color:#64748b; font-weight:700; letter-spacing:0.05em; margin-bottom:4px;">System Notification</div>
                                <div style="font-size:12px; color:#94a3b8; margin-bottom:12px;">AthleteHub Administration</div>
                                <div style="display:flex; justify-content:space-between; font-size:12px; color:#64748b;">
                                    <span>Date: ${today}</span>
                                    <span>Reference ID: AH-${refId}</span>
                                </div>
                            </div>
                            
                            <div style="font-size:18px; font-weight:600; color:#0f172a; margin-bottom:16px;">
                                ${role} Role Application ${isApproved ? 'Approval' : 'Rejection'}
                            </div>
                            
                            <div style="font-size:14.5px; color:#334155;">
                                ${bodyHtml}
                                <br><br>
                                <span style="color:#64748b;">Regards,<br>AthleteHub Administration Team</span>
                            </div>
                        </div>
                    `;
                    
                    el.innerHTML = cardHtml;
                    
                    let bubble = el.closest('.bubble');
                    if (bubble) {
                        bubble.style.background = 'transparent';
                        bubble.style.padding = '0';
                        bubble.style.boxShadow = 'none';
                        bubble.style.border = 'none';
                        bubble.style.maxWidth = '100%';
                    }
                }
            }
        });
    }
    
    // Format existing messages on load
    formatSystemMessages();

    // Util
    function escapeHtml(unsafe) {
        return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
                     .replace(/"/g, "&quot;").replace(/'/g, "&#039;")
                     .replace(/\n/g, "<br>");
    }
});
