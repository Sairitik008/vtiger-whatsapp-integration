<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = Database::getConnection();
$pdo->exec("UPDATE leads_contacts SET last_message_text = REPLACE(last_message_text, '👋 ', '')");
$pdo->exec("UPDATE leads_contacts SET last_message_text = REPLACE(last_message_text, '👋', '')");
$pdo->exec("UPDATE whatsapp_messages SET message_content = REPLACE(message_content, '👋 ', '')");
$pdo->exec("UPDATE whatsapp_messages SET message_content = REPLACE(message_content, '👋', '')");
echo "DB messages cleaned\n";
