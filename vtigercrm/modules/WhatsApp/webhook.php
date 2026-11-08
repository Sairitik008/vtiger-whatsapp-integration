<?php

chdir(dirname(__FILE__) . '/../..');
require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'include/utils/utils.php';
require_once 'include/database/PearDatabase.php';
require_once 'modules/WhatsApp/WhatsApp.php';

header('Cache-Control: no-store');

function whatsappWebhookRespond($status, $body) {
	http_response_code($status);
	header('Content-Type: text/plain; charset=utf-8');
	echo $body;
	exit;
}

if (WhatsApp_MetaCloudApi::transport() !== 'meta') {
	whatsappWebhookRespond(503, 'Webhook is not enabled.');
}

try {
	$settings = WhatsApp_MetaCloudApi::webhookSettings();
	if ($_SERVER['REQUEST_METHOD'] === 'GET') {
		$mode = isset($_GET['hub_mode']) ? $_GET['hub_mode'] : (isset($_GET['hub.mode']) ? $_GET['hub.mode'] : '');
		$token = isset($_GET['hub_verify_token']) ? $_GET['hub_verify_token'] : (isset($_GET['hub.verify_token']) ? $_GET['hub.verify_token'] : '');
		$challenge = isset($_GET['hub_challenge']) ? $_GET['hub_challenge'] : (isset($_GET['hub.challenge']) ? $_GET['hub.challenge'] : '');
		if (!is_string($mode) || !is_string($token) || !is_string($challenge)
			|| $mode !== 'subscribe' || $token === '' || !hash_equals($settings['verify_token'], $token)
			|| !preg_match('/^[0-9]{1,128}$/', (string) $challenge)) {
			whatsappWebhookRespond(403, 'Webhook verification failed.');
		}
		whatsappWebhookRespond(200, $challenge);
	}

	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		header('Allow: GET, POST');
		whatsappWebhookRespond(405, 'Method not allowed.');
	}
	$contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
	if ($contentLength > 1048576) {
		whatsappWebhookRespond(413, 'Payload too large.');
	}
	WhatsApp_MetaCloudApi::configuration();
	$input = fopen('php://input', 'rb');
	if ($input === false) {
		throw new RuntimeException('Webhook request body could not be read.');
	}
	$rawBody = stream_get_contents($input, 1048577);
	fclose($input);
	if ($rawBody === false || strlen($rawBody) > 1048576) {
		whatsappWebhookRespond(413, 'Payload too large.');
	}
	$signature = isset($_SERVER['HTTP_X_HUB_SIGNATURE_256']) ? $_SERVER['HTTP_X_HUB_SIGNATURE_256'] : '';
	if (!WhatsApp_MetaCloudApi::verifySignature($rawBody, $signature, $settings['app_secret'])) {
		whatsappWebhookRespond(401, 'Invalid webhook signature.');
	}
	WhatsApp_Mock_Service::queueProviderWebhook($rawBody);
	whatsappWebhookRespond(200, 'EVENT_RECEIVED');
} catch (Throwable $exception) {
	error_log('WhatsApp webhook ingestion failed (' . get_class($exception) . ').');
	whatsappWebhookRespond(503, 'Webhook could not be accepted.');
}
