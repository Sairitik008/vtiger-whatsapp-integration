let hasSimulatorChanges = false;

window.openSimulatorModal = async function() {
    hasSimulatorChanges = false;
    openModal('simulatorModal');
    await refreshSimulatorData();
};

// When simulator modal closes, refresh page if changes occurred
document.addEventListener('DOMContentLoaded', () => {
    const simModal = document.getElementById('simulatorModal');
    if (simModal) {
        simModal.querySelectorAll('[data-close-modal]').forEach(btn => {
            btn.addEventListener('click', () => {
                if (hasSimulatorChanges) {
                    window.location.reload();
                }
            });
        });
    }
});

async function refreshSimulatorData() {
    try {
        const response = await fetch('api/simulator_helper.php');
        const res = await response.json();

        if (res.success) {
            populateMessageSelect(res.messages);
            if (res.logs && res.logs.length > 0) {
                renderWebhookLog(res.logs[0]);
            }
        }
        await checkScheduledQueue();
    } catch (err) {
        console.error('Error loading simulator data:', err);
    }
}

function populateMessageSelect(messages) {
    const select = document.getElementById('sim_message_select');
    if (!select) return;

    if (messages.length === 0) {
        select.innerHTML = '<option value="">No outgoing messages sent yet</option>';
        return;
    }

    select.innerHTML = messages.map(m => {
        const leadLabel = m.lead_name ? `${m.lead_name} (${m.recipient_number})` : m.recipient_number;
        const snippet = m.message_content.length > 30 ? m.message_content.substring(0, 30) + '...' : m.message_content;
        return `<option value="${m.message_id}">[${m.status.toUpperCase()}] ${m.message_id} - ${leadLabel} ("${snippet}")</option>`;
    }).join('');
}

// 1. Trigger Status Update Callback (delivered, read, failed)
window.triggerStatusWebhook = async function(status) {
    const select = document.getElementById('sim_message_select');
    const messageId = select.value;

    if (!messageId) {
        showToast('Please send a WhatsApp message from a Lead first so there is a message to update.', 'warning');
        return;
    }

    const payload = {
        event_type: 'status_update',
        message_id: messageId,
        status: status,
        failure_reason: status === 'failed' ? 'Destination handset reported delivery failure' : null
    };

    await dispatchWebhook(payload);
};

// 2. Trigger Inbound Customer Reply
window.triggerInboundWebhook = async function() {
    const phone = document.getElementById('sim_incoming_phone').value.trim();
    const message = document.getElementById('sim_incoming_text').value.trim();

    if (!phone || !message) {
        showToast('Please enter both customer phone and message text.', 'warning');
        return;
    }

    const payload = {
        event_type: 'incoming_message',
        sender_number: phone,
        recipient_number: '919876543210',
        message_content: message,
        message_id: 'WAM-SIM-' + Date.now(),
        timestamp: new Date().toISOString().slice(0, 19).replace('T', ' ')
    };

    await dispatchWebhook(payload);
};

