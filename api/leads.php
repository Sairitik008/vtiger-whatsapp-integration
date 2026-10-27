<?php
/**
 * REST API Endpoint: /api/leads.php
 * Handles pipeline board data fetching and new Lead/Contact creation.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../models/Lead.php';
$leadModel = new Lead();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // If specific ID requested
    if (isset($_GET['id'])) {
        $lead = $leadModel->findById((int)$_GET['id']);
        if (!$lead) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Lead not found.']);
            exit;
        }
        echo json_encode(['success' => true, 'data' => $lead]);
        exit;
    }

    // Default: Return full pipeline board
    $board = $leadModel->getPipelineBoard();
    echo json_encode(['success' => true, 'data' => $board]);
    exit;
}

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?: $_POST;

    $firstName = trim($data['first_name'] ?? '');
    $lastName  = trim($data['last_name'] ?? '');
    $phone     = trim($data['phone'] ?? '');

    if (empty($firstName) || empty($lastName) || empty($phone)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'First Name, Last Name, and Phone are mandatory.'
        ]);
        exit;
    }

    try {
        require_once __DIR__ . '/../services/WhatsAppGateway.php';

        $newId = $leadModel->create([
            'first_name'        => $firstName,
            'last_name'         => $lastName,
            'email'             => trim($data['email'] ?? ''),
            'phone'             => $phone,
            'record_type'       => $data['record_type'] ?? 'Lead',
            'company'           => trim($data['company'] ?? ''),
            'pipeline_stage_id' => (int)($data['pipeline_stage_id'] ?? 1),
            'next_action'       => trim($data['next_action'] ?? 'Initiate WhatsApp outreach')
        ]);

        // Dispatch initial outreach message if enabled for new leads
        $autoWelcomeSent = false;
        if (!isset($data['auto_welcome']) || !empty($data['auto_welcome'])) {
            try {
                $gateway = new WhatsAppGateway();
                $gateway->sendAutomatedLeadWelcomeMessage($newId, $phone, $firstName);
                $autoWelcomeSent = true;
            } catch (Exception $wEx) {
                // Log or continue if gateway error
            }
        }

        $created = $leadModel->findById($newId);
        http_response_code(201);
        echo json_encode([
            'success'           => true,
            'auto_welcome_sent' => $autoWelcomeSent,
            'data'              => $created
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
