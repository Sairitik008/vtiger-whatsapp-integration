<?php
require_once dirname(__DIR__) . '/WhatsApp.php';

class WhatsApp_Status_Action extends Vtiger_Action_Controller {
	public function requiresPermission(Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'DetailView'));
	}

	public function validateRequest(Vtiger_Request $request) {
		return $request->validateWriteAccess();
	}

	public function process(Vtiger_Request $request) {
		$result = WhatsApp_Mock_Service::updateStatus($request->get('message_id'), strtoupper($request->get('status')));
		$query = $result['duplicate'] ? 'duplicate' : 'status_updated';
		header('Location: index.php?module=WhatsApp&view=Index&result=' . rawurlencode($query));
	}
}
