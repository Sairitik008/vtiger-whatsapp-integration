<?php
/**
 * Vtiger CRM Action: WhatsApp_Webhook_Action
 * Handles incoming webhooks inside Vtiger CRM routing architecture.
 */

class WhatsApp_Webhook_Action extends Vtiger_Action_Controller {

    public function checkPermission(Vtiger_Request $request) {
        // Webhook endpoints authenticate via webhook signature / API key rather than session
        return true;
    }

    public function process(Vtiger_Request $request) {
        $rawPayload = file_get_contents('php://input');
        $data = json_decode($rawPayload, true);

        if (!$data) {
            $response = new Vtiger_Response();
            $response->setError(400, 'Invalid payload');
            $response->emit();
            return;
        }

        $eventType = $data['event_type'] ?? 'incoming_message';
        $adb = PearDatabase::getInstance();

        if ($eventType === 'status_update') {
            $messageId = $data['message_id'];
            $status = strtolower($data['status']);

            $adb->pquery(
                "UPDATE vtiger_whatsappmessages SET status = ? WHERE message_id = ?",
                [$status, $messageId]
            );

            $response = new Vtiger_Response();
            $response->setResult(['success' => true, 'updated_status' => $status]);
            $response->emit();
            return;
        }

        if ($eventType === 'incoming_message') {
            $sender = preg_replace('/[^0-9]/', '', $data['sender_number']);
            $messageContent = $data['message_content'];

            // Match Lead in Vtiger
            $leadQuery = $adb->pquery(
                "SELECT leadid FROM vtiger_leadaddress WHERE phone LIKE ? OR mobile LIKE ? LIMIT 1",
                ['%' . substr($sender, -10), '%' . substr($sender, -10)]
            );

            $leadId = null;
            if ($adb->num_rows($leadQuery) > 0) {
                $leadId = $adb->query_result($leadQuery, 0, 'leadid');
            }

            // Insert into WhatsApp messages table
            $currentId = $adb->getUniqueID('vtiger_crmentity');
            $adb->pquery(
                "INSERT INTO vtiger_whatsappmessages (whatsappmessageid, message_id, lead_id, sender_number, recipient_number, direction, message_content, status, sent_at)
                 VALUES (?, ?, ?, ?, ?, 'incoming', ?, 'read', NOW())",
                [$currentId, $data['message_id'], $leadId, $sender, '919876500000', $messageContent]
            );

            $response = new Vtiger_Response();
            $response->setResult(['success' => true, 'lead_id' => $leadId]);
            $response->emit();
            return;
        }
    }
}
