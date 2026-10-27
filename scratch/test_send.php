<?php
require_once __DIR__ . '/../services/WhatsAppGateway.php';

$gateway = new WhatsAppGateway();
$leadModel = new Lead();

echo "Before Send - Lead #1 Stage: " . $leadModel->findById(1)['stage_name'] . "\n";

$result = $gateway->sendMessage(1, '+91 98230 12345', 'Hello Rahul, greetings from NB Entrepreneurs! How can we assist you today?');
echo "Send result:\n";
print_r($result);

$leadAfter = $leadModel->findById(1);
echo "After Send - Lead #1 Stage: " . $leadAfter['stage_name'] . "\n";
echo "After Send - Last Message: " . $leadAfter['last_message_text'] . "\n";
echo "After Send - Last Status: " . $leadAfter['last_message_status'] . "\n";
