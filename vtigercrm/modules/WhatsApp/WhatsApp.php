<?php
require_once __DIR__ . '/MetaCloudApi.php';

class WhatsApp_Mock_Service {
	const MAX_MESSAGE_LENGTH = 4096;
	const MAX_PHONE_DIGITS = 20;

	private static function db() {
		return PearDatabase::getInstance();
	}

	public static function normalizePhone($phone) {
		$digits = preg_replace('/\D+/', '', (string) $phone);
		if (strlen($digits) < 5 || strlen($digits) > self::MAX_PHONE_DIGITS) {
			throw new InvalidArgumentException('Enter a phone number containing 5 to 20 digits.');
		}
		return $digits;
	}

	public static function newId() {
		return bin2hex(random_bytes(16));
	}

	private static function validateMessage($message) {
		$message = trim((string) $message);
		if ($message === '' || strlen($message) > self::MAX_MESSAGE_LENGTH || preg_match('//u', $message) !== 1) {
			throw new InvalidArgumentException('Message must be valid UTF-8, non-empty, and no longer than 4096 bytes.');
		}
		return $message;
	}

	public static function resolveRecord($module, $recordId) {
		if (!in_array($module, array('Leads', 'Contacts'), true) || !ctype_digit((string) $recordId)) {
			throw new InvalidArgumentException('Choose a valid Lead or Contact record.');
		}
		if (!Users_Privileges_Model::isPermitted($module, 'DetailView', $recordId)) {
			throw new AppException(vtranslate('LBL_PERMISSION_DENIED'));
		}

		$record = Vtiger_Record_Model::getInstanceById($recordId, $module);
		if (!$record) {
			throw new InvalidArgumentException('The selected CRM record could not be found.');
		}
		$phone = $record->get('phone');
		if (empty($phone)) {
			$phone = $record->get('mobile');
		}
		if (empty($phone)) {
			throw new InvalidArgumentException('The selected CRM record has no phone number.');
		}

		return array(
			'module' => $module,
			'recordid' => (int) $recordId,
			'phone' => self::normalizePhone($phone),
			'label' => $record->getName()
		);
	}

	public static function send($phone, $message, $requestId, $relatedModule = '', $relatedRecordId = '', $consentAcknowledged = false) {
		$phone = self::normalizePhone($phone);
		$message = self::validateMessage($message);
		if (!preg_match('/^[a-f0-9]{32}$/', (string) $requestId)) {
			throw new InvalidArgumentException('A valid outbound request ID is required.');
		}
		if ($relatedModule !== '' || $relatedRecordId !== '') {
			if ($relatedModule === '' || $relatedRecordId === '') {
				throw new InvalidArgumentException('Both CRM module and record ID are required to link a message.');
			}
			$record = self::resolveRecord($relatedModule, $relatedRecordId);
			$phone = $record['phone'];
			$relatedModule = $record['module'];
			$relatedRecordId = $record['recordid'];
		} else {
			$relatedModule = null;
			$relatedRecordId = null;
		}

		if (WhatsApp_MetaCloudApi::transport() === 'meta') {
			return self::queueLiveSend($phone, $message, $requestId, $relatedModule, $relatedRecordId, $consentAcknowledged);
		}
		if (WhatsApp_MetaCloudApi::transport() !== 'mock') {
			throw new RuntimeException('WHATSAPP_TRANSPORT must be either mock or meta.');
		}
		$payload = array('phone' => $phone, 'message' => $message);
		$eventId = 'send:' . $requestId;
		$alreadyProcessed = self::saveEvent($eventId, null, 'outbound_send', $payload);
		$event = self::db()->pquery('SELECT payload FROM vtiger_whatsapp_events WHERE event_id = ?', array($eventId));
		if (!$event || !self::db()->num_rows($event)
			|| self::db()->query_result($event, 0, 'payload') !== json_encode($payload)) {
			throw new InvalidArgumentException('This request ID was already used with different message data.');
		}
		if ($alreadyProcessed) {
			$result = self::db()->pquery(
				'SELECT message_id FROM vtiger_whatsapp_messages WHERE source_event_id = ? AND created_by = ? AND direction = ?',
				array($eventId, Users_Record_Model::getCurrentUserModel()->getId(), 'OUT')
			);
			if (!$result || !self::db()->num_rows($result)) {
				throw new RuntimeException('The outbound request was processed but its message record is missing.');
			}
			return self::db()->query_result($result, 0, 'message_id');
		}

		$id = self::newId();
		$userId = Users_Record_Model::getCurrentUserModel()->getId();
		$matchState = $relatedRecordId === null ? 'unlinked' : 'linked';
		$db = self::db();
		$insert = $db->pquery(
			'INSERT INTO vtiger_whatsapp_messages
				(message_id, source_event_id, direction, phone, message, status, related_module, related_recordid, match_state, created_by)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
				ON DUPLICATE KEY UPDATE source_event_id = source_event_id',
			array($id, $eventId, 'OUT', $phone, $message, 'SENT', $relatedModule, $relatedRecordId, $matchState, $userId)
		);
		if (!$insert) {
			throw new RuntimeException('The outbound mock message could not be saved.');
		}
		$result = $db->pquery(
			'SELECT message_id FROM vtiger_whatsapp_messages WHERE source_event_id = ? AND created_by = ? AND direction = ?',
			array($eventId, $userId, 'OUT')
		);
		if (!$result || !$db->num_rows($result)) {
			throw new RuntimeException('The outbound mock message was not available after saving.');
		}
		$id = $db->query_result($result, 0, 'message_id');
		$updated = $db->pquery("UPDATE vtiger_whatsapp_events SET message_id = ?, status = 'processed' WHERE event_id = ?", array($id, $eventId));
		if (!$updated) {
			throw new RuntimeException('The outbound message was saved but its event could not be marked processed. Retry the same request.');
		}
		return $id;
	}

