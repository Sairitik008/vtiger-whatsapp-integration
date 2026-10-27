<?php
/**
 * WhatsApp Message Model
 * Handles message persistence, conversation history, and status updates.
 */

require_once __DIR__ . '/../includes/db.php';

class Message {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Store a new WhatsApp message (outgoing or incoming)
     */
    public function create(array $data): int {
        $sql = "INSERT INTO whatsapp_messages 
                (message_id, lead_id, sender_number, recipient_number, direction, message_content, message_type, status, failure_reason, sent_at, scheduled_at)
                VALUES 
                (:message_id, :lead_id, :sender_number, :recipient_number, :direction, :message_content, :message_type, :status, :failure_reason, :sent_at, :scheduled_at)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':message_id'        => $data['message_id'],
            ':lead_id'           => $data['lead_id'] ?? null,
            ':sender_number'     => $data['sender_number'],
            ':recipient_number'  => $data['recipient_number'],
            ':direction'         => $data['direction'],
            ':message_content'   => $data['message_content'],
            ':message_type'      => $data['message_type'] ?? 'text',
            ':status'            => $data['status'] ?? 'sent',
            ':failure_reason'    => $data['failure_reason'] ?? null,
            ':sent_at'           => $data['sent_at'] ?? (isset($data['status']) && $data['status'] === 'scheduled' ? null : date('Y-m-d H:i:s')),
            ':scheduled_at'      => $data['scheduled_at'] ?? null
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Update message status (Sent -> Delivered -> Read or Failed)
     */
    public function updateStatus(string $messageId, string $status, ?string $failureReason = null): bool {
        $fields = ["status = :status"];
        $params = [
            ':status'     => $status,
            ':message_id' => $messageId
        ];

        if ($status === 'delivered') {
            $fields[] = "delivered_at = NOW()";
        } elseif ($status === 'read') {
            // Also ensure delivered_at is populated if read directly
            $fields[] = "read_at = NOW()";
            $fields[] = "delivered_at = IFNULL(delivered_at, NOW())";
        } elseif ($status === 'failed') {
            $fields[] = "failure_reason = :failure_reason";
            $params[':failure_reason'] = $failureReason ?? 'Gateway delivery failure';
        }

        $sql = "UPDATE whatsapp_messages SET " . implode(', ', $fields) . " WHERE message_id = :message_id";
        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute($params);

        // Also synchronize the Lead's pipeline card status
        if ($success) {
            $leadStmt = $this->db->prepare("SELECT lead_id FROM whatsapp_messages WHERE message_id = :message_id LIMIT 1");
            $leadStmt->execute([':message_id' => $messageId]);
            $msg = $leadStmt->fetch();
            if ($msg && $msg['lead_id']) {
                $updLead = $this->db->prepare("UPDATE leads_contacts SET last_message_status = :status WHERE id = :lead_id");
                $updLead->execute([
                    ':status'  => $status,
                    ':lead_id' => $msg['lead_id']
                ]);
            }
        }

        return $success;
    }

    /**
     * Retrieve complete conversation history for a Lead/Contact in chronological order
     */
    public function getConversationByLeadId(int $leadId): array {
        $sql = "SELECT m.*, 
                       DATE_FORMAT(IFNULL(m.sent_at, m.scheduled_at), '%d %b %Y, %h:%i %p') as formatted_time,
                       DATE_FORMAT(m.scheduled_at, '%d %b, %h:%i %p') as formatted_scheduled_time
                FROM whatsapp_messages m
                WHERE m.lead_id = :lead_id
                ORDER BY COALESCE(m.sent_at, m.scheduled_at, m.created_at) ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':lead_id' => $leadId]);
        return $stmt->fetchAll();
    }

    /**
     * Find message by message_id
     */
    public function findByMessageId(string $messageId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM whatsapp_messages WHERE message_id = :message_id LIMIT 1");
        $stmt->execute([':message_id' => $messageId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Get count of pending scheduled messages
     */
    public function getPendingScheduledCount(): int {
        return (int)$this->db->query("SELECT COUNT(*) FROM whatsapp_messages WHERE status = 'scheduled'")->fetchColumn();
    }

    /**
     * Get list of pending scheduled messages
     */
    public function getScheduledMessages(int $limit = 20): array {
        $stmt = $this->db->prepare(
            "SELECT m.*, l.first_name, l.last_name, l.company,
                    DATE_FORMAT(m.scheduled_at, '%d %b %Y, %h:%i %p') as formatted_scheduled_time
             FROM whatsapp_messages m
             LEFT JOIN leads_contacts l ON m.lead_id = l.id
             WHERE m.status = 'scheduled'
             ORDER BY m.scheduled_at ASC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Dispatch due scheduled messages (called via cron / simulator)
     * 
     * @param bool $forceAll If true, immediately dispatches all scheduled messages regardless of time
     * @return array Array of dispatched message records
     */
    public function dispatchDueScheduledMessages(bool $forceAll = false): array {
        $condition = $forceAll ? "status = 'scheduled'" : "status = 'scheduled' AND scheduled_at <= NOW()";
        $stmt = $this->db->query("SELECT * FROM whatsapp_messages WHERE {$condition}");
        $due = $stmt->fetchAll();

        $dispatched = [];
        $updateMsg = $this->db->prepare(
            "UPDATE whatsapp_messages 
             SET status = 'sent', sent_at = NOW() 
             WHERE id = :id"
        );
        $updateLead = $this->db->prepare(
            "UPDATE leads_contacts 
             SET last_message_status = 'sent',
                 last_message_text = :text,
                 last_contacted_at = NOW(),
                 pipeline_stage_id = CASE WHEN pipeline_stage_id = 1 THEN 2 ELSE pipeline_stage_id END,
                 updated_at = NOW()
             WHERE id = :lead_id"
        );

        foreach ($due as $msg) {
            $updateMsg->execute([':id' => $msg['id']]);
            if (!empty($msg['lead_id'])) {
                $updateLead->execute([
                    ':text'    => mb_substr($msg['message_content'], 0, 250),
                    ':lead_id' => $msg['lead_id']
                ]);
            }
            // Dispatch through real WhatsApp bridge if available
            if (class_exists('WhatsAppGateway')) {
                WhatsAppGateway::dispatchToBridge($msg['recipient_number'], $msg['message_content']);
            }

            $dispatched[] = [
                'message_id' => $msg['message_id'],
                'recipient'  => $msg['recipient_number'],
                'content'    => $msg['message_content'],
                'scheduled_for' => $msg['scheduled_at']
            ];
        }

        return $dispatched;
    }

    /**
     * Log raw incoming webhook requests
     */
    public function logWebhook(string $eventType, ?string $messageId, ?string $sender, string $payloadJson, string $status = 'success'): int {
        $sql = "INSERT INTO webhook_logs (event_type, message_id, sender_number, payload_json, ip_address, processed_status)
                VALUES (:event_type, :message_id, :sender, :payload, :ip, :status)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':event_type' => $eventType,
            ':message_id' => $messageId,
            ':sender'     => $sender,
            ':payload'    => $payloadJson,
            ':ip'         => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ':status'     => $status
        ]);
        return (int)$this->db->lastInsertId();
    }
}
