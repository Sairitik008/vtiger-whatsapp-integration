<?php
/**
 * REST API Endpoint: /api/chat.php
 * Fetches chronological conversation history for a specific Lead/Contact.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../models/Message.php';
require_once __DIR__ . '/../models/Lead.php';

$leadId = isset($_GET['lead_id']) ? (int)$_GET['lead_id'] : 0;

if ($leadId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valid lead_id parameter is required.']);
    exit;
}

try {
    $leadModel = new Lead();
    $messageModel = new Message();

    $lead = $leadModel->findById($leadId);
    if (!$lead) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Lead not found.']);
        exit;
    }

    $messages = $messageModel->getConversationByLeadId($leadId);

    echo json_encode([
        'success'  => true,
        'lead'     => [
            'id'    => $lead['id'],
            'name'  => $lead['first_name'] . ' ' . $lead['last_name'],
            'phone' => $lead['phone'],
            'stage' => $lead['stage_name']
        ],
        'messages' => $messages
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
