<?php
require_once dirname(__DIR__) . '/WhatsApp.php';

class WhatsApp_Inbound_Action extends Vtiger_Action_Controller {
	public function requiresPermission(Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'DetailView'));
	}

	public function validateRequest(Vtiger_Request $request) {
		return $request->validateWriteAccess();
	}

	public function process(Vtiger_Request $request) {
		if (WhatsApp_MetaCloudApi::transport() !== 'mock') {
			throw new AppException('Inbound event simulation is disabled when the official provider transport is selected.');
		}
		$result = WhatsApp_Mock_Service::receive(
			$request->get('event_id'),
			$request->get('phone'),
			$request->get('message')
		);
		$query = $result['duplicate'] ? 'duplicate' : $result['match_state'];
		header('Location: index.php?module=WhatsApp&view=Index&result=' . rawurlencode($query));
	}
}
