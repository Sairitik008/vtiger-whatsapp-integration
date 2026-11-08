<?php
require_once dirname(__DIR__) . '/WhatsApp.php';

class WhatsApp_Send_Action extends Vtiger_Action_Controller {
	public function requiresPermission(Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'DetailView'));
	}

	public function validateRequest(Vtiger_Request $request) {
		return $request->validateWriteAccess();
	}

	public function process(Vtiger_Request $request) {
		$module = $request->get('sourceModule');
		$recordId = $request->get('sourceRecord');
		$phone = $request->get('phone');
		if ($module && $recordId) {
			$record = WhatsApp_Mock_Service::resolveRecord($module, $recordId);
			$phone = $record['phone'];
		}
		WhatsApp_Mock_Service::send(
			$phone,
			$request->get('message'),
			$request->get('request_id'),
			$module,
			$recordId,
			$request->get('consent_acknowledged') === '1'
		);
		$result = WhatsApp_MetaCloudApi::transport() === 'meta' ? 'queued' : 'sent';
		header('Location: index.php?module=WhatsApp&view=Index&result=' . $result);
	}
}
