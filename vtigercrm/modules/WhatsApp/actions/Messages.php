<?php
require_once dirname(__DIR__) . '/WhatsApp.php';

class WhatsApp_Messages_Action extends Vtiger_BasicAjax_Action {
	public function requiresPermission(Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'DetailView'));
	}

	public function validateRequest(Vtiger_Request $request) {
		$request->validateReadAccess();
	}

	public function process(Vtiger_Request $request) {
		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: no-store, private');
		echo json_encode(array(
			'messages' => WhatsApp_Mock_Service::getMessages(),
			'csrf' => array(
				'name' => $GLOBALS['csrf']['input-name'],
				'token' => csrf_get_tokens()
			)
		));
	}
}
