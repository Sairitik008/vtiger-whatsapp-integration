/**
 * CRM Pipeline Kanban Controller (Native Vanilla JS)
 * Handles Drag & Drop, Stage Transitions, Send WhatsApp Modal, and Live Sync
 */

document.addEventListener('DOMContentLoaded', () => {
    initDragAndDrop();
    initModals();
    initNewLeadForm();
    initSendWhatsAppForm();
    initViewSwitcher();
    initPipelineFilters();
});

// View Switcher (Kanban Board ⇄ Table / List View)
function initViewSwitcher() {
    const savedView = localStorage.getItem('crm_pipeline_view') || 'kanban';
    switchPipelineView(savedView, false);
}

window.switchPipelineView = function(view, persist = true) {
    const kanban = document.getElementById('kanbanContainer');
    const table = document.getElementById('tableContainer');
    const btnKanban = document.getElementById('btnViewKanban');
    const btnTable = document.getElementById('btnViewTable');

    if (view === 'table') {
        if (kanban) kanban.classList.remove('active');
        if (table) table.classList.add('active');
        if (btnKanban) btnKanban.classList.remove('active');
        if (btnTable) btnTable.classList.add('active');
    } else {
        if (table) table.classList.remove('active');
        if (kanban) kanban.classList.add('active');
        if (btnTable) btnTable.classList.remove('active');
        if (btnKanban) btnKanban.classList.add('active');
    }

    if (persist) {
        localStorage.setItem('crm_pipeline_view', view);
    }
};

// Real-time Search & Filter Pipeline
function initPipelineFilters() {
    const searchInput = document.getElementById('pipelineSearchInput');
    const stageSelect = document.getElementById('stageFilterSelect');
    const typeSelect  = document.getElementById('typeFilterSelect');

    if (searchInput) searchInput.addEventListener('input', filterPipelineData);
    if (stageSelect) stageSelect.addEventListener('change', filterPipelineData);
    if (typeSelect)  typeSelect.addEventListener('change', filterPipelineData);
}

