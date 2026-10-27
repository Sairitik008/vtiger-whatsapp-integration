<?php
/**
 * WhatsApp Gateway Service
 * Handles outgoing WhatsApp message processing, delivery scheduling,
 * and automated lead response workflows.
 */

require_once __DIR__ . '/../models/Message.php';
require_once __DIR__ . '/../models/Lead.php';

class WhatsAppGateway {
    private Message $messageModel;
    private Lead $leadModel;

    public const BUSINESS_NUMBER = '919876543210';

    public static function getBusinessNumber(): string {
        return getenv('BUSINESS_WHATSAPP_NUMBER') ?: self::BUSINESS_NUMBER;
    }

    public function __construct() {
        $this->messageModel = new Message();
        $this->leadModel    = new Lead();
    }

    /**
     * Generate unique message reference ID
     */
    public static function generateMessageId(): string {
        return 'WAM-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
    }

    /**
     * Send outgoing WhatsApp message to a Lead or Contact
     *
     * @param int $leadId
     * @param string $recipientPhone
     * @param string $messageContent
     * @param bool $simulateFailure
     * @param string|null $scheduledAt
     * @return array
     */
    public function sendMessage(int $leadId, string $recipientPhone, string $messageContent, bool $simulateFailure = false, ?string $scheduledAt = null): array {
        $recipientClean = Database::normalizePhone($recipientPhone);
        $messageId      = self::generateMessageId();
        $timestamp      = date('Y-m-d H:i:s');

        $isScheduled   = !empty($scheduledAt) && strtotime($scheduledAt) > time();
        $initialStatus = $simulateFailure ? 'failed' : ($isScheduled ? 'scheduled' : 'sent');
        $failureReason = $simulateFailure ? 'Recipient number not registered on WhatsApp' : null;

        // Persist message record
        $this->messageModel->create([
            'message_id'        => $messageId,
            'lead_id'           => $leadId,
            'sender_number'     => self::BUSINESS_NUMBER,
            'recipient_number'  => $recipientClean,
            'direction'         => 'outgoing',
            'message_content'   => $messageContent,
            'message_type'      => 'text',
            'status'            => $initialStatus,
            'failure_reason'    => $failureReason,
            'sent_at'           => $isScheduled ? null : $timestamp,
            'scheduled_at'      => $isScheduled ? date('Y-m-d H:i:s', strtotime($scheduledAt)) : null
        ]);

        // Update lead interaction snapshot
        $this->leadModel->updateLatestMessage($leadId, $messageContent, $initialStatus, $timestamp);

        // Advance stage from 'New Lead' to 'WhatsApp Contacted' if immediate dispatch succeeded
        $lead = $this->leadModel->findById($leadId);
        if ($lead && (int)$lead['pipeline_stage_id'] === 1 && !$simulateFailure && !$isScheduled) {
            $this->leadModel->updateStage($leadId, 2);
        }

        // Dispatch to real WhatsApp bridge if active and immediate
        $bridgeInfo = null;
        if (!$simulateFailure && !$isScheduled) {
            $bridgeInfo = self::dispatchToBridge($recipientClean, $messageContent);
        }

        return [
            'success'      => !$simulateFailure,
            'message_id'   => $messageId,
            'status'       => $initialStatus,
            'timestamp'    => $timestamp,
            'scheduled_at' => $isScheduled ? date('Y-m-d H:i:s', strtotime($scheduledAt)) : null,
            'recipient'    => $recipientClean,
            'lead_id'      => $leadId,
            'real_dispatch'=> $bridgeInfo,
            'error'        => $failureReason
        ];
    }

    /**
     * Dispatch real WhatsApp message via local open-source bridge (Baileys)
     */
    public static function dispatchToBridge(string $phone, string $message): array {
        $bridgeUrl = 'http://127.0.0.1:3333/send';
        $payload = json_encode(['phone' => $phone, 'message' => $message]);
        
        $ch = curl_init($bridgeUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST              => true,
            CURLOPT_POSTFIELDS        => $payload,
            CURLOPT_HTTPHEADER        => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER    => true,
            CURLOPT_TIMEOUT_MS        => 2500,
            CURLOPT_CONNECTTIMEOUT_MS => 800
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            return ['dispatched' => true, 'bridge_response' => $data];
        }

        return ['dispatched' => false, 'note' => 'Bridge offline or waiting for QR scan'];
    }

    /**
     * Dispatch automated introductory welcome message to prospective leads
     */
    public function sendAutomatedLeadWelcomeMessage(int $leadId, string $recipientPhone, ?string $recipientName = null): array {
        $nameGreeting = !empty($recipientName) ? " " . trim($recipientName) : "";
        $autoText     = "Hello{$nameGreeting}, thank you for contacting NB Entrepreneurs. We have received your inquiry and our team will get in touch with you shortly. Please let us know how we can assist you.";

        return $this->sendMessage($leadId, $recipientPhone, $autoText, false);
    }
}

