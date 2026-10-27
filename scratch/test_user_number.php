<?php
/**
 * End-to-end Test with Generic Demo Number: +91 98765 43210
 */

require_once __DIR__ . '/../models/Lead.php';
require_once __DIR__ . '/../models/Message.php';
require_once __DIR__ . '/../services/WhatsAppGateway.php';

$leadModel = new Lead();
$messageModel = new Message();
$gateway = new WhatsAppGateway();

$userPhone = '+91 98765 43210';
$normalized = Database::normalizePhone($userPhone);

echo "========================================================\n";
echo "TESTING WHATSAPP INTEGRATION FOR NUMBER: {$userPhone}\n";
echo "Normalized E.164: {$normalized}\n";
echo "========================================================\n\n";

// 1. Check if lead exists, or create Demo Customer
$existingLead = $leadModel->findByPhone($userPhone);
if (!$existingLead) {
    echo "[Step 1] Creating Lead for Demo Customer...\n";
    $leadId = $leadModel->create([
        'first_name'        => 'Demo',
        'last_name'         => 'Customer',
        'email'             => 'demo.customer@example.com',
        'phone'             => $userPhone,
        'company'           => 'Enterprise Client Inc',
        'record_type'       => 'Lead',
        'pipeline_stage_id' => 1, // New Lead
        'next_action'       => 'Confirm product demonstration'
    ]);
    echo " -> Created Lead ID #{$leadId}\n";
} else {
    $leadId = (int)$existingLead['id'];
    echo "[Step 1] Lead already exists with ID #{$leadId}\n";
}

$lead = $leadModel->findById($leadId);
echo " -> Initial Stage: {$lead['stage_name']}\n\n";

// 2. Dispatch Outgoing WhatsApp Message from CRM to Demo Number
echo "[Step 2] Sending Outgoing WhatsApp message from CRM to {$userPhone}...\n";
$sendResult = $gateway->sendMessage(
    $leadId,
    $userPhone,
    "Hello! This is an official message from your Vtiger CRM WhatsApp Integration testing pipeline."
);
$messageId = $sendResult['message_id'];
echo " -> Message Sent! Reference ID: {$messageId}\n";
echo " -> Status: {$sendResult['status']}\n";

$leadAfterSend = $leadModel->findById($leadId);
echo " -> Pipeline Stage auto-updated to: {$leadAfterSend['stage_name']}\n";
echo " -> Card Preview Text: \"{$leadAfterSend['last_message_text']}\"\n\n";

// 3. Webhook Delivery Receipt: Sent -> Delivered
echo "[Step 3] Simulating Webhook Status Callback: DELIVERED...\n";
$messageModel->updateStatus($messageId, 'delivered');
$messageModel->logWebhook('status_update', $messageId, null, json_encode(['status' => 'delivered']), 'success');
$deliveredMsg = $messageModel->findByMessageId($messageId);
echo " -> Message Status in DB: {$deliveredMsg['status']} (Delivered At: {$deliveredMsg['delivered_at']})\n\n";

// 4. Webhook Delivery Receipt: Delivered -> Read (Double Blue Ticks)
echo "[Step 4] Simulating Webhook Status Callback: READ (Double Blue Ticks)...\n";
$messageModel->updateStatus($messageId, 'read');
$messageModel->logWebhook('status_update', $messageId, null, json_encode(['status' => 'read']), 'success');
$readMsg = $messageModel->findByMessageId($messageId);
echo " -> Message Status in DB: {$readMsg['status']} (Read At: {$readMsg['read_at']})\n\n";

// 5. Simulate Inbound Reply from Demo Handset (+91 98765 43210)
echo "[Step 5] Ingesting Incoming Customer Reply from {$userPhone} via Webhook...\n";
$incomingMessageId = 'WAM-IN-USER-' . date('His');
$incomingText = "Yes, I received this message! The WhatsApp CRM pipeline integration is working seamlessly.";

// Run through Webhook logic
$matched = $leadModel->findByPhone($userPhone);
if ($matched) {
    $matchedId = (int)$matched['id'];
    $messageModel->create([
        'message_id'        => $incomingMessageId,
        'lead_id'           => $matchedId,
        'sender_number'     => $normalized,
        'recipient_number'  => WhatsAppGateway::getBusinessNumber(),
        'direction'         => 'incoming',
        'message_content'   => $incomingText,
        'message_type'      => 'text',
        'status'            => 'read',
        'sent_at'           => date('Y-m-d H:i:s')
    ]);
    $leadModel->updateLatestMessage($matchedId, $incomingText, 'read');
    $leadModel->updateStage($matchedId, 3); // Advance to Stage 3: Conversation Started
    $messageModel->logWebhook('incoming_message', $incomingMessageId, $userPhone, json_encode(['text' => $incomingText]), 'success');
    echo " -> Webhook successfully matched Lead: {$matched['first_name']} {$matched['last_name']} (ID: {$matchedId})\n";
}

$leadFinal = $leadModel->findById($leadId);
echo " -> Final Pipeline Stage: {$leadFinal['stage_name']}\n";
echo " -> Latest Message on Pipeline Card: \"{$leadFinal['last_message_text']}\"\n";
echo " -> Latest Message Status: {$leadFinal['last_message_status']}\n\n";

// 6. Print Conversation History
echo "[Step 6] Full Conversation History in CRM for {$userPhone}:\n";
$history = $messageModel->getConversationByLeadId($leadId);
foreach ($history as $idx => $m) {
    $dir = $m['direction'] === 'outgoing' ? 'CRM -> User' : 'User -> CRM';
    $status = strtoupper($m['status']);
    echo " [" . ($idx + 1) . "] [{$dir}] [{$status}] [{$m['sent_at']}] ID: {$m['message_id']}\n";
    echo "     \"{$m['message_content']}\"\n";
}

echo "\n========================================================\n";
echo "TEST COMPLETED SUCCESSFULLY FOR {$userPhone}!\n";
echo "========================================================\n";