	private static function queueLiveSend($phone, $message, $requestId, $relatedModule, $relatedRecordId, $consentAcknowledged) {
		if (!WhatsApp_MetaCloudApi::liveSendingEnabled()) {
			throw new RuntimeException('Live WhatsApp sending is disabled. Enable it explicitly after configuring and reviewing the official provider.');
		}
		$config = WhatsApp_MetaCloudApi::configuration();
		if (!$consentAcknowledged) {
			throw new InvalidArgumentException('Confirm that the recipient opted in before queuing a live WhatsApp message.');
		}
		$db = self::db();
		$window = $db->pquery(
			"SELECT created_by, related_module, related_recordid FROM vtiger_whatsapp_messages
				WHERE phone = ? AND direction = 'IN' AND transport = 'meta'
					AND created_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 24 HOUR)
				ORDER BY created_at DESC LIMIT 100",
			array($phone)
		);
		$currentUserId = (int) Users_Record_Model::getCurrentUserModel()->getId();
		$canReply = false;
		for ($index = 0; $window && $index < $db->num_rows($window); $index++) {
			$inbound = $db->query_result_rowdata($window, $index);
			$ownsUnmatchedConversation = (int) $inbound['created_by'] === $currentUserId;
			$canViewRelatedRecord = in_array($inbound['related_module'], array('Leads', 'Contacts'), true)
				&& (int) $inbound['related_recordid'] > 0
				&& Users_Privileges_Model::isPermitted(
					$inbound['related_module'],
					'DetailView',
					(int) $inbound['related_recordid']
				);
			if ($ownsUnmatchedConversation || $canViewRelatedRecord) {
				$canReply = true;
				break;
			}
		}
		if (!$canReply) {
			throw new InvalidArgumentException('No accessible inbound conversation is within the 24-hour reply window. Approved template sending is not implemented.');
		}

		$eventId = 'send:' . $requestId;
		$payload = array('phone' => $phone, 'message' => $message, 'consent_acknowledged' => true);
		$alreadyProcessed = self::saveEvent($eventId, null, 'outbound_send', $payload);
		$event = $db->pquery('SELECT payload FROM vtiger_whatsapp_events WHERE event_id = ?', array($eventId));
		if (!$event || !$db->num_rows($event) || $db->query_result($event, 0, 'payload') !== json_encode($payload)) {
			throw new InvalidArgumentException('This request ID was already used with different message data.');
		}
		$userId = (int) Users_Record_Model::getCurrentUserModel()->getId();
		if ($alreadyProcessed) {
			$result = $db->pquery(
				'SELECT message_id FROM vtiger_whatsapp_messages WHERE source_event_id = ? AND created_by = ? AND direction = ?',
				array($eventId, $userId, 'OUT')
			);
			if (!$result || !$db->num_rows($result)) {
				throw new RuntimeException('The processed outbound request has no message record.');
			}
			return $db->query_result($result, 0, 'message_id');
		}
		$existing = $db->pquery(
			'SELECT message_id FROM vtiger_whatsapp_messages WHERE source_event_id = ? AND created_by = ? AND direction = ?',
			array($eventId, $userId, 'OUT')
		);
		if ($existing && $db->num_rows($existing)) {
			$id = $db->query_result($existing, 0, 'message_id');
			$outbox = $db->pquery(
				"INSERT INTO vtiger_whatsapp_outbox (outbox_id, message_id, status)
					VALUES (?, ?, 'queued') ON DUPLICATE KEY UPDATE message_id = message_id",
				array(self::newId(), $id)
			);
			if (!$outbox) {
				throw new RuntimeException('The existing outbound message could not be restored to the delivery queue.');
			}
			$db->pquery("UPDATE vtiger_whatsapp_events SET status = 'processed', message_id = ? WHERE event_id = ?", array($id, $eventId));
			return $id;
		}

