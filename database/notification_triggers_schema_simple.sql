-- Notification Triggers Database Schema (Simplified)
-- This file creates the necessary tables for automated notification triggers

-- Create notification_triggers table
CREATE TABLE IF NOT EXISTS `notification_triggers` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `description` text,
    `trigger_type` enum('membership_expiry','payment_overdue','welcome','birthday','custom') NOT NULL,
    `trigger_condition` json DEFAULT NULL COMMENT 'JSON object with trigger conditions',
    `recipient_group` varchar(100) NOT NULL COMMENT 'Recipient group key from config',
    `notification_template` varchar(100) NOT NULL COMMENT 'Template key from config',
    `schedule_pattern` json NOT NULL COMMENT 'JSON object with schedule configuration',
    `next_run` datetime NOT NULL,
    `last_run` datetime DEFAULT NULL,
    `run_count` int(11) DEFAULT 0,
    `status` enum('active','inactive','paused') DEFAULT 'active',
    `email_enabled` tinyint(1) DEFAULT 1,
    `sms_enabled` tinyint(1) DEFAULT 0,
    `created_by` int(11) NOT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status_next_run` (`status`, `next_run`),
    KEY `idx_trigger_type` (`trigger_type`),
    KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create notification_trigger_log table
CREATE TABLE IF NOT EXISTS `notification_trigger_log` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `trigger_id` int(11) NOT NULL,
    `execution_status` enum('started','completed','failed','skipped') NOT NULL,
    `message` text,
    `recipients_count` int(11) DEFAULT 0,
    `sent_count` int(11) DEFAULT 0,
    `failed_count` int(11) DEFAULT 0,
    `execution_time` decimal(10,3) DEFAULT NULL COMMENT 'Execution time in seconds',
    `executed_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_trigger_id` (`trigger_id`),
    KEY `idx_execution_status` (`execution_status`),
    KEY `idx_executed_at` (`executed_at`),
    FOREIGN KEY (`trigger_id`) REFERENCES `notification_triggers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create notification_templates table
CREATE TABLE IF NOT EXISTS `notification_templates` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL UNIQUE,
    `display_name` varchar(255) NOT NULL,
    `description` text,
    `type` enum('email','sms','both') DEFAULT 'email',
    `subject` varchar(500) DEFAULT NULL,
    `content` longtext NOT NULL,
    `sms_content` text DEFAULT NULL,
    `variables` json DEFAULT NULL COMMENT 'Available template variables',
    `category` varchar(50) DEFAULT 'general',
    `is_system` tinyint(1) DEFAULT 0 COMMENT 'System templates cannot be deleted',
    `status` enum('active','inactive') DEFAULT 'active',
    `created_by` int(11) DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_name` (`name`),
    KEY `idx_type` (`type`),
    KEY `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create notification_schedules table
CREATE TABLE IF NOT EXISTS `notification_schedules` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `trigger_id` int(11) NOT NULL,
    `schedule_type` enum('once','daily','weekly','monthly','yearly','custom') NOT NULL,
    `schedule_data` json NOT NULL COMMENT 'Schedule configuration data',
    `timezone` varchar(50) DEFAULT 'UTC',
    `start_date` date DEFAULT NULL,
    `end_date` date DEFAULT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_trigger_id` (`trigger_id`),
    KEY `idx_schedule_type` (`schedule_type`),
    FOREIGN KEY (`trigger_id`) REFERENCES `notification_triggers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create notification_recipients table
CREATE TABLE IF NOT EXISTS `notification_recipients` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `trigger_log_id` int(11) NOT NULL,
    `recipient_type` enum('member','user','admin','custom') NOT NULL,
    `recipient_id` int(11) DEFAULT NULL,
    `recipient_email` varchar(255) DEFAULT NULL,
    `recipient_phone` varchar(20) DEFAULT NULL,
    `recipient_name` varchar(255) DEFAULT NULL,
    `email_sent` tinyint(1) DEFAULT 0,
    `sms_sent` tinyint(1) DEFAULT 0,
    `email_status` enum('pending','sent','failed','bounced') DEFAULT 'pending',
    `sms_status` enum('pending','sent','failed','delivered') DEFAULT 'pending',
    `sent_at` timestamp NULL DEFAULT NULL,
    `error_message` text DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_trigger_log_id` (`trigger_log_id`),
    KEY `idx_recipient_type_id` (`recipient_type`, `recipient_id`),
    KEY `idx_email_status` (`email_status`),
    KEY `idx_sms_status` (`sms_status`),
    FOREIGN KEY (`trigger_log_id`) REFERENCES `notification_trigger_log`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_notification_triggers_next_run` ON `notification_triggers` (`next_run`);
CREATE INDEX IF NOT EXISTS `idx_notification_trigger_log_executed_at` ON `notification_trigger_log` (`executed_at`);
CREATE INDEX IF NOT EXISTS `idx_notification_recipients_status` ON `notification_recipients` (`email_status`, `sms_status`);