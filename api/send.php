<?php
/**
 * REST API Endpoint: /api/send.php
 * Handles outgoing WhatsApp message requests from Vtiger CRM UI.
 */

header('Content-Type: application/json; charset=utf-8');

// Allow local CORS for testing
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Only POST is accepted.']);
    exit;
}

require_once __DIR__ . '/../services/WhatsAppGateway.php';

// Parse incoming JSON body
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data) {
    // Also check standard $_POST form submission
    $data = $_POST;
}

$leadId          = isset($data['lead_id']) ? (int)$data['lead_id'] : 0;
$phone           = trim($data['phone'] ?? '');
$message         = trim($data['message'] ?? '');
$simulateFailure = !empty($data['simulate_failure']);
$scheduledAt     = !empty($data['scheduled_at']) ? trim($data['scheduled_at']) : null;
$isAutoWelcome   = !empty($data['auto_welcome']) || (!empty($data['action']) && $data['action'] === 'auto_welcome');

// Special Action: Trigger Automated Lead Generation Outreach
if ($isAutoWelcome && $leadId > 0) {
    try {
        $leadModel = new Lead();
        $lead = $leadModel->findById($leadId);
        if (!$lead) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Lead not found.']);
            exit;
        }
        $gateway = new WhatsAppGateway();
        $leadName = trim($lead['first_name'] . ' ' . $lead['last_name']);
        $result = $gateway->sendAutomatedLeadWelcomeMessage($leadId, $lead['phone'], $lead['first_name']);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'is_automated' => true,
            'message' => 'Automated lead generation message dispatched successfully!',
            'data'    => $result
        ]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Validation
if ($leadId <= 0 || empty($phone) || empty($message)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'Missing required parameters: lead_id, phone, and message are mandatory.'
    ]);
    exit;
}

try {
    $gateway = new WhatsAppGateway();
    $result = $gateway->sendMessage($leadId, $phone, $message, $simulateFailure, $scheduledAt);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data'    => $result
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Internal server error: ' . $e->getMessage()
    ]);
}