		$id = self::newId();
		$matchState = $relatedRecordId === null ? 'unlinked' : 'linked';
		$insert = $db->pquery(
			"INSERT INTO vtiger_whatsapp_messages
				(message_id, source_event_id, direction, phone, message, status, related_module, related_recordid, match_state, created_by, transport, consent_acknowledged, consent_acknowledged_at)
				VALUES (?, ?, 'OUT', ?, ?, 'QUEUED', ?, ?, ?, ?, 'meta', 1, CURRENT_TIMESTAMP)
				ON DUPLICATE KEY UPDATE source_event_id = source_event_id",
			array($id, $eventId, $phone, $message, $relatedModule, $relatedRecordId, $matchState, $userId)
		);
		if (!$insert) {
			throw new RuntimeException('The live outbound message could not be queued.');
		}
		$result = $db->pquery(
			'SELECT message_id FROM vtiger_whatsapp_messages WHERE source_event_id = ? AND created_by = ? AND direction = ?',
			array($eventId, $userId, 'OUT')
		);
		if (!$result || !$db->num_rows($result)) {
			throw new RuntimeException('The queued outbound message was not available after saving.');
		}
		$id = $db->query_result($result, 0, 'message_id');
		$outbox = $db->pquery(
			"INSERT INTO vtiger_whatsapp_outbox (outbox_id, message_id, status)
				VALUES (?, ?, 'queued') ON DUPLICATE KEY UPDATE message_id = message_id",
			array(self::newId(), $id)
		);
		if (!$outbox) {
			throw new RuntimeException('The message was saved but could not be queued for delivery.');
		}
		$updated = $db->pquery("UPDATE vtiger_whatsapp_events SET message_id = ?, status = 'processed' WHERE event_id = ?", array($id, $eventId));
		if (!$updated) {
			throw new RuntimeException('The outbound message was queued but its request event could not be marked processed.');
		}
		return $id;
	}

	private static function matchPhone($phone, $checkPermissions = true) {
		$db = self::db();
		$matches = array();
		$queries = array(
			array('Leads', 'vtiger_leaddetails', 'leadid'),
			array('Contacts', 'vtiger_contactdetails', 'contactid')
		);

		foreach ($queries as $source) {
			list($module, $table, $idColumn) = $source;
			$result = $db->pquery(
				"SELECT entity.crmid
					FROM $table AS records
					INNER JOIN vtiger_crmentity AS entity ON entity.crmid = records.$idColumn
					WHERE entity.deleted = 0
						AND (REGEXP_REPLACE(COALESCE(records.phone, ''), '[^0-9]', '') = ?
							OR REGEXP_REPLACE(COALESCE(records.mobile, ''), '[^0-9]', '') = ?)
					ORDER BY entity.crmid DESC",
				array($phone, $phone)
			);
			if (!$result) {
				throw new RuntimeException('CRM records could not be checked for a matching phone number.');
			}

			for ($rowIndex = 0; $rowIndex < $db->num_rows($result); $rowIndex++) {
				$row = $db->query_result_rowdata($result, $rowIndex);
				$recordId = (int) $row['crmid'];
				if (!$checkPermissions || Users_Privileges_Model::isPermitted($module, 'DetailView', $recordId)) {
					$matches[$module . ':' . $recordId] = array(
						'module' => $module,
						'recordid' => $recordId
					);
				}
			}
		}

		return array_values($matches);
	}

	private static function saveEvent($eventId, $messageId, $eventType, $payload) {
		$db = self::db();
		$result = $db->pquery(
			'INSERT IGNORE INTO vtiger_whatsapp_events (event_id, message_id, event_type, payload, status)
				VALUES (?, ?, ?, ?, ?)',
			array($eventId, $messageId, $eventType, json_encode($payload), 'processing')
		);
		if (!$result) {
			throw new RuntimeException('The mock event could not be saved.');
		}
		if ($db->getAffectedRowCount($result) > 0) {
			return false;
		}

		$result = $db->pquery('SELECT status FROM vtiger_whatsapp_events WHERE event_id = ?', array($eventId));
		if (!$result || !$db->num_rows($result)) {
			throw new RuntimeException('The mock event could not be saved.');
		}
		return $db->query_result($result, 0, 'status') === 'processed';
	}

	public static function receive($eventId, $phone, $message) {
		if (!preg_match('/^[A-Za-z0-9._:-]{8,100}$/', (string) $eventId) || preg_match('/^(send:|status:|sent:)/', (string) $eventId)) {
			throw new InvalidArgumentException('Event ID must contain 8 to 100 letters, digits, dots, underscores, colons, or hyphens and must not use a reserved prefix.');
		}
		$phone = self::normalizePhone($phone);
		$message = self::validateMessage($message);
		$payload = array('phone' => $phone, 'message' => $message);
		if (self::saveEvent($eventId, null, 'inbound_message', $payload)) {
			return array('duplicate' => true, 'message_id' => null, 'match_state' => 'duplicate');
		}

		$db = self::db();
		$result = $db->pquery(
			'SELECT message_id, related_module, related_recordid, match_state
				FROM vtiger_whatsapp_messages WHERE source_event_id = ?',
			array($eventId)
		);
		if ($db->num_rows($result)) {
			$messageId = $db->query_result($result, 0, 'message_id');
			$matchState = $db->query_result($result, 0, 'match_state');
		} else {
			$matches = self::matchPhone($phone);
			$relatedModule = count($matches) === 1 ? $matches[0]['module'] : null;
			$relatedRecordId = count($matches) === 1 ? $matches[0]['recordid'] : null;
			$matchState = count($matches) === 1 ? 'matched' : (count($matches) ? 'ambiguous' : 'unmatched');
			$messageId = self::newId();
			$insert = $db->pquery(
				'INSERT INTO vtiger_whatsapp_messages
					(message_id, source_event_id, direction, phone, message, status, related_module, related_recordid, match_state, created_by)
					VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
					ON DUPLICATE KEY UPDATE source_event_id = source_event_id',
				array($messageId, $eventId, 'IN', $phone, $message, 'RECEIVED', $relatedModule, $relatedRecordId, $matchState, Users_Record_Model::getCurrentUserModel()->getId())
			);
			if (!$insert) {
				throw new RuntimeException('The inbound mock message could not be saved.');
			}
			$result = $db->pquery(
				'SELECT message_id, match_state FROM vtiger_whatsapp_messages WHERE source_event_id = ?',
				array($eventId)
			);
			if (!$db->num_rows($result)) {
				throw new RuntimeException('The inbound mock message was not available after saving.');
			}
			$messageId = $db->query_result($result, 0, 'message_id');
			$matchState = $db->query_result($result, 0, 'match_state');
		}

		$updated = $db->pquery("UPDATE vtiger_whatsapp_events SET message_id = ?, status = 'processed' WHERE event_id = ?", array($messageId, $eventId));
		if (!$updated) {
			throw new RuntimeException('The inbound event was saved but could not be marked processed. Retry the same event ID.');
		}
		return array('duplicate' => false, 'message_id' => $messageId, 'match_state' => $matchState);
	}

	public static function updateStatus($messageId, $targetStatus) {
		if (!preg_match('/^[a-f0-9]{32}$/', (string) $messageId) || !in_array($targetStatus, array('DELIVERED', 'READ', 'FAILED'), true)) {
			throw new InvalidArgumentException('The message or requested status is invalid.');
		}
		$db = self::db();
		$result = $db->pquery(
			'SELECT status, direction, transport FROM vtiger_whatsapp_messages WHERE message_id = ? AND created_by = ?',
			array($messageId, Users_Record_Model::getCurrentUserModel()->getId())
		);
		if (!$db->num_rows($result)) {
			throw new InvalidArgumentException('The message was not found.');
		}
		$currentStatus = $db->query_result($result, 0, 'status');
		$direction = $db->query_result($result, 0, 'direction');
		$transport = $db->query_result($result, 0, 'transport');
		if ($transport !== 'mock') {
			throw new InvalidArgumentException('Live provider message statuses are updated only by verified provider webhooks.');
		}

		$eventId = 'status:' . $messageId . ':' . $targetStatus;
		if (self::saveEvent($eventId, $messageId, 'status_' . strtolower($targetStatus), array())) {
			return array('duplicate' => true, 'status' => $targetStatus);
		}

		$allowed = ($currentStatus === 'SENT' && $direction === 'OUT' && in_array($targetStatus, array('DELIVERED', 'FAILED'), true))
			|| ($currentStatus === 'DELIVERED' && $direction === 'OUT' && $targetStatus === 'READ')
			|| $currentStatus === $targetStatus;
		if (!$allowed) {
			$db->pquery("DELETE FROM vtiger_whatsapp_events WHERE event_id = ? AND status = 'processing'", array($eventId));
			throw new InvalidArgumentException('Invalid status transition: ' . $currentStatus . ' cannot become ' . $targetStatus . '.');
		}

		if ($currentStatus !== $targetStatus) {
			$updated = $db->pquery(
				'UPDATE vtiger_whatsapp_messages SET status = ?, updated_at = CURRENT_TIMESTAMP
					WHERE message_id = ? AND status = ? AND direction = ? AND created_by = ?',
				array($targetStatus, $messageId, $currentStatus, 'OUT', Users_Record_Model::getCurrentUserModel()->getId())
			);
			if (!$updated) {
				$db->pquery("DELETE FROM vtiger_whatsapp_events WHERE event_id = ? AND status = 'processing'", array($eventId));
				throw new RuntimeException('The message status could not be saved.');
			}
			if ($db->getAffectedRowCount($updated) === 0) {
				$latest = $db->pquery(
					'SELECT status FROM vtiger_whatsapp_messages WHERE message_id = ? AND created_by = ?',
					array($messageId, Users_Record_Model::getCurrentUserModel()->getId())
				);
				if ($db->num_rows($latest) && $db->query_result($latest, 0, 'status') === $targetStatus) {
					$eventUpdated = $db->pquery("UPDATE vtiger_whatsapp_events SET status = 'processed' WHERE event_id = ?", array($eventId));
					if (!$eventUpdated) {
						throw new RuntimeException('The status already changed, but its event could not be marked processed.');
					}
					return array('duplicate' => true, 'status' => $targetStatus);
				}
				$db->pquery("DELETE FROM vtiger_whatsapp_events WHERE event_id = ? AND status = 'processing'", array($eventId));
				throw new InvalidArgumentException('The message status changed concurrently. Refresh the page and retry the valid transition.');
			}
		}
		$updated = $db->pquery("UPDATE vtiger_whatsapp_events SET status = 'processed' WHERE event_id = ?", array($eventId));
		if (!$updated) {
			throw new RuntimeException('The status changed but its mock event could not be marked processed.');
		}
		return array('duplicate' => false, 'status' => $targetStatus);
	}

	public static function queueProviderWebhook($rawBody) {
		if (!is_string($rawBody) || $rawBody === '' || strlen($rawBody) > 1048576) {
			throw new InvalidArgumentException('Webhook payload is empty or exceeds the 1 MiB limit.');
		}
		$payload = json_decode($rawBody, true);
		if (!is_array($payload) || !isset($payload['object']) || $payload['object'] !== 'whatsapp_business_account'
			|| !isset($payload['entry']) || !is_array($payload['entry'])) {
			throw new InvalidArgumentException('Webhook payload has an unsupported structure.');
		}
		$eventId = 'meta:' . hash('sha256', $rawBody);
		$db = self::db();
		$result = $db->pquery(
			"INSERT IGNORE INTO vtiger_whatsapp_events (event_id, event_type, payload, status)
				VALUES (?, 'provider_webhook', ?, 'queued')",
			array($eventId, $rawBody)
		);
		if (!$result) {
			throw new RuntimeException('The verified provider webhook could not be persisted.');
		}
		return $eventId;
	}

	private static function applyProviderStatus($providerMessageId, $providerStatus, $timestamp, $errorCode = 0) {
		$map = array('sent' => 'SENT', 'delivered' => 'DELIVERED', 'read' => 'READ', 'failed' => 'FAILED');
		if (!isset($map[$providerStatus]) || !preg_match('/^[0-9]{1,16}$/', (string) $timestamp)) {
			throw new InvalidArgumentException('Provider status update is invalid.');
		}
		$db = self::db();
		$result = $db->pquery(
			'SELECT message_id, status, provider_status_at FROM vtiger_whatsapp_messages
				WHERE provider_message_id = ? AND transport = ?',
			array($providerMessageId, 'meta')
		);
		if (!$result || !$db->num_rows($result)) {
			throw new RuntimeException('Provider status arrived before its outbound message was correlated.');
		}
		$messageId = $db->query_result($result, 0, 'message_id');
		$currentStatus = $db->query_result($result, 0, 'status');
		$currentTime = $db->query_result($result, 0, 'provider_status_at');
		$targetStatus = $map[$providerStatus];
		$eventTime = gmdate('Y-m-d H:i:s', (int) $timestamp);
		if ($currentTime && strtotime($eventTime . ' UTC') <= strtotime($currentTime . ' UTC')) {
			return;
		}
		$rank = array('QUEUED' => 0, 'SENDING' => 1, 'SENT' => 2, 'DELIVERED' => 3, 'READ' => 4);
		if ($currentStatus === 'FAILED' || $currentStatus === 'UNKNOWN' || $currentStatus === 'READ') {
			return;
		}
		if (isset($rank[$currentStatus], $rank[$targetStatus]) && $rank[$targetStatus] < $rank[$currentStatus]) {
			return;
		}
		if ($targetStatus === 'FAILED' && isset($rank[$currentStatus]) && $rank[$currentStatus] > $rank['SENT']) {
			return;
		}
		$error = $targetStatus === 'FAILED' ? 'Provider reported delivery failure (code ' . (int) $errorCode . ').' : null;
		$updated = $db->pquery(
			'UPDATE vtiger_whatsapp_messages SET status = ?, provider_status_at = ?, last_error = ?
				WHERE message_id = ? AND transport = ? AND (provider_status_at IS NULL OR provider_status_at < ?)',
			array($targetStatus, $eventTime, $error, $messageId, 'meta', $eventTime)
		);
		if (!$updated) {
			throw new RuntimeException('Provider status could not be recorded.');
		}
	}

	private static function processProviderPayload($payload) {
		$config = WhatsApp_MetaCloudApi::configuration();
		foreach ($payload['entry'] as $entry) {
			if (empty($entry['changes']) || !is_array($entry['changes'])) {
				continue;
			}
			foreach ($entry['changes'] as $change) {
				if (!isset($change['field']) || $change['field'] !== 'messages' || empty($change['value']) || !is_array($change['value'])) {
					continue;
				}
				$value = $change['value'];
				if (empty($value['metadata']['phone_number_id'])
					|| $value['metadata']['phone_number_id'] !== $config['phone_number_id']) {
					throw new InvalidArgumentException('Webhook does not match the configured WhatsApp phone number.');
				}
				if (!empty($value['messages']) && is_array($value['messages'])) {
					foreach ($value['messages'] as $providerMessage) {
						if (empty($providerMessage['id']) || empty($providerMessage['from'])
							|| !isset($providerMessage['type']) || $providerMessage['type'] !== 'text'
							|| !isset($providerMessage['text']['body'])) {
							throw new InvalidArgumentException('Only text messages with a phone sender are currently supported.');
						}
						$phone = self::normalizePhone($providerMessage['from']);
						$message = self::validateMessage($providerMessage['text']['body']);
						$matches = self::matchPhone($phone, false);
						$relatedModule = count($matches) === 1 ? $matches[0]['module'] : null;
						$relatedRecordId = count($matches) === 1 ? $matches[0]['recordid'] : null;
						$matchState = count($matches) === 1 ? 'matched' : (count($matches) ? 'ambiguous' : 'unmatched');
						$sourceEventId = 'meta-msg:' . hash('sha256', $providerMessage['id']);
						$id = self::newId();
						$insert = self::db()->pquery(
							"INSERT INTO vtiger_whatsapp_messages
								(message_id, source_event_id, direction, phone, message, status, related_module, related_recordid, match_state, created_by, transport, provider_message_id)
								VALUES (?, ?, 'IN', ?, ?, 'RECEIVED', ?, ?, ?, ?, 'meta', ?)
								ON DUPLICATE KEY UPDATE source_event_id = source_event_id",
							array($id, $sourceEventId, $phone, $message, $relatedModule, $relatedRecordId, $matchState, $config['system_user_id'], $providerMessage['id'])
						);
						if (!$insert) {
							throw new RuntimeException('Inbound provider message could not be saved.');
						}
					}
				}
				if (!empty($value['statuses']) && is_array($value['statuses'])) {
					foreach ($value['statuses'] as $status) {
						if (empty($status['id']) || empty($status['status']) || empty($status['timestamp'])) {
							throw new InvalidArgumentException('Provider status payload is incomplete.');
						}
						$errorCode = 0;
						if (!empty($status['errors'][0]['code'])) {
							$errorCode = (int) $status['errors'][0]['code'];
						}
						self::applyProviderStatus($status['id'], $status['status'], $status['timestamp'], $errorCode);
					}
				}
			}
		}
	}

	public static function workOneOutbound() {
		if (WhatsApp_MetaCloudApi::transport() !== 'meta' || !WhatsApp_MetaCloudApi::liveSendingEnabled()) {
			return false;
		}
		WhatsApp_MetaCloudApi::configuration();
		$token = self::newId();
		$db = self::db();
		$claimed = $db->pquery(
			"UPDATE vtiger_whatsapp_outbox SET status = 'processing', attempt_count = attempt_count + 1,
				lock_token = ?, locked_until = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 60 SECOND), updated_at = CURRENT_TIMESTAMP
				WHERE status IN ('queued', 'retry') AND available_at <= CURRENT_TIMESTAMP
				ORDER BY available_at, created_at LIMIT 1",
			array($token)
		);
		if (!$claimed || $db->getAffectedRowCount($claimed) === 0) {
			return false;
		}
		$result = $db->pquery(
			'SELECT outbox.outbox_id, outbox.message_id, outbox.attempt_count, message.phone, message.message
				FROM vtiger_whatsapp_outbox AS outbox
				INNER JOIN vtiger_whatsapp_messages AS message ON message.message_id = outbox.message_id
				WHERE outbox.lock_token = ?',
			array($token)
		);
		if (!$result || !$db->num_rows($result)) {
			throw new RuntimeException('Claimed outbound queue item could not be loaded.');
		}
		$job = $db->query_result_rowdata($result, 0);
		$db->pquery("UPDATE vtiger_whatsapp_messages SET status = 'SENDING' WHERE message_id = ? AND status = 'QUEUED'", array($job['message_id']));
		$result = WhatsApp_MetaCloudApi::sendText($job['phone'], $job['message']);
		$attempts = (int) $job['attempt_count'];
		if ($result['outcome'] === 'accepted') {
			$db->pquery(
				"UPDATE vtiger_whatsapp_outbox SET status = 'sent', provider_message_id = ?, lock_token = NULL, locked_until = NULL, updated_at = CURRENT_TIMESTAMP WHERE outbox_id = ? AND lock_token = ?",
				array($result['provider_message_id'], $job['outbox_id'], $token)
			);
			$db->pquery(
				"UPDATE vtiger_whatsapp_messages SET status = 'SENT', provider_message_id = ?, last_error = NULL WHERE message_id = ? AND status = 'SENDING'",
				array($result['provider_message_id'], $job['message_id'])
			);
		} elseif ($result['outcome'] === 'retry' && $attempts < 5) {
			$delay = max(1, min(3600, (int) $result['retry_after']));
			$db->pquery(
				"UPDATE vtiger_whatsapp_outbox SET status = 'retry', available_at = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL $delay SECOND),
					last_error = ?, lock_token = NULL, locked_until = NULL, updated_at = CURRENT_TIMESTAMP WHERE outbox_id = ? AND lock_token = ?",
				array($result['error'], $job['outbox_id'], $token)
			);
			$db->pquery("UPDATE vtiger_whatsapp_messages SET status = 'QUEUED', last_error = ? WHERE message_id = ?", array($result['error'], $job['message_id']));
		} else {
			$status = $result['outcome'] === 'unknown' ? 'unknown' : 'failed';
			$messageStatus = $status === 'unknown' ? 'UNKNOWN' : 'FAILED';
			$db->pquery(
				"UPDATE vtiger_whatsapp_outbox SET status = ?, last_error = ?, lock_token = NULL, locked_until = NULL, updated_at = CURRENT_TIMESTAMP WHERE outbox_id = ? AND lock_token = ?",
				array($status, $result['error'], $job['outbox_id'], $token)
			);
			$db->pquery("UPDATE vtiger_whatsapp_messages SET status = ?, last_error = ? WHERE message_id = ? AND status = 'SENDING'", array($messageStatus, $result['error'], $job['message_id']));
		}
		return true;
	}

	public static function workOneWebhook() {
		if (WhatsApp_MetaCloudApi::transport() !== 'meta') {
			return false;
		}
		$token = self::newId();
		$db = self::db();
		$claimed = $db->pquery(
			"UPDATE vtiger_whatsapp_events SET status = 'processing', attempt_count = attempt_count + 1,
				lock_token = ?, locked_until = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 60 SECOND)
				WHERE event_type = 'provider_webhook' AND status IN ('queued', 'retry') AND available_at <= CURRENT_TIMESTAMP
				ORDER BY available_at, created_at LIMIT 1",
			array($token)
		);
		if (!$claimed || $db->getAffectedRowCount($claimed) === 0) {
			return false;
		}
		$result = $db->pquery('SELECT event_id, payload, attempt_count FROM vtiger_whatsapp_events WHERE lock_token = ?', array($token));
		if (!$result || !$db->num_rows($result)) {
			throw new RuntimeException('Claimed provider webhook could not be loaded.');
		}
		$event = $db->query_result_rowdata($result, 0);
		try {
			$payload = json_decode($event['payload'], true);
			if (!is_array($payload)) {
				throw new InvalidArgumentException('Provider webhook JSON is invalid.');
			}
			self::processProviderPayload($payload);
			$updated = $db->pquery(
				"UPDATE vtiger_whatsapp_events SET status = 'processed', payload = '', last_error = NULL, lock_token = NULL, locked_until = NULL
					WHERE event_id = ? AND lock_token = ?",
				array($event['event_id'], $token)
			);
			if (!$updated) {
				throw new RuntimeException('Provider webhook was handled but could not be marked processed.');
			}
		} catch (Exception $exception) {
			$attempts = (int) $event['attempt_count'];
			$delay = min(3600, 15 * (2 ** min(8, max(0, $attempts - 1))));
			$status = $attempts >= 5 ? 'failed' : 'retry';
			$db->pquery(
				"UPDATE vtiger_whatsapp_events SET status = ?, payload = IF(? = 'failed', '', payload), available_at = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL $delay SECOND),
					last_error = ?, lock_token = NULL, locked_until = NULL WHERE event_id = ? AND lock_token = ?",
				array($status, $status, 'Webhook processing failed; inspect the server log for the event correlation ID.', $event['event_id'], $token)
			);
			error_log('WhatsApp webhook processing exception for event ' . hash('sha256', $event['event_id']) . ' (' . get_class($exception) . ').');
		}
		return true;
	}

	public static function recoverExpiredWork() {
		$db = self::db();
		$expiredOutbox = $db->pquery(
			"SELECT message_id, outbox_id FROM vtiger_whatsapp_outbox
				WHERE status = 'processing' AND locked_until < CURRENT_TIMESTAMP LIMIT 50",
			array()
		);
		for ($index = 0; $index < $db->num_rows($expiredOutbox); $index++) {
			$row = $db->query_result_rowdata($expiredOutbox, $index);
			$db->pquery(
				"UPDATE vtiger_whatsapp_outbox SET status = 'unknown', last_error = ?, lock_token = NULL, locked_until = NULL,
					updated_at = CURRENT_TIMESTAMP WHERE outbox_id = ? AND status = 'processing' AND locked_until < CURRENT_TIMESTAMP",
				array('Worker stopped after provider request; check provider delivery status before retrying.', $row['outbox_id'])
			);
			$db->pquery("UPDATE vtiger_whatsapp_messages SET status = 'UNKNOWN', last_error = ? WHERE message_id = ? AND status = 'SENDING'", array('Worker stopped after provider request; check provider delivery status before retrying.', $row['message_id']));
		}
		$db->pquery(
			"UPDATE vtiger_whatsapp_events SET status = 'retry', available_at = CURRENT_TIMESTAMP, lock_token = NULL, locked_until = NULL
				WHERE event_type = 'provider_webhook' AND status = 'processing' AND locked_until < CURRENT_TIMESTAMP",
			array()
		);
	}

	public static function runProviderWorkerBatch() {
		self::recoverExpiredWork();
		$worked = self::workOneOutbound();
		for ($count = 0; $count < 10; $count++) {
			$eventWorked = self::workOneWebhook();
			if (!$eventWorked) {
				break;
			}
			$worked = true;
		}
		return $worked;
	}

	public static function getMessages($limit = 50) {
		$db = self::db();
		$result = $db->pquery(
			'SELECT message_id, direction, phone, message, status, related_module, related_recordid, match_state,
					created_at, transport, last_error, created_by
				FROM vtiger_whatsapp_messages
				WHERE created_by = ? OR (transport = ? AND direction = ?)
				ORDER BY created_at DESC LIMIT ?',
			array(Users_Record_Model::getCurrentUserModel()->getId(), 'meta', 'IN', max(200, (int) $limit))
		);
		$messages = array();
		$currentUserId = (int) Users_Record_Model::getCurrentUserModel()->getId();
		for ($i = 0; $i < $db->num_rows($result) && count($messages) < (int) $limit; $i++) {
			$row = $db->query_result_rowdata($result, $i);
			if ((int) $row['created_by'] !== $currentUserId
				&& (!in_array($row['related_module'], array('Leads', 'Contacts'), true)
					|| (int) $row['related_recordid'] < 1
					|| !Users_Privileges_Model::isPermitted(
						$row['related_module'],
						'DetailView',
						(int) $row['related_recordid']
					))) {
				continue;
			}
			unset($row['created_by']);
			$messages[] = $row;
		}
		return $messages;
	}
}