function filterPipelineData() {
    const searchVal = (document.getElementById('pipelineSearchInput')?.value || '').toLowerCase().trim();
    const stageVal  = document.getElementById('stageFilterSelect')?.value || 'all';
    const typeVal   = (document.getElementById('typeFilterSelect')?.value || 'all').toLowerCase();

    // 1. Filter Kanban Cards
    document.querySelectorAll('.kanban-card').forEach(card => {
        const text  = (card.dataset.searchText || '').toLowerCase();
        const stage = card.dataset.stageId || '';
        const type  = (card.dataset.recordType || '').toLowerCase();

        const matchSearch = !searchVal || text.includes(searchVal);
        const matchStage  = stageVal === 'all' || stage === stageVal;
        const matchType   = typeVal === 'all' || type === typeVal;

        if (matchSearch && matchStage && matchType) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });

    // Update column counters based on visible cards
    document.querySelectorAll('.kanban-column').forEach(col => {
        const visibleCount = col.querySelectorAll('.kanban-card:not([style*="display: none"])').length;
        const countBadge = col.querySelector('.column-count');
        if (countBadge) countBadge.textContent = visibleCount;
    });

    // 2. Filter Table Rows
    document.querySelectorAll('.table-lead-row').forEach(row => {
        const text  = (row.dataset.searchText || '').toLowerCase();
        const stage = row.dataset.stageId || '';
        const type  = (row.dataset.recordType || '').toLowerCase();

        const matchSearch = !searchVal || text.includes(searchVal);
        const matchStage  = stageVal === 'all' || stage === stageVal;
        const matchType   = typeVal === 'all' || type === typeVal;

        if (matchSearch && matchStage && matchType) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Stage change directly from Table dropdown
window.changeLeadStageFromTable = async function(leadId, newStageId) {
    try {
        const response = await fetch('api/update_stage.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ lead_id: parseInt(leadId, 10), stage_id: parseInt(newStageId, 10) })
        });
        const res = await response.json();
        if (!res.success) {
            throw new Error(res.error || 'Failed to update stage');
        }

        // Update row data attribute
        const row = document.querySelector(`.table-lead-row[data-lead-id="${leadId}"]`);
        if (row) row.dataset.stageId = newStageId;

        // Move card in Kanban board to match
        const card = document.querySelector(`.kanban-card[data-lead-id="${leadId}"]`);
        const targetColumn = document.querySelector(`.kanban-column[data-stage-id="${newStageId}"] .column-cards`);
        if (card && targetColumn) {
            card.dataset.stageId = newStageId;
            targetColumn.appendChild(card);
            updateColumnCounts();
        }

        showToast(`Lead updated to: ${res.stage_name}`, 'success');
    } catch (err) {
        showToast('Error: ' + err.message, 'error');
    }
};

// Drag & Drop State
let draggedCard = null;

function initDragAndDrop() {
    const cards = document.querySelectorAll('.kanban-card');
    const columns = document.querySelectorAll('.column-cards');

    cards.forEach(card => {
        card.setAttribute('draggable', 'true');
        card.addEventListener('dragstart', handleDragStart);
        card.addEventListener('dragend', handleDragEnd);
    });

    columns.forEach(col => {
        col.addEventListener('dragover', handleDragOver);
        col.addEventListener('dragenter', handleDragEnter);
        col.addEventListener('dragleave', handleDragLeave);
        col.addEventListener('drop', handleDrop);
    });
}

function handleDragStart(e) {
    draggedCard = this;
    this.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', this.dataset.leadId);
}

function handleDragEnd() {
    this.classList.remove('dragging');
    document.querySelectorAll('.kanban-column').forEach(c => c.classList.remove('drag-over'));
    draggedCard = null;
}

function handleDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
}

function handleDragEnter(e) {
    e.preventDefault();
    this.closest('.kanban-column').classList.add('drag-over');
}

function handleDragLeave() {
    this.closest('.kanban-column').classList.remove('drag-over');
}

async function handleDrop(e) {
    e.preventDefault();
    const column = this.closest('.kanban-column');
    column.classList.remove('drag-over');

    if (!draggedCard) return;

    const leadId = parseInt(draggedCard.dataset.leadId, 10);
    const targetStageId = parseInt(column.dataset.stageId, 10);
    const originalColumn = draggedCard.closest('.column-cards');

    // Optimistic UI updates
    draggedCard.dataset.stageId = targetStageId;
    this.appendChild(draggedCard);
    updateColumnCounts();

    // Two-way synchronization with Table View row and stage dropdown
    const tableRow = document.querySelector(`.table-lead-row[data-lead-id="${leadId}"]`);
    if (tableRow) {
        tableRow.dataset.stageId = targetStageId;
        const select = tableRow.querySelector('.table-stage-select');
        if (select) select.value = targetStageId;
    }

    try {
        const response = await fetch('api/update_stage.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ lead_id: leadId, stage_id: targetStageId })
        });
        const res = await response.json();
        if (!res.success) {
            throw new Error(res.error || 'Failed to update stage');
        }
        showToast(`Lead updated to: ${res.stage_name}`, 'success');
    } catch (err) {
        // Rollback on error
        originalColumn.appendChild(draggedCard);
        updateColumnCounts();
        showToast('Error updating stage: ' + err.message, 'error');
    }
}

function updateColumnCounts() {
    document.querySelectorAll('.kanban-column').forEach(col => {
        const cardsContainer = col.querySelector('.column-cards');
        const cards = col.querySelectorAll('.kanban-card');
        const badge = col.querySelector('.column-count');
        if (badge) badge.textContent = cards.length;

        // Ensure clean placeholder state
        let placeholder = cardsContainer ? cardsContainer.querySelector('.column-empty-placeholder') : null;
        if (cards.length === 0) {
            if (!placeholder && cardsContainer) {
                placeholder = document.createElement('div');
                placeholder.className = 'column-empty-placeholder';
                placeholder.innerHTML = `
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.6">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                        <line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>
                    </svg>
                    <span>No leads in stage</span>
                    <small>Drop cards here to update</small>
                `;
                cardsContainer.appendChild(placeholder);
            } else if (placeholder) {
                placeholder.style.display = 'flex';
            }
        } else if (placeholder) {
            placeholder.style.display = 'none';
        }
    });
}

// Modal Management
function initModals() {
    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('.modal-overlay');
            if (modal) modal.classList.remove('active');
        });
    });

    // Close on backdrop click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) overlay.classList.remove('active');
        });
    });

    // Close on Escape key press
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
        }
    });
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.classList.add('active');
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.classList.remove('active');
}

// Send Mode Management (Immediate vs Schedule)
let currentSendMode = 'immediate';

