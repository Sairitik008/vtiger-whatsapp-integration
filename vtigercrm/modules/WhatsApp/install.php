<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

ini_set('display_errors', '1');
error_reporting(E_ALL);
register_shutdown_function(function () {
	$error = error_get_last();
	if ($error && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
		fwrite(STDERR, $error['message'] . ' in ' . $error['file'] . ':' . $error['line'] . PHP_EOL);
	}
});

chdir(dirname(__FILE__) . '/../..');
require_once 'vendor/autoload.php';
require_once 'include/utils/utils.php';
require_once 'vtlib/Vtiger/Module.php';
require_once 'includes/main/WebUI.php';

global $adb;

$module = Vtiger_Module::getInstance('WhatsApp');
if (!$module) {
	$module = new Vtiger_Module();
	$module->name = 'WhatsApp';
	$module->label = 'WhatsApp';
	$module->parent = 'Tools';
	$module->isentitytype = false;
	$module->save();
}

$adb->pquery(
	"CREATE TABLE IF NOT EXISTS vtiger_whatsapp_messages (
		message_id CHAR(32) NOT NULL,
		source_event_id VARCHAR(100) DEFAULT NULL,
		direction VARCHAR(3) NOT NULL,
		phone VARCHAR(20) NOT NULL,
		message TEXT NOT NULL,
		status VARCHAR(20) NOT NULL,
		related_module VARCHAR(20) DEFAULT NULL,
		related_recordid INT(19) DEFAULT NULL,
		match_state VARCHAR(20) NOT NULL,
		created_by INT(19) NOT NULL,
		created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at TIMESTAMP NULL DEFAULT NULL,
		PRIMARY KEY (message_id),
		UNIQUE KEY whatsapp_source_event (source_event_id),
		KEY whatsapp_phone (phone),
		KEY whatsapp_related_record (related_module, related_recordid)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
	array()
);
$messageColumns = array(
	'transport' => "VARCHAR(10) NOT NULL DEFAULT 'mock'",
	'provider_message_id' => 'VARCHAR(255) DEFAULT NULL',
	'last_error' => 'VARCHAR(500) DEFAULT NULL',
	'consent_acknowledged' => 'TINYINT(1) NOT NULL DEFAULT 0',
	'consent_acknowledged_at' => 'TIMESTAMP NULL DEFAULT NULL',
	'provider_status_at' => 'DATETIME DEFAULT NULL'
);
foreach ($messageColumns as $column => $definition) {
	$existing = $adb->pquery('SHOW COLUMNS FROM vtiger_whatsapp_messages LIKE ?', array($column));
	if (!$existing || !$adb->num_rows($existing)) {
		$adb->pquery("ALTER TABLE vtiger_whatsapp_messages ADD COLUMN $column $definition", array());
	}
}
$adb->pquery(
	"CREATE TABLE IF NOT EXISTS vtiger_whatsapp_outbox (
		outbox_id CHAR(32) NOT NULL,
		message_id CHAR(32) NOT NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'queued',
		attempt_count INT NOT NULL DEFAULT 0,
		available_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		locked_until TIMESTAMP NULL DEFAULT NULL,
		lock_token CHAR(32) DEFAULT NULL,
		provider_message_id VARCHAR(255) DEFAULT NULL,
		last_error VARCHAR(500) DEFAULT NULL,
		created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at TIMESTAMP NULL DEFAULT NULL,
		PRIMARY KEY (outbox_id),
		UNIQUE KEY whatsapp_outbox_message (message_id),
		KEY whatsapp_outbox_ready (status, available_at),
		KEY whatsapp_outbox_lease (status, locked_until)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
	array()
);
$adb->pquery(
	"CREATE TABLE IF NOT EXISTS vtiger_whatsapp_events (
		event_id VARCHAR(120) NOT NULL,
		message_id CHAR(32) DEFAULT NULL,
		event_type VARCHAR(30) NOT NULL,
		payload TEXT NOT NULL,
		status VARCHAR(20) NOT NULL,
		created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (event_id),
		KEY whatsapp_event_message (message_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
	array()
);
$eventColumns = array(
	'attempt_count' => 'INT NOT NULL DEFAULT 0',
	'available_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
	'locked_until' => 'TIMESTAMP NULL DEFAULT NULL',
	'lock_token' => 'CHAR(32) DEFAULT NULL',
	'last_error' => 'VARCHAR(500) DEFAULT NULL'
);
foreach ($eventColumns as $column => $definition) {
	$existing = $adb->pquery('SHOW COLUMNS FROM vtiger_whatsapp_events LIKE ?', array($column));
	if (!$existing || !$adb->num_rows($existing)) {
		$adb->pquery("ALTER TABLE vtiger_whatsapp_events ADD COLUMN $column $definition", array());
	}
}

$actions = $adb->pquery(
	"SELECT actionid FROM vtiger_actionmapping WHERE actionname IN ('index', 'DetailView')",
	array()
);
for ($actionIndex = 0; $actionIndex < $adb->num_rows($actions); $actionIndex++) {
	$actionId = $adb->query_result($actions, $actionIndex, 'actionid');
	$adb->pquery(
		'INSERT INTO vtiger_profile2standardpermissions (profileid, tabid, operation, permissions)
			SELECT profile.profileid, ?, ?, 0 FROM vtiger_profile AS profile
			LEFT JOIN vtiger_profile2standardpermissions AS permission
				ON permission.profileid = profile.profileid AND permission.tabid = ? AND permission.operation = ?
			WHERE permission.profileid IS NULL',
		array($module->id, $actionId, $module->id, $actionId)
	);
}
foreach (array('Leads', 'Contacts') as $parentName) {
	$parent = Vtiger_Module::getInstance($parentName);
	if ($parent) {
		$parent->deleteLink('DETAILVIEWBASIC', 'WhatsApp Mock');
		$parent->addLink(
			'DETAILVIEWBASIC',
			'WhatsApp Mock',
			'index.php?module=WhatsApp&view=Index&sourceModule=$MODULE$&sourceRecord=$RECORD$'
		);
	}
}

echo "WhatsApp local mock module is installed.\n";
