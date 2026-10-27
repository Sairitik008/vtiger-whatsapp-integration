<?php
/**
 * REST Webhook Receiver: /api/webhook.php
 * Handles inbound WhatsApp messages, customer replies, and delivery status receipts.
 * Normalizes sender numbers, performs idempotent deduplication, and maps records.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../models/Message.php';
require_once __DIR__ . '/../models/Lead.php';
require_once __DIR__ . '/../services/WhatsAppGateway.php';

$messageModel = new Message();
$leadModel    = new Lead();

// 1. Read Raw Payload
$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);

if (!$payload) {
    // Also accept form POST
    $payload = $_POST;
}

if (empty($payload)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'Empty webhook payload received.'
    ]);
    exit;
}

// 2. Identify Event Type
// Supports both simplified JSON and standard WhatsApp Cloud API payload formats
$eventType = $payload['event_type'] ?? ($payload['type'] ?? 'incoming_message');

try {
    // =========================================================================
    // CASE A: MESSAGE STATUS CALLBACK (Sent -> Delivered -> Read / Failed)
    // =========================================================================
    if ($eventType === 'status_update' || isset($payload['status'])) {
        $messageId     = trim($payload['message_id'] ?? '');
        $status        = strtolower(trim($payload['status'] ?? 'delivered'));
        $failureReason = $payload['failure_reason'] ?? null;

        if (empty($messageId)) {
            throw new InvalidArgumentException('Missing message_id in status update.');
        }

        // Validate allowed statuses
        $allowedStatuses = ['sent', 'delivered', 'read', 'failed'];
        if (!in_array($status, $allowedStatuses, true)) {
            throw new InvalidArgumentException("Invalid status: {$status}");
        }

        // Update database
        $updated = $messageModel->updateStatus($messageId, $status, $failureReason);

        // Audit log
        $messageModel->logWebhook('status_update', $messageId, null, json_encode($payload), $updated ? 'success' : 'failed');

        http_response_code(200);
        echo json_encode([
            'success'    => $updated,
            'event'      => 'status_update',
            'message_id' => $messageId,
            'status'     => $status
        ]);
        exit;
    }

    // =========================================================================
    // CASE B: INCOMING WHATSAPP MESSAGE (Customer Reply / Inbound Chat)
    // =========================================================================
    if ($eventType === 'incoming_message' || $eventType === 'message') {
        $senderNumber    = trim($payload['sender_number'] ?? ($payload['from'] ?? ''));
        $recipientNumber = trim($payload['recipient_number'] ?? ($payload['to'] ?? WhatsAppGateway::BUSINESS_NUMBER));
        $messageContent  = trim($payload['message_content'] ?? ($payload['text'] ?? ($payload['body'] ?? '')));
        $messageId       = trim($payload['message_id'] ?? ('WAM-IN-' . date('YmdHis') . '-' . bin2hex(random_bytes(2))));
        $timestamp       = $payload['timestamp'] ?? date('Y-m-d H:i:s');

        if (empty($senderNumber) || empty($messageContent)) {
            throw new InvalidArgumentException('Missing required fields: sender_number and message_content are mandatory.');
        }

        // Idempotency check: don't process if already ingested
        $existing = $messageModel->findByMessageId($messageId);
        if ($existing) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'note'    => 'Message already ingested (idempotent response).',
                'message_id' => $messageId
            ]);
            exit;
        }

        // Automatic Lead/Contact Mapping
        $matchedLead = $leadModel->findByPhone($senderNumber);
        $leadId = null;
        $isNewLeadCreated = false;

        if ($matchedLead) {
            // Found existing Lead/Contact!
            $leadId = (int)$matchedLead['id'];

            // Advance stage to 'Conversation Started' (Stage 3) if currently in stage 1 or 2
            if ((int)$matchedLead['pipeline_stage_id'] <= 2) {
                $leadModel->updateStage($leadId, 3); // 3 = 'Conversation Started'
            }
        } else {
            // Unmatched number: Automatically create a new Inbound Lead so lead isn't lost!
            $leadId = $leadModel->createFromIncomingNumber($senderNumber, $messageContent);
            $isNewLeadCreated = true;
        }

        // Store incoming message in conversation history
        $messageModel->create([
            'message_id'        => $messageId,
            'lead_id'           => $leadId,
            'sender_number'     => Database::normalizePhone($senderNumber),
            'recipient_number'  => Database::normalizePhone($recipientNumber),
            'direction'         => 'incoming',
            'message_content'   => $messageContent,
            'message_type'      => 'text',
            'status'            => 'read', // incoming messages are already received/read
            'sent_at'           => $timestamp
        ]);

        // Update pipeline card with latest message snippet
        $leadModel->updateLatestMessage($leadId, $messageContent, 'read', $timestamp);

        // Audit log
        $statusLog = $isNewLeadCreated ? 'unmapped' : 'success';
        $messageModel->logWebhook('incoming_message', $messageId, $senderNumber, json_encode($payload), $statusLog);

        // Dispatch welcome message for newly captured inbound leads
        $autoWelcomeResult = null;
        if ($isNewLeadCreated) {
            $gateway = new WhatsAppGateway();
            $autoWelcomeResult = $gateway->sendAutomatedLeadWelcomeMessage($leadId, $senderNumber);
        }

        http_response_code(200);
        echo json_encode([
            'success'                => true,
            'event'                  => 'incoming_message',
            'message_id'             => $messageId,
            'lead_id'                => $leadId,
            'auto_mapped'            => !$isNewLeadCreated,
            'new_lead_created'       => $isNewLeadCreated,
            'lead_name'              => $matchedLead ? ($matchedLead['first_name'] . ' ' . $matchedLead['last_name']) : ('Inbound +' . $senderNumber),
            'auto_welcome_dispatched'=> !empty($autoWelcomeResult),
            'auto_welcome_message_id'=> $autoWelcomeResult['message_id'] ?? null
        ]);
        exit;
    }

    throw new InvalidArgumentException("Unknown event_type: {$eventType}");

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
