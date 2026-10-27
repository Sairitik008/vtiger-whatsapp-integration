<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../models/Lead.php';
require_once __DIR__ . '/../models/Message.php';
require_once __DIR__ . '/../services/WhatsAppGateway.php';

$pdo = Database::getConnection();
$leadModel = new Lead();
$messageModel = new Message();
$gateway = new WhatsAppGateway();

$phone = '+91 9021208580';
$cleanPhone = Database::normalizePhone($phone);

// 1. Fetch or create Lead
$lead = $leadModel->findByPhone($phone);
if (!$lead) {
    $leadId = $leadModel->create([
        'first_name'        => 'Valued Lead',
        'last_name'         => '(9021208580)',
        'email'             => 'inquiry@nbentrepreneurs.com',
        'phone'             => $phone,
        'company'           => 'NB Entrepreneurs Prospective Client',
        'record_type'       => 'Lead',
        'pipeline_stage_id' => 1,
        'next_action'       => '2-minute automated WhatsApp test'
    ]);
} else {
    $leadId = (int)$lead['id'];
}

// 2. Exactly 2 minutes ahead
$currentTime = date('Y-m-d H:i:s');
$scheduledTimestamp = time() + 120; // 2 minutes
$scheduledAt = date('Y-m-d H:i:s', $scheduledTimestamp);

$messageContent = "Hello! This is a 2-minute automated WhatsApp test message from NB Entrepreneurs CRM scheduled for +91 9021208580.";

// 3. Clear previous scheduled messages for this phone to isolate this test
$pdo->prepare("DELETE FROM whatsapp_messages WHERE recipient_number = ? AND status = 'scheduled'")->execute([$cleanPhone]);

// 4. Schedule via WhatsAppGateway
$result = $gateway->sendMessage(
    $leadId,
    $phone,
    $messageContent,
    false,
    $scheduledAt
);

echo "=======================================================\n";
echo "2-MINUTE WHATSAPP AUTOMATION SCHEDULED\n";
echo "=======================================================\n";
echo "Recipient Number: {$phone} ({$cleanPhone})\n";
echo "Lead Record ID:   #{$leadId}\n";
echo "Message ID:       {$result['message_id']}\n";
echo "Current Time:     {$currentTime}\n";
echo "Scheduled Time:   {$scheduledAt} (in exactly 120 seconds)\n";
echo "Initial Status:   {$result['status']}\n";
echo "Pending in Queue: " . $messageModel->getPendingScheduledCount() . "\n";
echo "=======================================================\n";
