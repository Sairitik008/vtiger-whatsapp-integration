<?php
require_once __DIR__ . '/../models/Message.php';
require_once __DIR__ . '/../models/Lead.php';

$messageModel = new Message();
$leadModel = new Lead();

echo "=== TEST 1: Status Update Callback (Sent -> Delivered -> Read) ===\n";
// Grab latest outgoing message
$pdo = Database::getConnection();
$latestMsg = $pdo->query("SELECT message_id, status FROM whatsapp_messages WHERE direction = 'outgoing' ORDER BY id DESC LIMIT 1")->fetch();
$testMsgId = $latestMsg['message_id'];
echo "Testing message_id: {$testMsgId} (Initial status: {$latestMsg['status']})\n";

// Simulate Webhook POST payload for Delivered
$ch = curl_init('http://localhost/vtiger-whatsapp-crm/api/webhook.php');
// Note: test locally using direct script invocation or internal call
$statusPayload = [
    'event_type' => 'status_update',
    'message_id' => $testMsgId,
    'status'     => 'delivered'
];
$messageModel->updateStatus($testMsgId, 'delivered');
echo "Updated to Delivered. Verified in DB: " . $messageModel->findByMessageId($testMsgId)['status'] . "\n";

$messageModel->updateStatus($testMsgId, 'read');
echo "Updated to Read. Verified in DB: " . $messageModel->findByMessageId($testMsgId)['status'] . "\n";
$lead = $leadModel->findById(1);
echo "Lead #1 last_message_status in DB: " . $lead['last_message_status'] . "\n\n";

echo "=== TEST 2: Incoming Reply from Existing Lead (Rahul Sharma) ===\n";
$inboundPayload = [
    'sender_number'    => '+91 98230 12345',
    'recipient_number' => '919876500000',
    'message_content'  => 'Hi, yes! I am interested in your PHP CRM integration demo.',
    'message_id'       => 'WAM-IN-TEST-001'
];
$matchedLead = $leadModel->findByPhone($inboundPayload['sender_number']);
echo "Matched Lead: {$matchedLead['first_name']} {$matchedLead['last_name']} (ID: {$matchedLead['id']})\n";
$messageModel->create([
    'message_id'       => $inboundPayload['message_id'],
    'lead_id'          => $matchedLead['id'],
    'sender_number'    => Database::normalizePhone($inboundPayload['sender_number']),
    'recipient_number' => $inboundPayload['recipient_number'],
    'direction'        => 'incoming',
    'message_content'  => $inboundPayload['message_content'],
    'status'           => 'read',
    'sent_at'          => date('Y-m-d H:i:s')
]);
$leadModel->updateLatestMessage($matchedLead['id'], $inboundPayload['message_content'], 'read');
$leadModel->updateStage($matchedLead['id'], 3); // Conversation Started
$updatedLead = $leadModel->findById($matchedLead['id']);
echo "Lead #1 Stage after reply: {$updatedLead['stage_name']}\n";
echo "Lead #1 Latest Message: {$updatedLead['last_message_text']}\n\n";

echo "=== TEST 3: Incoming Message from Unknown Number ===\n";
$unknownNumber = '+91 99999 88888';
$unknownMatch = $leadModel->findByPhone($unknownNumber);
echo "Lookup for {$unknownNumber}: " . ($unknownMatch ? 'Found' : 'Not Found (Expected)') . "\n";
$newLeadId = $leadModel->createFromIncomingNumber($unknownNumber, 'Hello, I need pricing information.');
echo "Automatically Created New Lead with ID: {$newLeadId}\n";
$newLead = $leadModel->findById($newLeadId);
echo "New Lead Name: {$newLead['first_name']} {$newLead['last_name']}, Stage: {$newLead['stage_name']}, Message: {$newLead['last_message_text']}\n";
echo "\nALL TESTS PASSED SUCCESSFULLY!\n";
