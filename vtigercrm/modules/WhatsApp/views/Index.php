<?php
require_once dirname(__DIR__) . '/WhatsApp.php';

class WhatsApp_Index_View extends Vtiger_Index_View {
	public function checkPermission(Vtiger_Request $request) {
		if (!Users_Privileges_Model::isPermitted('WhatsApp', 'DetailView')) {
			throw new AppException(vtranslate('LBL_PERMISSION_DENIED'));
		}
	}

	public function process(Vtiger_Request $request) {
		$viewer = $this->getViewer($request);
		$sourceModule = $request->get('sourceModule');
		$sourceRecord = $request->get('sourceRecord');
		$record = null;

		if ($sourceModule && $sourceRecord) {
			$record = WhatsApp_Mock_Service::resolveRecord($sourceModule, $sourceRecord);
		}

		$viewer->assign('MODULE', 'WhatsApp');
		$viewer->assign('MESSAGES', WhatsApp_Mock_Service::getMessages());
		$viewer->assign('TRANSPORT', WhatsApp_MetaCloudApi::transport());
		$viewer->assign('SOURCE_RECORD', $record);
		$viewer->assign('RESULT', $request->get('result'));
		$viewer->assign('EVENT_TOKEN', WhatsApp_Mock_Service::newId());
		$viewer->assign('SEND_TOKEN', WhatsApp_Mock_Service::newId());
		$viewer->assign('OUTBOUND_TRANSITIONS', array('DELIVERED', 'FAILED'));
		$viewer->view('Index.tpl', 'WhatsApp');
	}
}
