<?php
/**
 * Lead & Contact Model
 * Handles CRM Lead retrieval, Phone Mapping, and Pipeline Updates.
 */

require_once __DIR__ . '/../includes/db.php';

class Lead {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Find a Lead or Contact by raw or normalized phone number
     */
    public function findByPhone(string $phone): ?array {
        $normalized = Database::normalizePhone($phone);
        
        $sql = "SELECT l.*, s.stage_name, s.stage_key, s.color_code 
                FROM leads_contacts l
                JOIN pipeline_stages s ON l.pipeline_stage_id = s.id
                WHERE l.normalized_phone = :normalized 
                   OR l.normalized_phone LIKE :suffix
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        // Also support matching last 10 digits in case country code was stored differently
        $suffix = '%' . substr($normalized, -10);
        $stmt->execute([
            ':normalized' => $normalized,
            ':suffix'     => $suffix
        ]);

        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Find Lead by ID
     */
    public function findById(int $id): ?array {
        $sql = "SELECT l.*, s.stage_name, s.stage_key, s.color_code 
                FROM leads_contacts l
                JOIN pipeline_stages s ON l.pipeline_stage_id = s.id
                WHERE l.id = :id 
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Get all pipeline stages with their associated leads/contacts
     */
    public function getPipelineBoard(): array {
        // Fetch all stages in sort order
        $stagesStmt = $this->db->query("SELECT * FROM pipeline_stages ORDER BY sort_order ASC");
        $stages = $stagesStmt->fetchAll();

        // Fetch all leads
        $leadsStmt = $this->db->query(
            "SELECT l.*, s.stage_key, s.stage_name 
             FROM leads_contacts l
             JOIN pipeline_stages s ON l.pipeline_stage_id = s.id
             ORDER BY l.updated_at DESC"
        );
        $leads = $leadsStmt->fetchAll();

        // Group leads by stage
        $pipeline = [];
        foreach ($stages as $stage) {
            $stage['leads'] = [];
            $pipeline[$stage['id']] = $stage;
        }

        foreach ($leads as $lead) {
            $stageId = (int)$lead['pipeline_stage_id'];
            if (isset($pipeline[$stageId])) {
                $pipeline[$stageId]['leads'][] = $lead;
            }
        }

        return array_values($pipeline);
    }

    /**
     * Update Lead's pipeline stage
     */
    public function updateStage(int $leadId, int $newStageId): bool {
        $stmt = $this->db->prepare(
            "UPDATE leads_contacts 
             SET pipeline_stage_id = :stage_id, updated_at = NOW() 
             WHERE id = :id"
        );
        return $stmt->execute([
            ':stage_id' => $newStageId,
            ':id'       => $leadId
        ]);
    }

    /**
     * Update latest communication details on the Lead for the Pipeline Card
     */
    public function updateLatestMessage(int $leadId, string $messageText, string $status, ?string $timestamp = null): bool {
        $timestamp = $timestamp ?? date('Y-m-d H:i:s');
        $stmt = $this->db->prepare(
            "UPDATE leads_contacts 
             SET last_message_text = :text,
                 last_message_status = :status,
                 last_contacted_at = :contacted_at,
                 updated_at = NOW()
             WHERE id = :id"
        );
        return $stmt->execute([
            ':text'         => mb_substr($messageText, 0, 250),
            ':status'       => $status,
            ':contacted_at' => $timestamp,
            ':id'           => $leadId
        ]);
    }

    /**
     * Auto-provisions a new Lead record when receiving an inbound message from an unknown number.
     * Prevents lead leakage by capturing prospective customers in the New Lead stage.
     */
    public function createFromIncomingNumber(string $senderPhone, string $firstMessage): int {
        $normalized = Database::normalizePhone($senderPhone);
        $stmt = $this->db->prepare(
            "INSERT INTO leads_contacts 
             (first_name, last_name, phone, normalized_phone, record_type, company, pipeline_stage_id, next_action, last_contacted_at, last_message_text, last_message_status) 
             VALUES 
             (:first_name, :last_name, :phone, :normalized_phone, 'Lead', 'WhatsApp Inbound Lead', 1, 'Review incoming inquiry', NOW(), :last_message, 'read')"
        );

        $stmt->execute([
            ':first_name'       => 'Inbound',
            ':last_name'        => '+' . $normalized,
            ':phone'            => '+' . $normalized,
            ':normalized_phone' => $normalized,
            ':last_message'     => mb_substr($firstMessage, 0, 250)
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Create a new Lead manually from CRM
     */
    public function create(array $data): int {
        $normalized = Database::normalizePhone($data['phone']);
        $stmt = $this->db->prepare(
            "INSERT INTO leads_contacts 
             (first_name, last_name, email, phone, normalized_phone, record_type, company, pipeline_stage_id, next_action) 
             VALUES 
             (:first_name, :last_name, :email, :phone, :normalized_phone, :record_type, :company, :stage_id, :next_action)"
        );

        $stmt->execute([
            ':first_name'       => $data['first_name'],
            ':last_name'        => $data['last_name'],
            ':email'            => $data['email'] ?? null,
            ':phone'            => $data['phone'],
            ':normalized_phone' => $normalized,
            ':record_type'      => $data['record_type'] ?? 'Lead',
            ':company'          => $data['company'] ?? null,
            ':stage_id'         => $data['pipeline_stage_id'] ?? 1,
            ':next_action'      => $data['next_action'] ?? 'Initiate WhatsApp outreach'
        ]);

        return (int)$this->db->lastInsertId();
    }
}
