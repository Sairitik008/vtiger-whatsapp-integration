-- ====================================================================
-- Open-Source WhatsApp Integration with Vtiger CRM
-- Database Schema: vtiger_whatsapp_crm
-- Optimized for: Lead/Contact Mapping, Status Tracking, Pipeline Kanban
-- ====================================================================

CREATE DATABASE IF NOT EXISTS `vtiger_whatsapp_crm` 
DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `vtiger_whatsapp_crm`;

-- 1. Pipeline Stages Table
DROP TABLE IF EXISTS `pipeline_stages`;
CREATE TABLE `pipeline_stages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `stage_key` VARCHAR(50) NOT NULL UNIQUE,
    `stage_name` VARCHAR(100) NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `color_code` VARCHAR(20) DEFAULT '#4f46e5',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Leads and Contacts Table
DROP TABLE IF EXISTS `leads_contacts`;
CREATE TABLE `leads_contacts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) DEFAULT NULL,
    `phone` VARCHAR(20) NOT NULL,              -- Raw input phone
    `normalized_phone` VARCHAR(20) NOT NULL,   -- Standardized digits only (E.164, e.g. 919876543210)
    `record_type` ENUM('Lead', 'Contact') DEFAULT 'Lead',
    `company` VARCHAR(150) DEFAULT NULL,
    `pipeline_stage_id` INT NOT NULL DEFAULT 1,
    `next_action` VARCHAR(255) DEFAULT 'Initiate WhatsApp outreach',
    `last_contacted_at` DATETIME DEFAULT NULL,
    `last_message_text` TEXT DEFAULT NULL,
    `last_message_status` ENUM('scheduled', 'sent', 'delivered', 'read', 'failed') DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_normalized_phone` (`normalized_phone`),
    INDEX `idx_pipeline_stage` (`pipeline_stage_id`),
    CONSTRAINT `fk_lead_stage` FOREIGN KEY (`pipeline_stage_id`) REFERENCES `pipeline_stages` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 3. WhatsApp Messages Table (Two-way Conversation History & Status Lifecycle)
DROP TABLE IF EXISTS `whatsapp_messages`;
CREATE TABLE `whatsapp_messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `message_id` VARCHAR(100) NOT NULL UNIQUE, -- Unique Gateway/WhatsApp reference ID
    `lead_id` INT NULL,                         -- Relational link to Lead/Contact (NULL if unassigned)
    `sender_number` VARCHAR(30) NOT NULL,
    `recipient_number` VARCHAR(30) NOT NULL,
    `direction` ENUM('outgoing', 'incoming') NOT NULL,
    `message_content` TEXT NOT NULL,
    `message_type` VARCHAR(30) DEFAULT 'text',
    `status` ENUM('scheduled', 'sent', 'delivered', 'read', 'failed') NOT NULL DEFAULT 'sent',
    `failure_reason` VARCHAR(255) DEFAULT NULL,
    `sent_at` DATETIME NULL,
    `scheduled_at` DATETIME NULL DEFAULT NULL,
    `delivered_at` DATETIME DEFAULT NULL,
    `read_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_message_id` (`message_id`),
    INDEX `idx_lead_id` (`lead_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`),
    CONSTRAINT `fk_message_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads_contacts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Webhook Ingestion Logs (For audit trail & developer inspection)
DROP TABLE IF EXISTS `webhook_logs`;
CREATE TABLE `webhook_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `event_type` VARCHAR(50) NOT NULL,         -- 'incoming_message', 'status_update', etc.
    `message_id` VARCHAR(100) DEFAULT NULL,
    `sender_number` VARCHAR(30) DEFAULT NULL,
    `payload_json` LONGTEXT NOT NULL,
    `ip_address` VARCHAR(50) DEFAULT NULL,
    `processed_status` ENUM('success', 'failed', 'unmapped') DEFAULT 'success',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_log_message_id` (`message_id`),
    INDEX `idx_event_type` (`event_type`)
) ENGINE=InnoDB;

-- ====================================================================
-- SEED INITIAL DATA (Default Stages & Realistic Sample Leads)
-- ====================================================================

INSERT INTO `pipeline_stages` (`id`, `stage_key`, `stage_name`, `sort_order`, `color_code`) VALUES
(1, 'new_lead', 'New Lead', 1, '#6366f1'),
(2, 'whatsapp_contacted', 'WhatsApp Contacted', 2, '#0284c7'),
(3, 'conversation_started', 'Conversation Started', 3, '#0d9488'),
(4, 'qualified', 'Qualified', 4, '#ca8a04'),
(5, 'follow_up', 'Follow-up', 5, '#ea580c'),
(6, 'opportunity', 'Opportunity', 6, '#9333ea'),
(7, 'won', 'Closed Won', 7, '#16a34a'),
(8, 'lost', 'Closed Lost', 8, '#dc2626');

-- Insert realistic seed Leads
INSERT INTO `leads_contacts` (`first_name`, `last_name`, `email`, `phone`, `normalized_phone`, `record_type`, `company`, `pipeline_stage_id`, `next_action`) VALUES
('Rahul', 'Sharma', 'rahul.sharma@example.com', '+91 98230 12345', '919823012345', 'Lead', 'Apex Logistics', 1, 'Send introductory WhatsApp brochure'),
('Priya', 'Verma', 'priya.verma@innovate.co', '+91 98231 67890', '919823167890', 'Contact', 'Innovatech Solutions', 2, 'Wait for product catalog acknowledgment'),
('Amit', 'Patel', 'amit.patel@patelmotors.in', '+91 98232 45678', '919823245678', 'Lead', 'Patel Motors', 3, 'Confirm demo schedule'),
('Sneha', 'Deshmukh', 'sneha.d@nagpurfabrics.com', '+91 98233 11223', '919823311223', 'Contact', 'Nagpur Textiles', 4, 'Send custom enterprise quote');
