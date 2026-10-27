<?php
/**
 * Main CRM Pipeline & WhatsApp Dashboard
 * Vtiger Open Source WhatsApp Integration
 */

require_once __DIR__ . '/models/Lead.php';
require_once __DIR__ . '/models/Message.php';

$leadModel = new Lead();
$pipeline  = $leadModel->getPipelineBoard();

// Calculate total statistics
$totalLeads = 0;
foreach ($pipeline as $st) {
    $totalLeads += count($st['leads']);
}

$pdo = Database::getConnection();
$totalMessages = (int)$pdo->query("SELECT COUNT(*) FROM whatsapp_messages")->fetchColumn();
$deliveredCount = (int)$pdo->query("SELECT COUNT(*) FROM whatsapp_messages WHERE status IN ('delivered', 'read')")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vtiger Open Source WhatsApp Integration & CRM Pipeline</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/chat.css">
    <link rel="stylesheet" href="assets/css/simulator.css">
</head>
<body>

    <!-- Header Navigation -->
    <header class="app-header">
        <div class="brand-section">
            <div class="logo-badge">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                </svg>
                <span>vtiger CRM</span>
            </div>
            <div class="integration-badge">
                <span class="status-dot"></span>
                <span>Open-Source WhatsApp Gateway: <strong>Active</strong></span>
            </div>
        </div>

        <div class="header-actions">
            <a href="http://localhost:3333" target="_blank" class="btn btn-whatsapp" style="text-decoration: none;" title="Open Real WhatsApp Web Multi-Device Bridge & QR Scanner">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                </svg>
                <span>Real WhatsApp Bridge (QR)</span>
            </a>
            <button class="btn btn-simulator" onclick="openSimulatorModal()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><path d="M10 8l6 4-6 4V8z"/>
                </svg>
                <span>Webhook & Gateway Simulator</span>
            </button>
            <button class="btn btn-primary" onclick="openModal('newLeadModal')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 5v14M5 12h14"/>
                </svg>
                <span>Add Lead / Contact</span>
            </button>
            <button class="btn btn-secondary" onclick="window.location.reload()" title="Refresh Board">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/>
                </svg>
            </button>
        </div>
    </header>

    <!-- Subheader & Metric Counters -->
    <section class="pipeline-banner">
        <div class="banner-title">
            <h1>Lead & Contact Journey Pipeline</h1>
            <p>End-to-End WhatsApp Communication, Real-Time Webhook Mapping & Status Tracking</p>
        </div>
        <div class="pipeline-stats">
            <div class="stat-pill">Total Pipeline Leads: <strong><?= $totalLeads ?></strong></div>
            <div class="stat-pill">WhatsApp Messages: <strong><?= $totalMessages ?></strong></div>
            <div class="stat-pill">Delivery Rate: <strong><?= $totalMessages > 0 ? round(($deliveredCount / $totalMessages) * 100) : 100 ?>%</strong></div>
        </div>
    </section>

    <!-- Pipeline Controls: Search, Stage Filter & View Switcher -->
    <div class="pipeline-controls-bar">
        <div class="controls-left">
            <div class="search-box-wrap">
                <svg class="search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="text" id="pipelineSearchInput" placeholder="Search by name, company, or phone...">
            </div>
            <select id="stageFilterSelect" class="filter-select">
                <option value="all">All Stages</option>
                <?php foreach ($pipeline as $st): ?>
                    <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['stage_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="typeFilterSelect" class="filter-select">
                <option value="all">All Types</option>
                <option value="Lead">Leads</option>
                <option value="Contact">Contacts</option>
            </select>
        </div>

        <div class="controls-right">
            <div class="view-switcher-group">
                <button type="button" class="view-btn active" id="btnViewKanban" onclick="switchPipelineView('kanban')">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="18"/><rect x="14" y="3" width="7" height="11"/>
                    </svg>
                    <span>Board View</span>
                </button>
                <button type="button" class="view-btn" id="btnViewTable" onclick="switchPipelineView('table')">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
                        <line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
                    </svg>
                    <span>Table View</span>
                </button>
            </div>
        </div>
    </div>

    <!-- View 1: Main Kanban Container -->
    <main class="kanban-container active" id="kanbanContainer">
        <div class="kanban-board">
            <?php foreach ($pipeline as $stage): ?>
                <div class="kanban-column" data-stage-id="<?= $stage['id'] ?>">
                    <div class="column-header" style="border-bottom-color: <?= htmlspecialchars($stage['color_code']) ?>;">
                        <div class="column-title-wrap">
                            <span class="column-color-indicator" style="background-color: <?= htmlspecialchars($stage['color_code']) ?>;"></span>
                            <span class="column-title"><?= htmlspecialchars($stage['stage_name']) ?></span>
                        </div>
                        <span class="column-count"><?= count($stage['leads']) ?></span>
                    </div>

                    <div class="column-cards">
                        <?php 
                        $avatarPalette = ['#2563eb', '#059669', '#7c3aed', '#d97706', '#db2777', '#0891b2', '#4f46e5'];
                        if (empty($stage['leads'])): 
                        ?>
                            <div class="column-empty-placeholder">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.6">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                    <line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>
                                </svg>
                                <span>No leads in stage</span>
                                <small>Drop cards here to update</small>
                            </div>
                        <?php 
                        endif;
                        foreach ($stage['leads'] as $lead): 
                            $initials = strtoupper(substr($lead['first_name'], 0, 1) . substr($lead['last_name'], 0, 1));
                            $avatarBg = $avatarPalette[$lead['id'] % count($avatarPalette)];
                            $status = strtolower($lead['last_message_status'] ?? 'sent');
                            
                            $ticksHtml = '';
                            if ($status === 'sent') {
                                $ticksHtml = '<span class="status-tick">✓</span>';
                            } elseif ($status === 'delivered') {
                                $ticksHtml = '<span class="status-tick">✓✓</span>';
                            } elseif ($status === 'read') {
                                $ticksHtml = '<span class="status-tick tick-read">✓✓</span>';
                            } elseif ($status === 'failed') {
                                $ticksHtml = '<span class="status-tick tick-failed">!</span>';
                            } elseif ($status === 'scheduled') {
                                $ticksHtml = '<span class="status-tick tick-scheduled"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align: -1px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>';
                            }
                        ?>
                            <div class="kanban-card" 
                                data-lead-id="<?= $lead['id'] ?>"
                                data-stage-id="<?= $stage['id'] ?>"
                                data-record-type="<?= strtolower($lead['record_type']) ?>"
                                data-search-text="<?= strtolower(htmlspecialchars($lead['first_name'] . ' ' . $lead['last_name'] . ' ' . $lead['company'] . ' ' . $lead['phone'])) ?>">

                                <!-- Card Header: Avatar, Name, Company, Record Type -->
                                <div class="card-top-row">
                                    <div class="card-lead-identity">
                                        <div class="lead-avatar" style="background-color: <?= $avatarBg ?>;">
                                            <?= htmlspecialchars($initials) ?>
                                        </div>
                                        <div class="lead-meta">
                                            <h4 class="lead-name" title="<?= htmlspecialchars($lead['first_name'] . ' ' . $lead['last_name']) ?>">
                                                <?= htmlspecialchars($lead['first_name'] . ' ' . $lead['last_name']) ?>
                                            </h4>
                                            <div class="lead-company" title="<?= htmlspecialchars($lead['company'] ?: 'Individual Account') ?>">
                                                <?= htmlspecialchars($lead['company'] ?: 'Individual Account') ?>
                                            </div>
                                        </div>
                                    </div>
                                    <span class="type-pill <?= strtolower($lead['record_type']) ?>">
                                        <?= htmlspecialchars($lead['record_type']) ?>
                                    </span>
                                </div>

                                <!-- Contact Info: Phone Dialer Link + Direct WhatsApp Chip -->
                                <div class="card-contact-strip">
                                    <div class="contact-number">
                                        <a href="tel:+<?= $lead['normalized_phone'] ?: preg_replace('/[^0-9]/', '', $lead['phone']) ?>" 
                                           class="phone-dialer-link" 
                                           title="Click to call <?= htmlspecialchars($lead['phone']) ?>">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                            </svg>
                                            <span><?= htmlspecialchars($lead['phone']) ?></span>
                                        </a>
                                    </div>
                                    <a href="https://wa.me/<?= $lead['normalized_phone'] ?>" target="_blank" class="wa-direct-chip" title="Direct chat in WhatsApp Web">
                                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                                        </svg>
                                        <span>WhatsApp</span>
                                    </a>
                                </div>

                                <!-- WhatsApp Message Interaction (Compact & Sleek) -->
                                <?php if (!empty($lead['last_message_text'])): ?>
                                    <div class="card-message-compact">
                                        <div class="msg-compact-text" title="<?= htmlspecialchars($lead['last_message_text']) ?>">
                                            "<?= htmlspecialchars($lead['last_message_text']) ?>"
                                        </div>
                                        <div class="msg-compact-meta">
                                            <span class="status-badge <?= $status ?>">
                                                <?= $ticksHtml ?> <?= ucfirst($status) ?>
                                            </span>
                                            <span class="msg-compact-time">
                                                <?= $lead['last_contacted_at'] ? date('d M, h:i A', strtotime($lead['last_contacted_at'])) : '' ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Next Action Pill -->
                                <?php if (!empty($lead['next_action'])): ?>
                                    <div class="card-next-step" title="<?= htmlspecialchars($lead['next_action']) ?>">
                                        <span class="step-label">Next:</span>
                                        <span class="step-text"><?= htmlspecialchars($lead['next_action']) ?></span>
                                    </div>
                                <?php endif; ?>

                                <!-- Stage 1 Welcome Outreach Action -->
                                <?php if ((int)$lead['pipeline_stage_id'] === 1): ?>
                                    <button type="button" class="btn btn-card-welcome" onclick="triggerAutoLeadOutreach(<?= $lead['id'] ?>, '<?= htmlspecialchars(addslashes($lead['first_name'])) ?>')" title="Send automated welcome outreach message">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="22" y1="2" x2="11" y2="13"/>
                                            <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                                        </svg>
                                        <span>Send Welcome Outreach</span>
                                    </button>
                                <?php endif; ?>

                                <!-- Action Buttons: 2-Column Grid Across All Columns -->
                                <div class="card-actions-grid">
                                    <button type="button" class="btn btn-card-chat" onclick="openChatModal(<?= $lead['id'] ?>, '<?= htmlspecialchars(addslashes($lead['first_name'] . ' ' . $lead['last_name'])) ?>', '<?= htmlspecialchars($lead['phone']) ?>')" title="View conversation thread">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                        </svg>
                                        <span>Chat</span>
                                    </button>
                                    <button type="button" class="btn btn-card-send" onclick="openSendWhatsAppModal(<?= $lead['id'] ?>, '<?= htmlspecialchars(addslashes($lead['first_name'] . ' ' . $lead['last_name'])) ?>', '<?= htmlspecialchars($lead['phone']) ?>')" title="Send new WhatsApp message">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="22" y1="2" x2="11" y2="13"/>
                                            <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                                        </svg>
                                        <span>Send Msg</span>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <!-- View 2: Modern Table / List Pipeline View -->
    <section class="table-pipeline-container" id="tableContainer">
        <table class="crm-data-table" id="pipelineDataTable">
            <thead>
                <tr>
                    <th style="width: 250px;">Lead / Contact</th>
                    <th style="width: 150px;">Phone Number</th>
                    <th style="width: 180px;">Pipeline Stage</th>
                    <th>Latest WhatsApp Message</th>
                    <th style="width: 130px;">Status</th>
                    <th style="width: 180px;">Next Action</th>
                    <th style="width: 170px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                foreach ($pipeline as $st):
                    foreach ($st['leads'] as $lead):
                        $initials = strtoupper(substr($lead['first_name'], 0, 1) . substr($lead['last_name'], 0, 1));
                        $avatarBg = $avatarPalette[$lead['id'] % count($avatarPalette)];
                        $status = strtolower($lead['last_message_status'] ?? 'sent');
                ?>
                <tr class="table-lead-row" 
                    data-lead-id="<?= $lead['id'] ?>"
                    data-stage-id="<?= $lead['pipeline_stage_id'] ?>"
                    data-record-type="<?= strtolower($lead['record_type']) ?>"
                    data-search-text="<?= strtolower(htmlspecialchars($lead['first_name'] . ' ' . $lead['last_name'] . ' ' . $lead['company'] . ' ' . $lead['phone'])) ?>">
                    <td>
                        <div class="card-identity">
                            <div class="lead-avatar" style="background-color: <?= $avatarBg ?>;">
                                <?= htmlspecialchars($initials) ?>
                            </div>
                            <div class="lead-meta">
                                <div class="lead-name"><?= htmlspecialchars($lead['first_name'] . ' ' . $lead['last_name']) ?></div>
                                <div class="lead-company"><?= htmlspecialchars($lead['company'] ?: 'Individual Account') ?></div>
                            </div>
                            <span class="type-pill <?= strtolower($lead['record_type']) ?>" style="margin-left: 6px;">
                                <?= htmlspecialchars($lead['record_type']) ?>
                            </span>
                        </div>
                    </td>
                    <td>
                        <a href="tel:+<?= $lead['normalized_phone'] ?: preg_replace('/[^0-9]/', '', $lead['phone']) ?>" 
                           class="table-phone-link" 
                           title="Click to call <?= htmlspecialchars($lead['phone']) ?>">
                            <?= htmlspecialchars($lead['phone']) ?>
                        </a>
                    </td>
                    <td>
                        <select class="table-stage-select" onchange="changeLeadStageFromTable(<?= $lead['id'] ?>, this.value)">
                            <?php foreach ($pipeline as $optStage): ?>
                                <option value="<?= $optStage['id'] ?>" <?= $optStage['id'] == $lead['pipeline_stage_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($optStage['stage_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <?php if (!empty($lead['last_message_text'])): ?>
                            <div class="table-msg-preview">
                                <div class="table-msg-text">"<?= htmlspecialchars($lead['last_message_text']) ?>"</div>
                                <span class="table-msg-time">
                                    <?= $lead['last_contacted_at'] ? date('d M, h:i A', strtotime($lead['last_contacted_at'])) : 'Recent' ?>
                                </span>
                            </div>
                        <?php else: ?>
                            <span style="font-size: 11px; color: #94a3b8; font-style: italic;">No messages sent yet</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($lead['last_message_status'])): ?>
                            <?php
                                $ticksHtml = '';
                                if ($status === 'sent') {
                                    $ticksHtml = '<span class="status-tick">✓</span>';
                                } elseif ($status === 'delivered') {
                                    $ticksHtml = '<span class="status-tick">✓✓</span>';
                                } elseif ($status === 'read') {
                                    $ticksHtml = '<span class="status-tick tick-read">✓✓</span>';
                                } elseif ($status === 'failed') {
                                    $ticksHtml = '<span class="status-tick tick-failed">!</span>';
                                } elseif ($status === 'scheduled') {
                                    $ticksHtml = '<span class="status-tick tick-scheduled"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align: -1px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>';
                                }
                            ?>
                            <span class="status-badge <?= $status ?>">
                                <?= $ticksHtml ?> <?= ucfirst($status) ?>
                            </span>
                        <?php else: ?>
                            <span style="color: #cbd5e1;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span style="font-size: 11.5px; color: #475569;">
                            <?= htmlspecialchars($lead['next_action'] ?: 'Follow up') ?>
                        </span>
                    </td>
                    <td style="text-align: right;">
                        <div class="table-btn-group" style="justify-content: flex-end;">
                            <?php if ((int)$lead['pipeline_stage_id'] === 1): ?>
                                <button class="btn btn-card-welcome" style="width: auto; padding: 4px 9px;" onclick="triggerAutoLeadOutreach(<?= $lead['id'] ?>, '<?= htmlspecialchars(addslashes($lead['first_name'])) ?>')" title="Send automated welcome outreach message">
                                    Welcome
                                </button>
                            <?php endif; ?>
                            <button class="btn btn-card-secondary" onclick="openChatModal(<?= $lead['id'] ?>, '<?= htmlspecialchars(addslashes($lead['first_name'] . ' ' . $lead['last_name'])) ?>', '<?= htmlspecialchars($lead['phone']) ?>')">
                                Chat
                            </button>
                            <button class="btn btn-card-primary" onclick="openSendWhatsAppModal(<?= $lead['id'] ?>, '<?= htmlspecialchars(addslashes($lead['first_name'] . ' ' . $lead['last_name'])) ?>', '<?= htmlspecialchars($lead['phone']) ?>')">
                                Send
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endforeach; ?>
            </tbody>
        </table>
    </section>

    <!-- Modal: Send WhatsApp Message -->
    <div class="modal-overlay" id="sendWhatsAppModal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 class="modal-title">Send WhatsApp Message</h3>
                <button class="modal-close" data-close-modal>&times;</button>
            </div>
            <form id="sendWhatsAppForm">
                <div class="modal-body">
                    <input type="hidden" id="send_lead_id" name="lead_id">
                    <div class="form-group">
                        <label>Recipient:</label>
                        <div style="font-weight: 700; color: #0f172a; margin-bottom: 4px;" id="send_lead_name_display"></div>
                        <input type="text" id="send_lead_phone" class="form-control" readonly style="background: #f1f5f9;">
                    </div>

                    <!-- Dispatch Mode Selector: Send Immediately vs Schedule -->
                    <div class="send-mode-toggle">
                        <button type="button" class="send-mode-btn active" id="btnModeImmediate" onclick="setSendMode('immediate')">Send Immediately</button>
                        <button type="button" class="send-mode-btn" id="btnModeSchedule" onclick="setSendMode('schedule')">Schedule for Later</button>
                    </div>

                    <!-- Scheduled DateTime Picker (Visible when Schedule Mode is active) -->
                    <div id="scheduleDetailsBox" class="schedule-details-box" style="display: none;">
                        <label style="font-size: 11.5px; font-weight: 700; color: #6b21a8; margin-bottom: 4px; display: block;">Select Scheduled Delivery Time:*</label>
                        <input type="datetime-local" id="send_scheduled_at" class="form-control" style="background: #ffffff; border-color: #d8b4fe;">
                        <div class="schedule-presets">
                            <button type="button" class="preset-btn" onclick="setSchedulePreset(1)">+1 Hour</button>
                            <button type="button" class="preset-btn" onclick="setSchedulePreset(3)">+3 Hours</button>
                            <button type="button" class="preset-btn" onclick="setSchedulePresetTomorrow(10)">Tomorrow 10 AM</button>
                            <button type="button" class="preset-btn" onclick="setSchedulePresetTomorrow(15)">Tomorrow 3 PM</button>
                        </div>
                    </div>

                    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 10px 12px; margin-bottom: 12px; font-size: 11.5px; color: #166534; line-height: 1.4;">
                        <strong>Open-Source Gateway Notice:</strong>
                        Per assignment rules, paid APIs (Twilio/Meta WABA) are prohibited. Messages are tracked locally in the CRM pipeline. To also send directly to the recipient's live phone, click <strong>"Send via WhatsApp Web"</strong> below.
                    </div>
                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <label style="margin: 0;">Message Content:*</label>
                            <span style="font-size: 11px; color: #94a3b8;">Ctrl + Enter to send</span>
                        </div>
                        <div class="template-chips-row">
                            <span class="template-label">Templates:</span>
                            <button type="button" class="btn-template-chip" onclick="applyMessageTemplate('followup')">Inquiry Follow-up</button>
                            <button type="button" class="btn-template-chip" onclick="applyMessageTemplate('quote')">Pricing & Catalog</button>
                            <button type="button" class="btn-template-chip" onclick="applyMessageTemplate('meeting')">Schedule Call</button>
                        </div>
                        <textarea id="send_message_text" class="form-control" rows="3" placeholder="Type your WhatsApp message here..." required></textarea>
                    </div>
                    <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" id="send_simulate_failure" name="simulate_failure">
                        <label for="send_simulate_failure" style="margin: 0; font-size: 12px; color: #dc2626; cursor: pointer;">
                            Simulate delivery failure (tests unreachable recipient handling)
                        </label>
                    </div>
                </div>
                <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                    <div style="display: flex; gap: 8px;">
                        <button type="submit" class="btn btn-primary" id="btnSendSimulated">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                            </svg>
                            <span id="send_submit_label">Send & Track in CRM</span>
                        </button>
                        <button type="button" class="btn btn-whatsapp" id="btnSendWaWeb" onclick="sendAndLaunchWhatsAppWeb()" title="Log message in CRM and open WhatsApp Web with pre-filled text">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                            </svg>
                            <span>Send via WhatsApp Web</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal 2: Add New Lead / Contact -->
    <div class="modal-overlay" id="newLeadModal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 class="modal-title">Add Lead or Contact</h3>
                <button class="modal-close" data-close-modal>&times;</button>
            </div>
            <form id="newLeadForm">
                <div class="modal-body">
                    <div style="display: flex; gap: 12px;">
                        <div class="form-group" style="flex: 1;">
                            <label>First Name:*</label>
                            <input type="text" id="lead_first_name" class="form-control" required>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>Last Name:*</label>
                            <input type="text" id="lead_last_name" class="form-control" required>
                        </div>
                    </div>
                    <div style="display: flex; gap: 12px;">
                        <div class="form-group" style="flex: 1;">
                            <label>WhatsApp / Mobile Number:*</label>
                            <input type="text" id="lead_phone" class="form-control" placeholder="+91 98765 43210" required>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>Record Type:</label>
                            <select id="lead_type" class="form-control">
                                <option value="Lead">Lead</option>
                                <option value="Contact">Contact</option>
                            </select>
                        </div>
                    </div>
                    <div style="display: flex; gap: 12px;">
                        <div class="form-group" style="flex: 1;">
                            <label>Company / Organization:</label>
                            <input type="text" id="lead_company" class="form-control">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>Email Address:</label>
                            <input type="email" id="lead_email" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Initial Pipeline Stage:</label>
                        <select id="lead_stage_select" class="form-control">
                            <?php foreach ($pipeline as $st): ?>
                                <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['stage_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Next Action Plan:</label>
                        <input type="text" id="lead_next_action" class="form-control" value="Initiate WhatsApp outreach">
                    </div>

                    <!-- Automated Lead Outreach Option -->
                    <div class="form-group" style="margin-bottom: 6px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: #86198f; font-weight: 700; margin: 0;">
                            <input type="checkbox" id="lead_auto_welcome" checked style="width: 16px; height: 16px; accent-color: #a21caf;">
                            <span>Automate Lead Generation: Send Instant Welcome & Qualification Message</span>
                        </label>
                        <div class="auto-welcome-box">
                            <strong>Automated Outreach Message Preview:</strong><br>
                            <em>"Hello [First Name], thank you for connecting with NB Entrepreneurs. We have received your inquiry. Our product specialist will be in touch shortly..."</em>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                    <button type="submit" class="btn btn-primary">Save to Pipeline</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: WhatsApp Conversation History & Chat -->
    <div class="modal-overlay" id="chatHistoryModal">
        <div class="modal-dialog" style="max-width: 680px;">
            <div class="modal-header whatsapp-chat-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="chat-avatar" id="chat_avatar">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                        </svg>
                    </div>
                    <div>
                        <div class="modal-title" id="chat_lead_name">Contact Name</div>
                        <a href="#" id="chat_lead_phone_link" class="phone-dialer-link chat-dialer-link" title="Click to call" style="margin-top: 2px;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                            </svg>
                            <span id="chat_lead_phone">+91 00000 00000</span>
                        </a>
                    </div>
                </div>
                <button class="modal-close" data-close-modal>&times;</button>
            </div>
            <div class="modal-body chat-modal-body">
                <!-- Conversation Timeline Bubbles Container -->
                <div class="whatsapp-chat-timeline" id="chatTimeline">
                    <div style="text-align: center; color: #94a3b8; font-size: 12px; padding: 20px;">
                        Loading conversation history...
                    </div>
                </div>

                <!-- Quick Send in Chat -->
                <form id="chatQuickSendForm" class="chat-composer-row">
                    <input type="hidden" id="chat_lead_id">
                    <input type="text" id="chat_message_input" class="form-control" placeholder="Type a WhatsApp reply..." required>
                    <button type="submit" class="btn btn-whatsapp" style="border-radius: 50%; width: 40px; height: 40px; padding: 0; justify-content: center;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="22" y1="2" x2="11" y2="13"/>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Webhook & Gateway Simulator -->
    <div class="modal-overlay" id="simulatorModal">
        <div class="modal-dialog" style="max-width: 820px;">
            <div class="modal-header">
                <h3 class="modal-title">Interactive WhatsApp, Webhook & Automation Simulator</h3>
                <button class="modal-close" data-close-modal>&times;</button>
            </div>
            <div class="modal-body">
                <p style="font-size: 12px; color: #64748b; margin-bottom: 16px;">
                    Local simulator for testing delivery callbacks, status transitions, scheduled worker dispatching, and automated lead generation.
                </p>

                <div class="simulator-grid">
                    <!-- Scenario 1: Transition Outgoing Message Status -->
                    <div class="sim-card">
                        <div class="sim-title">1. Simulate Delivery Receipts</div>
                        <p class="sim-desc">Pick any recent message to trigger Delivered and Read status callbacks via the Webhook.</p>
                        <div class="form-group">
                            <label>Select Message Reference:</label>
                            <select id="sim_message_select" class="form-control"></select>
                        </div>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <button class="btn btn-secondary btn-sm" onclick="triggerStatusWebhook('delivered')">Delivered</button>
                            <button class="btn btn-whatsapp btn-sm" onclick="triggerStatusWebhook('read')">Read</button>
                            <button class="btn btn-secondary btn-sm" style="color: #dc2626;" onclick="triggerStatusWebhook('failed')">Failed</button>
                        </div>
                    </div>

                    <!-- Scenario 2: Simulate Inbound Customer Reply -->
                    <div class="sim-card">
                        <div class="sim-title">2. Simulate Inbound Customer Reply</div>
                        <p class="sim-desc">Simulates a WhatsApp message sent by an existing or new customer.</p>
                        <div class="form-group">
                            <label>Customer Mobile Number:</label>
                            <input type="text" id="sim_incoming_phone" class="form-control" value="+91 98230 12345">
                        </div>
                        <div class="form-group">
                            <label>Incoming Reply Text:</label>
                            <input type="text" id="sim_incoming_text" class="form-control" value="Yes, please share the pricing proposal.">
                        </div>
                        <button class="btn btn-primary btn-sm" onclick="triggerInboundWebhook()">
                            Dispatch Incoming Webhook
                        </button>
                    </div>

                    <!-- Scenario 3: Automate Lead Generation Simulation -->
                    <div class="sim-card" style="border-left: 3px solid #a21caf;">
                        <div class="sim-title" style="color: #86198f;">3. Automate Lead Inflow & Welcome</div>
                        <p class="sim-desc">Simulates a brand new lead arriving from an ad or QR code. The system creates the lead AND immediately auto-replies with an introductory qualification message.</p>
                        <div class="form-group">
                            <label>New Prospect Mobile Number:</label>
                            <input type="text" id="sim_auto_phone" class="form-control" value="+91 99778 89900">
                        </div>
                        <div class="form-group">
                            <label>Inquiry Message:</label>
                            <input type="text" id="sim_auto_inquiry" class="form-control" value="Hi, saw your WhatsApp ad for CRM integration. Want details.">
                        </div>
                        <button class="btn btn-primary btn-sm" style="background: #a21caf; border-color: #a21caf;" onclick="triggerAutomatedLeadGenSimulation()">
                            Simulate Lead Inflow & Auto-Welcome
                        </button>
                    </div>

                    <!-- Scenario 4: Scheduled Messages Queue & Dispatcher -->
                    <div class="sim-card" style="border-left: 3px solid #7c3aed;">
                        <div class="sim-title" style="color: #6b21a8;">4. Scheduled Messages Worker (Cron)</div>
                        <p class="sim-desc">Processes future-scheduled WhatsApp messages. In production, this runs as a cron job every minute.</p>
                        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 10px; margin-bottom: 8px; font-size: 11.5px;">
                            Pending Scheduled Messages: <strong id="sim_scheduled_count" style="color: #7c3aed;">0</strong>
                        </div>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <button class="btn btn-secondary btn-sm" style="background: #7c3aed; color: #ffffff; border-color: #7c3aed;" onclick="runScheduledDispatcher(true)">
                                Run Scheduled Dispatcher
                            </button>
                            <button class="btn btn-secondary btn-sm" onclick="checkScheduledQueue()">
                                Check Queue
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Live Webhook Log Inspector -->
                <div style="margin-top: 18px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label style="font-size: 12px; font-weight: 700; color: #334155;">Live Ingested Webhook Payload (JSON):</label>
                        <button class="btn btn-secondary btn-sm" onclick="fetchRecentWebhookLogs()">Refresh Logs</button>
                    </div>
                    <pre id="sim_payload_view" class="sim-payload-box">{ "status": "Waiting for webhook event..." }</pre>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Close</button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/js/pipeline.js"></script>
    <script src="assets/js/chat.js"></script>
    <script src="assets/js/simulator.js"></script>
</body>
</html>
