/**
 * WhatsApp 2-Way Chat Modal Controller
 * Fetches conversation history, formats timestamps & status checkmarks, and handles quick replies.
 */

window.openChatModal = async function(leadId, leadName, leadPhone) {
    document.getElementById('chat_lead_id').value = leadId;
    document.getElementById('chat_lead_name').textContent = leadName;
    document.getElementById('chat_lead_phone').textContent = leadPhone;
    
    // Configure click-to-call dialer link
    const phoneLink = document.getElementById('chat_lead_phone_link');
    if (phoneLink) {
        phoneLink.href = 'tel:' + leadPhone.replace(/[^0-9+]/g, '');
    }

    document.getElementById('chat_message_input').value = '';

    const timeline = document.getElementById('chatTimeline');
    timeline.innerHTML = '<div style="text-align: center; color: #64748b; font-size: 12px; padding: 30px;">Loading chat history...</div>';

    openModal('chatHistoryModal');

    await loadChatHistory(leadId);

    setTimeout(() => {
        const input = document.getElementById('chat_message_input');
        if (input) input.focus();
    }, 150);
};

async function loadChatHistory(leadId) {
    const timeline = document.getElementById('chatTimeline');

    try {
        const response = await fetch(`api/chat.php?lead_id=${leadId}`);
        const res = await response.json();

        if (!res.success) {
            throw new Error(res.error || 'Failed to load chat');
        }

        const messages = res.messages;
        if (messages.length === 0) {
            timeline.innerHTML = `
                <div style="text-align: center; color: #64748b; font-size: 13px; padding: 40px;">
                    No WhatsApp messages in this conversation yet.<br>
                    <span style="font-size: 11px; color: #94a3b8;">Send the first message using the box below!</span>
                </div>
            `;
            return;
        }

        timeline.innerHTML = messages.map(msg => renderChatBubble(msg)).join('');
        // Scroll to bottom
        timeline.scrollTop = timeline.scrollHeight;

    } catch (err) {
        timeline.innerHTML = `<div style="text-align: center; color: #dc2626; font-size: 13px; padding: 20px;">Error: ${err.message}</div>`;
    }
}

function renderChatBubble(msg) {
    const isOut = msg.direction === 'outgoing';
    const status = (msg.status || 'sent').toLowerCase();

    let ticksHtml = '';
    if (isOut) {
        if (status === 'sent') {
            ticksHtml = '<span style="color: #8696a0;">✓</span>';
        } else if (status === 'delivered') {
            ticksHtml = '<span style="color: #8696a0;">✓✓</span>';
        } else if (status === 'read') {
            ticksHtml = '<span style="color: #34b7f1; font-weight: bold;">✓✓</span>';
        } else if (status === 'scheduled') {
            ticksHtml = `<span style="color: #64748b; font-size: 11px; font-weight: 600;" title="Scheduled for ${escapeHtml(msg.formatted_scheduled_time || msg.scheduled_at)}">Scheduled</span>`;
        } else if (status === 'failed') {
            ticksHtml = `<span style="color: #dc2626; font-size: 11px; font-weight: 600;" title="${escapeHtml(msg.failure_reason || 'Failed')}">Failed</span>`;
        }
    }

    return `
        <div class="chat-bubble ${isOut ? 'outgoing' : 'incoming'}">
            <div class="bubble-ref">ID: ${escapeHtml(msg.message_id)}</div>
            <div class="bubble-content">${escapeHtml(msg.message_content)}</div>
            <div class="bubble-meta">
                <span>${msg.formatted_time || msg.sent_at}</span>
                ${ticksHtml}
            </div>
        </div>
    `;
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Quick reply from chat composer
document.addEventListener('DOMContentLoaded', () => {
    const chatForm = document.getElementById('chatQuickSendForm');
    if (!chatForm) return;

    chatForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const input = document.getElementById('chat_message_input');
        const leadId = parseInt(document.getElementById('chat_lead_id').value, 10);
        const leadPhone = document.getElementById('chat_lead_phone').textContent;
        const messageText = input.value.trim();

        if (!messageText) return;

        input.disabled = true;

        try {
            const response = await fetch('api/send.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    lead_id: leadId,
                    phone: leadPhone,
                    message: messageText
                })
            });

            const res = await response.json();
            if (!res.success) throw new Error(res.error);

            input.value = '';
            showToast('WhatsApp reply sent!', 'success');
            // Reload chat history
            await loadChatHistory(leadId);

        } catch (err) {
            showToast('Could not send message: ' + err.message, 'error');
        } finally {
            input.disabled = false;
            input.focus();
        }
    });
});