// 3. Trigger Automated Lead Inflow & Welcome Simulation
window.triggerAutomatedLeadGenSimulation = async function() {
    const phone = document.getElementById('sim_auto_phone').value.trim();
    const message = document.getElementById('sim_auto_inquiry').value.trim();

    if (!phone || !message) {
        showToast('Please enter both prospect phone number and inquiry message.', 'warning');
        return;
    }

    const payload = {
        event_type: 'incoming_message',
        sender_number: phone,
        recipient_number: '919876543210',
        message_content: message,
        message_id: 'WAM-LEAD-' + Date.now(),
        timestamp: new Date().toISOString().slice(0, 19).replace('T', ' ')
    };

    const payloadViewer = document.getElementById('sim_payload_view');
    payloadViewer.textContent = 'Simulating Inbound Lead Generation & Triggering Automated Welcome...\n' + JSON.stringify(payload, null, 2);

    try {
        const response = await fetch('api/webhook.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const res = await response.json();

        payloadViewer.textContent = 
            '=== 1. INCOMING PROSPECT LEAD WEBHOOK ===\n' + JSON.stringify(payload, null, 2) + 
            '\n\n=== 2. AUTOMATION & LEAD MAPPING RESPONSE ===\n' + JSON.stringify(res, null, 2) +
            '\n\nRESULT: New lead mapped, and automated qualification WhatsApp welcome message was dispatched!';

        if (res.success) {
            hasSimulatorChanges = true;
            showToast('Lead generated & automated welcome message dispatched!', 'success');
            await refreshSimulatorData();
        } else {
            showToast('Simulation Error: ' + (res.error || 'Failed'), 'error');
        }
    } catch (err) {
        payloadViewer.textContent += '\n\nERROR: ' + err.message;
        showToast('Dispatch failed: ' + err.message, 'error');
    }
};

// 4. Scheduled Messages Queue & Dispatcher Functions
window.checkScheduledQueue = async function() {
    try {
        const response = await fetch('api/scheduled_dispatch.php');
        const res = await response.json();
        if (res.success) {
            const countEl = document.getElementById('sim_scheduled_count');
            if (countEl) countEl.textContent = res.pending_count;

            const payloadViewer = document.getElementById('sim_payload_view');
            payloadViewer.textContent = 
                `=== SCHEDULED MESSAGES QUEUE STATUS ===\n` +
                `Server Time: ${res.server_time}\n` +
                `Pending Scheduled Messages: ${res.pending_count}\n\n` +
                JSON.stringify(res.scheduled_list, null, 2);
        }
    } catch (err) {
        console.error('Error checking scheduled queue:', err);
    }
};

window.runScheduledDispatcher = async function(forceAll = true) {
    const payloadViewer = document.getElementById('sim_payload_view');
    payloadViewer.textContent = 'Executing Scheduled Messages Dispatcher Worker (simulating minute cron)...';

    try {
        const response = await fetch('api/scheduled_dispatch.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ force_all: forceAll })
        });

        const res = await response.json();
        payloadViewer.textContent = 
            `=== SCHEDULED DISPATCHER WORKER RUN COMPLETE ===\n` +
            `Dispatched Count: ${res.dispatched_count}\n` +
            `Remaining Queue: ${res.remaining_count}\n\n` +
            JSON.stringify(res, null, 2);

        if (res.success) {
            hasSimulatorChanges = true;
            showToast(res.message, res.dispatched_count > 0 ? 'success' : 'info');
            await refreshSimulatorData();
        } else {
            showToast('Worker Error: ' + (res.error || 'Failed'), 'error');
        }
    } catch (err) {
        payloadViewer.textContent += '\n\nERROR: ' + err.message;
        showToast('Worker execution failed: ' + err.message, 'error');
    }
};

// Internal Webhook Dispatcher
async function dispatchWebhook(payload) {
    const payloadViewer = document.getElementById('sim_payload_view');
    payloadViewer.textContent = 'Dispatching HTTP POST to /api/webhook.php...\n' + JSON.stringify(payload, null, 2);

    try {
        const response = await fetch('api/webhook.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const res = await response.json();

        payloadViewer.textContent = 
            '=== OUTGOING WEBHOOK REQUEST ===\n' + JSON.stringify(payload, null, 2) + 
            '\n\n=== RESPONSE FROM /api/webhook.php (' + response.status + ') ===\n' + JSON.stringify(res, null, 2);

        if (res.success) {
            hasSimulatorChanges = true;
            showToast(`Webhook event processed successfully!`, 'success');
            await refreshSimulatorData();
        } else {
            showToast('Webhook Error: ' + (res.error || 'Failed'), 'error');
        }
    } catch (err) {
        payloadViewer.textContent += '\n\nERROR: ' + err.message;
        showToast('Webhook dispatch failed: ' + err.message, 'error');
    }
}

window.fetchRecentWebhookLogs = async function() {
    await refreshSimulatorData();
};

function renderWebhookLog(log) {
    const payloadViewer = document.getElementById('sim_payload_view');
    try {
        const parsed = JSON.parse(log.payload_json);
        payloadViewer.textContent = 
            `=== LATEST INGESTED WEBHOOK LOG (#${log.id}) ===\n` +
            `Event Type: ${log.event_type}\n` +
            `Timestamp: ${log.log_time}\n` +
            `Status: ${log.processed_status}\n\n` +
            JSON.stringify(parsed, null, 2);
    } catch (e) {
        payloadViewer.textContent = log.payload_json;
    }
}

