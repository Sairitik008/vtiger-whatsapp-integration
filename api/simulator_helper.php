<?php
/**
 * REST API Endpoint: /api/simulator_helper.php
 * Provides recent messages and raw webhook logs for the simulator UI.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = Database::getConnection();

    // 1. Fetch recent outgoing messages
    $messagesStmt = $pdo->query(
        "SELECT m.message_id, m.recipient_number, m.status, m.message_content, 
                CONCAT(l.first_name, ' ', l.last_name) as lead_name
         FROM whatsapp_messages m
         LEFT JOIN leads_contacts l ON m.lead_id = l.id
         WHERE m.direction = 'outgoing'
         ORDER BY m.id DESC
         LIMIT 15"
    );
    $messages = $messagesStmt->fetchAll();

    // 2. Fetch recent webhook logs
    $logsStmt = $pdo->query(
        "SELECT id, event_type, message_id, sender_number, payload_json, processed_status, 
                DATE_FORMAT(created_at, '%h:%i:%s %p') as log_time
         FROM webhook_logs
         ORDER BY id DESC
         LIMIT 10"
    );
    $logs = $logsStmt->fetchAll();

    echo json_encode([
        'success'  => true,
        'messages' => $messages,
        'logs'     => $logs
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