window.setSendMode = function(mode) {
    currentSendMode = mode;
    const btnImmediate = document.getElementById('btnModeImmediate');
    const btnSchedule = document.getElementById('btnModeSchedule');
    const scheduleBox = document.getElementById('scheduleDetailsBox');
    const submitLabel = document.getElementById('send_submit_label');
    const btnWaWeb = document.getElementById('btnSendWaWeb');

    if (mode === 'schedule') {
        if (btnImmediate) btnImmediate.classList.remove('active');
        if (btnSchedule) btnSchedule.classList.add('active');
        if (scheduleBox) scheduleBox.style.display = 'block';
        if (submitLabel) submitLabel.textContent = 'Schedule Message';
        if (btnWaWeb) btnWaWeb.style.display = 'none';

        // Set default to 2 hours from now if empty
        const schedInput = document.getElementById('send_scheduled_at');
        if (schedInput && !schedInput.value) {
            setSchedulePreset(2);
        }
    } else {
        if (btnSchedule) btnSchedule.classList.remove('active');
        if (btnImmediate) btnImmediate.classList.add('active');
        if (scheduleBox) scheduleBox.style.display = 'none';
        if (submitLabel) submitLabel.textContent = 'Send & Track in CRM';
        if (btnWaWeb) btnWaWeb.style.display = 'inline-flex';
    }
};

window.setSchedulePreset = function(hours) {
    const d = new Date();
    d.setHours(d.getHours() + hours);
    d.setMinutes(Math.round(d.getMinutes() / 15) * 15);
    const localIso = new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    const input = document.getElementById('send_scheduled_at');
    if (input) input.value = localIso;
};

window.setSchedulePresetTomorrow = function(hour) {
    const d = new Date();
    d.setDate(d.getDate() + 1);
    d.setHours(hour, 0, 0, 0);
    const localIso = new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    const input = document.getElementById('send_scheduled_at');
    if (input) input.value = localIso;
};

// Open Quick Send WhatsApp Modal
window.openSendWhatsAppModal = function(leadId, leadName, leadPhone) {
    document.getElementById('send_lead_id').value = leadId;
    document.getElementById('send_lead_name_display').textContent = leadName;
    document.getElementById('send_lead_phone').value = leadPhone;
    document.getElementById('send_message_text').value = '';
    setSendMode('immediate');
    openModal('sendWhatsAppModal');
    setTimeout(() => {
        const textarea = document.getElementById('send_message_text');
        if (textarea) textarea.focus();
    }, 150);
};

// Quick Pre-formatted Message Templates
window.applyMessageTemplate = function(type) {
    const textarea = document.getElementById('send_message_text');
    const leadName = document.getElementById('send_lead_name_display')?.textContent || 'Customer';
    const firstName = leadName.split(' ')[0] || 'there';

    const templates = {
        followup: `Hello ${firstName}, thank you for connecting with NB Entrepreneurs. We would love to understand your requirements and share tailored solutions. When is a convenient time for a quick conversation?`,
        quote: `Hello ${firstName}, following up on your request regarding pricing and product specifications. Please let me know if you would like me to share our complete enterprise brochure.`,
        meeting: `Hello ${firstName}, hope you are doing well. I would like to schedule a brief 10-minute discovery call to discuss your business needs. Please let me know what day and time works best for you.`
    };

    if (textarea && templates[type]) {
        textarea.value = templates[type];
        textarea.focus();
    }
};

// One-Click Automated Lead Generation Outreach
window.triggerAutoLeadOutreach = async function(leadId, leadName) {
    if (!confirm(`Trigger automated WhatsApp welcome & qualification message for ${leadName}?`)) {
        return;
    }

    try {
        showToast(`Sending automated outreach to ${leadName}...`, 'info');
        const response = await fetch('api/send.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                lead_id: leadId,
                auto_welcome: true
            })
        });

        const res = await response.json();
        if (!res.success) {
            throw new Error(res.error || 'Failed to trigger automated outreach');
        }

        showToast(`Automated welcome message sent to ${leadName}!`, 'success');
        setTimeout(() => window.location.reload(), 600);
    } catch (err) {
        showToast('Error: ' + err.message, 'error');
    }
};

