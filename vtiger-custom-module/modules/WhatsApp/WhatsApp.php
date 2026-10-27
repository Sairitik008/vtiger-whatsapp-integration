<?php
/**
 * Vtiger CRM Custom Module: WhatsApp
 * Class WhatsApp extends CRMEntity
 */

include_once 'modules/Vtiger/CRMEntity.php';

class WhatsApp extends CRMEntity {
    public $table_name = 'vtiger_whatsappmessages';
    public $table_index = 'whatsappmessageid';
    public $column_fields = [];

    /**
     * Mandatory table list for Vtiger CRMEntity
     */
    public $tab_name = ['vtiger_crmentity', 'vtiger_whatsappmessages'];
    public $tab_name_index = [
        'vtiger_crmentity'         => 'crmid',
        'vtiger_whatsappmessages'  => 'whatsappmessageid'
    ];

    public $list_fields = [
        'Message ID'        => ['whatsappmessages', 'message_id'],
        'Direction'         => ['whatsappmessages', 'direction'],
        'Recipient'         => ['whatsappmessages', 'recipient_number'],
        'Status'            => ['whatsappmessages', 'status'],
        'Sent At'           => ['whatsappmessages', 'sent_at']
    ];

    public $list_fields_name = [
        'Message ID' => 'message_id',
        'Direction'  => 'direction',
        'Recipient'  => 'recipient_number',
        'Status'     => 'status',
        'Sent At'    => 'sent_at'
    ];

    public $default_order_by = 'sent_at';
    public $default_sort_order = 'DESC';

    /**
     * Invoked when module is installed or updated in Vtiger
     */
    public function vtlib_handler($moduleName, $eventType) {
        if ($eventType === 'module.postinstall') {
            $this->addLinks($moduleName);
        }
    }

    /**
     * Register action links on Leads and Contacts modules
     */
    private function addLinks($moduleName) {
        $leadModule = Vtiger_Module::getInstance('Leads');
        if ($leadModule) {
            $leadModule->addLink(
                'DETAILVIEWBASIC',
                'Send WhatsApp Message',
                'javascript:Vtiger_WhatsApp_Js.openSendModal();',
                'modules/WhatsApp/resources/icon.png'
            );
        }

        $contactModule = Vtiger_Module::getInstance('Contacts');
        if ($contactModule) {
            $contactModule->addLink(
                'DETAILVIEWBASIC',
                'Send WhatsApp Message',
                'javascript:Vtiger_WhatsApp_Js.openSendModal();',
                'modules/WhatsApp/resources/icon.png'
            );
        }
    }
}
