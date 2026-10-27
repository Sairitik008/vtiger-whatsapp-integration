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
$normalized = Database::normalizePhone($phone);

// 1. Delete prior scheduled test messages for this phone to ensure exact clean state
$pdo->prepare("DELETE FROM whatsapp_messages WHERE recipient_number = ? AND status = 'scheduled'")->execute([$normalized]);

// 2. Fetch or create Lead
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
        'next_action'       => 'Scheduled WhatsApp follow-up in 5 minutes'
    ]);
} else {
    $leadId = (int)$lead['id'];
}

// 3. Exactly 5 minutes from current system time (Asia/Kolkata)
$scheduledTimestamp = time() + (5 * 60);
$scheduledAt = date('Y-m-d H:i:s', $scheduledTimestamp);

$messageContent = "Hello! Greetings from NB Entrepreneurs. This is an automated follow-up message scheduled exactly 5 minutes in advance for your mobile number +91 9021208580.";

$result = $gateway->sendMessage(
    $leadId,
    $phone,
    $messageContent,
    false,
    $scheduledAt
);

echo "SCHEDULED_SUCCESS:\n";
echo "Message ID: " . $result['message_id'] . "\n";
echo "Recipient Phone: " . $phone . " (" . $normalized . ")\n";
echo "Lead ID: " . $leadId . "\n";
echo "Current Time: " . date('Y-m-d H:i:s') . "\n";
echo "Scheduled Dispatch Time: " . $scheduledAt . "\n";
echo "Status: " . $result['status'] . "\n";
echo "Pending Scheduled Queue Count: " . $messageModel->getPendingScheduledCount() . "\n";