// Handle Outgoing Message Submit (Supports Immediate & Scheduled)
function initSendWhatsAppForm() {
    const form = document.getElementById('sendWhatsAppForm');
    if (!form) return;

    // Ctrl + Enter shortcut to submit message
    const textarea = document.getElementById('send_message_text');
    if (textarea) {
        textarea.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                form.requestSubmit();
            }
        });
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        const origText = submitBtn.innerHTML;
        submitBtn.textContent = currentSendMode === 'schedule' ? 'Scheduling...' : 'Sending...';

        const leadId = parseInt(document.getElementById('send_lead_id').value, 10);
        const phone = document.getElementById('send_lead_phone').value;
        const message = document.getElementById('send_message_text').value;
        const simulateFailure = document.getElementById('send_simulate_failure').checked;
        
        let scheduledAt = null;
        if (currentSendMode === 'schedule') {
            const rawSched = document.getElementById('send_scheduled_at').value;
            if (!rawSched) {
                showToast('Please select a scheduled date and time.', 'warning');
                submitBtn.disabled = false;
                submitBtn.innerHTML = origText;
                return;
            }
            scheduledAt = rawSched.replace('T', ' ') + ':00';
        }

        try {
            const response = await fetch('api/send.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    lead_id: leadId,
                    phone: phone,
                    message: message,
                    simulate_failure: simulateFailure,
                    scheduled_at: scheduledAt
                })
            });

            const res = await response.json();
            if (!res.success) {
                throw new Error(res.error || 'Failed to send/schedule message');
            }

            closeModal('sendWhatsAppModal');
            if (currentSendMode === 'schedule') {
                showToast(`Message scheduled for ${res.data.scheduled_at || scheduledAt}!`, 'success');
            } else {
                showToast(simulateFailure ? 'Message simulated as FAILED.' : 'WhatsApp message sent successfully!', simulateFailure ? 'warning' : 'success');
            }

            // Refresh board to display the updated message and status
            setTimeout(() => window.location.reload(), 600);
        } catch (err) {
            showToast('Send Error: ' + err.message, 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = origText;
        }
    });
}

// Send message, log in CRM, and open WhatsApp Web with pre-filled text
window.sendAndLaunchWhatsAppWeb = async function() {
    const leadId = parseInt(document.getElementById('send_lead_id').value, 10);
    const phone = document.getElementById('send_lead_phone').value;
    const message = document.getElementById('send_message_text').value.trim();

    if (!message) {
        showToast('Please enter a message first.', 'warning');
        return;
    }

    try {
        // 1. Log outgoing message in CRM database and update pipeline stage
        const response = await fetch('api/send.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                lead_id: leadId,
                phone: phone,
                message: message,
                simulate_failure: false
            })
        });

        const res = await response.json();
        if (!res.success) {
            throw new Error(res.error || 'Failed to record message in CRM');
        }

        const cleanPhone = (res.data && res.data.recipient) ? res.data.recipient : phone.replace(/[^0-9]/g, '');

        closeModal('sendWhatsAppModal');
        showToast('Recorded in CRM! Opening WhatsApp Web...', 'success');

        // 2. Launch official WhatsApp Web/App
        const waUrl = `https://wa.me/${cleanPhone}?text=${encodeURIComponent(message)}`;
        window.open(waUrl, '_blank');

        setTimeout(() => window.location.reload(), 800);
    } catch (err) {
        showToast('Error: ' + err.message, 'error');
    }
};

// Handle New Lead Creation Form (With Auto-Welcome Outreach)
function initNewLeadForm() {
    const form = document.getElementById('newLeadForm');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;

        const autoWelcome = document.getElementById('lead_auto_welcome') ? document.getElementById('lead_auto_welcome').checked : true;

        const data = {
            first_name: document.getElementById('lead_first_name').value,
            last_name: document.getElementById('lead_last_name').value,
            email: document.getElementById('lead_email').value,
            phone: document.getElementById('lead_phone').value,
            company: document.getElementById('lead_company').value,
            record_type: document.getElementById('lead_type').value,
            pipeline_stage_id: parseInt(document.getElementById('lead_stage_select').value, 10),
            next_action: document.getElementById('lead_next_action').value,
            auto_welcome: autoWelcome
        };

        try {
            const response = await fetch('api/leads.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const res = await response.json();
            if (!res.success) throw new Error(res.error);

            closeModal('newLeadModal');
            if (res.auto_welcome_sent) {
                showToast('Lead created & automated welcome message dispatched!', 'success');
            } else {
                showToast('New record added to pipeline!', 'success');
            }
            setTimeout(() => window.location.reload(), 600);
        } catch (err) {
            showToast('Creation failed: ' + err.message, 'error');
        } finally {
            submitBtn.disabled = false;
        }
    });
}

// Modern Toast Notification Component
let toastTimer = null;
function showToast(message, type = 'info') {
    let toast = document.getElementById('crmToast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'crmToast';
        toast.className = 'crm-toast';
        document.body.appendChild(toast);
    }

    if (toastTimer) {
        clearTimeout(toastTimer);
    }

    toast.className = `crm-toast crm-toast-${type}`;
    toast.textContent = message;

    // Trigger reflow to restart CSS animation smoothly
    toast.classList.remove('show');
    void toast.offsetWidth;
    toast.classList.add('show');

    toastTimer = setTimeout(() => {
        toast.classList.remove('show');
    }, 3200);
}
