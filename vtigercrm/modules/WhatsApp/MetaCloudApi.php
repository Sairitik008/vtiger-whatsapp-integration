<?php

class WhatsApp_MetaCloudApi {
	private static function setting($name) {
		$value = getenv($name);
		$file = getenv($name . '_FILE');
		if ($file !== false && $file !== '') {
			$contents = @file_get_contents($file);
			if ($contents === false) {
				throw new RuntimeException('Unable to read the configured WhatsApp secret file.');
			}
			$value = rtrim($contents, "\r\n");
		}
		return $value === false ? '' : trim($value);
	}

	public static function transport() {
		$transport = strtolower(self::setting('WHATSAPP_TRANSPORT'));
		return $transport === '' ? 'mock' : $transport;
	}

	public static function liveSendingEnabled() {
		return self::setting('WHATSAPP_LIVE_SEND_ENABLED') === '1';
	}

	public static function configuration() {
		$version = self::setting('WHATSAPP_GRAPH_API_VERSION');
		$phoneNumberId = self::setting('WHATSAPP_PHONE_NUMBER_ID');
		$accessToken = self::setting('WHATSAPP_ACCESS_TOKEN');
		$systemUserId = self::setting('WHATSAPP_SYSTEM_USER_ID');
		if (!extension_loaded('curl') || !preg_match('/^v[0-9]+\.[0-9]+$/', $version)
			|| !preg_match('/^[0-9]+$/', $phoneNumberId) || $accessToken === ''
			|| !preg_match('/^[1-9][0-9]*$/', $systemUserId)) {
			throw new RuntimeException('Official WhatsApp transport is not fully configured.');
		}
		return array(
			'version' => $version,
			'phone_number_id' => $phoneNumberId,
			'access_token' => $accessToken,
			'system_user_id' => (int) $systemUserId
		);
	}

	public static function webhookSettings() {
		$verifyToken = self::setting('WHATSAPP_WEBHOOK_VERIFY_TOKEN');
		$appSecret = self::setting('WHATSAPP_APP_SECRET');
		if ($verifyToken === '' || $appSecret === '') {
			throw new RuntimeException('WhatsApp webhook verification secrets are not configured.');
		}
		return array('verify_token' => $verifyToken, 'app_secret' => $appSecret);
	}

	public static function verifySignature($rawBody, $signatureHeader, $appSecret) {
		if (!is_string($signatureHeader) || !preg_match('/^sha256=([a-f0-9]{64})$/i', $signatureHeader, $matches)) {
			return false;
		}
		return hash_equals(hash_hmac('sha256', $rawBody, $appSecret), strtolower($matches[1]));
	}

	public static function sendText($to, $body) {
		$config = self::configuration();
		$endpoint = 'https://graph.facebook.com/' . $config['version'] . '/' . $config['phone_number_id'] . '/messages';
		$payload = json_encode(array(
			'messaging_product' => 'whatsapp',
			'recipient_type' => 'individual',
			'to' => $to,
			'type' => 'text',
			'text' => array('preview_url' => false, 'body' => $body)
		));
		if ($payload === false) {
			return array('outcome' => 'failed', 'error' => 'Message could not be encoded as JSON.');
		}

		$handle = curl_init($endpoint);
		curl_setopt_array($handle, array(
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $payload,
			CURLOPT_HTTPHEADER => array(
				'Authorization: Bearer ' . $config['access_token'],
				'Content-Type: application/json',
				'Accept: application/json'
			),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_TIMEOUT => 20,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2
		));
		$responseBody = curl_exec($handle);
		$curlError = curl_errno($handle);
		$httpStatus = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
		curl_close($handle);

		if ($responseBody === false || $curlError !== 0) {
			return array('outcome' => 'unknown', 'error' => 'Provider request outcome is unknown; inspect provider status before retrying.');
		}
		$response = json_decode($responseBody, true);
		if ($httpStatus >= 200 && $httpStatus < 300 && isset($response['messages'][0]['id'])) {
			return array('outcome' => 'accepted', 'provider_message_id' => $response['messages'][0]['id']);
		}
		if ($httpStatus === 429) {
			return array(
				'outcome' => 'retry',
				'retry_after' => max(1, min(3600, (int) (isset($response['error']['error_data']['retry_after']) ? $response['error']['error_data']['retry_after'] : 30))),
				'error' => 'Provider rate limit reached.'
			);
		}
		if ($httpStatus >= 500 || $httpStatus === 0) {
			return array('outcome' => 'unknown', 'error' => 'Provider returned an ambiguous server error; inspect status before retrying.');
		}
		$errorCode = isset($response['error']['code']) ? (int) $response['error']['code'] : 0;
		return array('outcome' => 'failed', 'error' => 'Provider rejected the message (HTTP ' . $httpStatus . ', code ' . $errorCode . ').');
	}
}
