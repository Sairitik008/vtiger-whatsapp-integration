<?php
/**
 * REST API & Cron Worker: /api/scheduled_dispatch.php
 * Checks and dispatches due scheduled WhatsApp messages.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../models/Message.php';
$messageModel = new Message();

$method = $_SERVER['REQUEST_METHOD'];

// GET: Check pending scheduled queue
if ($method === 'GET') {
    $pendingCount = $messageModel->getPendingScheduledCount();
    $scheduledList = $messageModel->getScheduledMessages(20);

    echo json_encode([
        'success'         => true,
        'pending_count'   => $pendingCount,
        'scheduled_list'  => $scheduledList,
        'server_time'     => date('Y-m-d H:i:s')
    ]);
    exit;
}

// POST: Trigger dispatcher worker
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?: $_POST;

    $forceAll = !empty($data['force_all']) || !empty($_GET['force']);

    try {
        $dispatched = $messageModel->dispatchDueScheduledMessages($forceAll);
        $remainingCount = $messageModel->getPendingScheduledCount();

        echo json_encode([
            'success'          => true,
            'dispatched_count' => count($dispatched),
            'dispatched'       => $dispatched,
            'remaining_count'  => $remainingCount,
            'message'          => count($dispatched) > 0 
                ? (count($dispatched) . ' scheduled message(s) successfully dispatched!')
                : 'No scheduled messages were due for dispatch.'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error'   => $e->getMessage()
        ]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
