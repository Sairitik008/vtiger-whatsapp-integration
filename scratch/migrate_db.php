<?php
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = Database::getConnection();

    // 1. Check whatsapp_messages columns
    $cols = $pdo->query('SHOW COLUMNS FROM whatsapp_messages')->fetchAll(PDO::FETCH_COLUMN);
    echo "Current whatsapp_messages columns: " . implode(', ', $cols) . "\n";

    if (!in_array('scheduled_at', $cols)) {
        $pdo->exec("ALTER TABLE whatsapp_messages ADD COLUMN scheduled_at DATETIME NULL DEFAULT NULL AFTER sent_at");
        echo "SUCCESS: Added scheduled_at column to whatsapp_messages.\n";
    } else {
        echo "Column scheduled_at already exists.\n";
    }

    // 2. Make sent_at nullable in whatsapp_messages for scheduled messages
    $pdo->exec("ALTER TABLE whatsapp_messages MODIFY COLUMN sent_at DATETIME NULL DEFAULT NULL");
    echo "SUCCESS: Made sent_at nullable in whatsapp_messages.\n";

    // 3. Modify status enum in whatsapp_messages to include 'scheduled'
    $pdo->exec("ALTER TABLE whatsapp_messages MODIFY COLUMN status ENUM('scheduled', 'sent', 'delivered', 'read', 'failed') NOT NULL DEFAULT 'sent'");
    echo "SUCCESS: Updated status ENUM in whatsapp_messages.\n";

    // 4. Modify last_message_status enum in leads_contacts to include 'scheduled'
    $pdo->exec("ALTER TABLE leads_contacts MODIFY COLUMN last_message_status ENUM('scheduled', 'sent', 'delivered', 'read', 'failed') DEFAULT NULL");
    echo "SUCCESS: Updated last_message_status ENUM in leads_contacts.\n";

    echo "ALL MIGRATIONS APPLIED SUCCESSFULLY!\n";
} catch (Exception $e) {
    echo "MIGRATION ERROR: " . $e->getMessage() . "\n";
}
