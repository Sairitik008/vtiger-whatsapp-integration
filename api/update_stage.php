<?php
/**
 * REST API Endpoint: /api/update_stage.php
 * Persists drag-and-drop or dropdown pipeline stage movements.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/../models/Lead.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: $_POST;

$leadId  = (int)($data['lead_id'] ?? 0);
$stageId = (int)($data['stage_id'] ?? 0);

if ($leadId <= 0 || $stageId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'lead_id and stage_id are required.']);
    exit;
}

try {
    $leadModel = new Lead();
    $updated = $leadModel->updateStage($leadId, $stageId);
    $lead = $leadModel->findById($leadId);

    echo json_encode([
        'success'    => $updated,
        'lead_id'    => $leadId,
        'stage_id'   => $stageId,
        'stage_name' => $lead ? $lead['stage_name'] : ''
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
