-- Notification Triggers Database Schema
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

-- Create notification_templates table (if not exists)
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

-- Create notification_schedules table for complex scheduling
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

-- Create notification_recipients table for tracking
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

-- Insert default notification templates
INSERT IGNORE INTO `notification_templates` (`name`, `display_name`, `description`, `type`, `subject`, `content`, `sms_content`, `variables`, `category`, `is_system`) VALUES
('membership_expiry_30', 'Membership Expiry - 30 Days', 'Reminder sent 30 days before membership expiry', 'both', 
'Membership Expiry Reminder - {days} Days Remaining', 
'<h2>Dear {name},</h2><p>Your membership will expire in <strong>{days} days</strong> on <strong>{expiry_date}</strong>.</p><p>Please renew your membership to continue enjoying our services.</p><h3>Membership Details:</h3><ul><li>Member ID: {member_id}</li><li>Current Status: {status}</li><li>Expiry Date: {expiry_date}</li></ul><p>Contact us for renewal assistance.</p><p>Thank you!</p>',
'CSIMS Alert: Hi {first_name}, your membership expires in {days} days ({expiry_date}). Please renew to continue services.',
'["name", "first_name", "member_id", "status", "expiry_date", "days"]', 'membership', 1),

('membership_expiry_15', 'Membership Expiry - 15 Days', 'Reminder sent 15 days before membership expiry', 'both',
'URGENT: Membership Expiry Reminder - {days} Days Remaining',
'<h2>Dear {name},</h2><p><strong>URGENT REMINDER:</strong> Your membership will expire in <strong>{days} days</strong> on <strong>{expiry_date}</strong>.</p><p>Please renew your membership immediately to avoid service interruption.</p><h3>Membership Details:</h3><ul><li>Member ID: {member_id}</li><li>Current Status: {status}</li><li>Expiry Date: {expiry_date}</li></ul><p>Contact us immediately for renewal.</p>',
'CSIMS URGENT: Hi {first_name}, your membership expires in {days} days ({expiry_date}). Renew now to avoid interruption.',
'["name", "first_name", "member_id", "status", "expiry_date", "days"]', 'membership', 1),

('membership_expiry_7', 'Membership Expiry - 7 Days', 'Final reminder sent 7 days before membership expiry', 'both',
'FINAL NOTICE: Membership Expires in {days} Days',
'<h2>Dear {name},</h2><p><strong>FINAL NOTICE:</strong> Your membership will expire in <strong>{days} days</strong> on <strong>{expiry_date}</strong>.</p><p>This is your final reminder. Please renew immediately to avoid service suspension.</p><h3>Membership Details:</h3><ul><li>Member ID: {member_id}</li><li>Current Status: {status}</li><li>Expiry Date: {expiry_date}</li></ul><p>Contact us TODAY for immediate renewal.</p>',
'CSIMS FINAL NOTICE: Hi {first_name}, membership expires in {days} days ({expiry_date}). Renew TODAY!',
'["name", "first_name", "member_id", "status", "expiry_date", "days"]', 'membership', 1),

('payment_overdue', 'Payment Overdue Notice', 'Notice for overdue payments', 'both',
'Payment Overdue - Immediate Action Required',
'<h2>Dear {name},</h2><p><strong>URGENT:</strong> Your membership payment is overdue.</p><p>Your membership expired on <strong>{expiry_date}</strong>.</p><p>Please make your payment immediately to restore your membership.</p><h3>Account Details:</h3><ul><li>Member ID: {member_id}</li><li>Current Status: {status}</li><li>Expired Date: {expiry_date}</li></ul><p>Contact us immediately to resolve this issue.</p>',
'CSIMS: Hi {first_name}, your membership payment is overdue. Contact us immediately to avoid service interruption.',
'["name", "first_name", "member_id", "status", "expiry_date"]', 'payment', 1),

('welcome_new_member', 'Welcome New Member', 'Welcome message for new members', 'both',
'Welcome to {organization_name} - Your Membership is Active!',
'<h2>Dear {name},</h2><p><strong>Congratulations!</strong> Your membership application has been approved.</p><p>We are thrilled to welcome you to our community!</p><h3>Your Membership Details:</h3><ul><li>Member ID: {member_id}</li><li>Status: Active</li><li>Join Date: {current_date}</li></ul><p>If you have any questions, please do not hesitate to contact us.</p><p>Welcome aboard!</p>',
'Welcome to {organization_name}, {first_name}! Your membership is now active. We are excited to have you as part of our community!',
'["name", "first_name", "member_id", "current_date", "organization_name"]', 'welcome', 1),

('birthday_wishes', 'Birthday Wishes', 'Birthday wishes for members', 'email',
'Happy Birthday from {organization_name}!',
'<h2>Happy Birthday, {first_name}!</h2><p>On behalf of everyone at {organization_name}, we wish you a wonderful birthday!</p><p>Thank you for being a valued member of our community.</p><p>We hope you have a fantastic day!</p><p>Best wishes,<br>The {organization_name} Team</p>',
'Happy Birthday, {first_name}! Wishing you a wonderful day from all of us at {organization_name}!',
'["first_name", "organization_name"]', 'special', 1),

('weekly_report', 'Weekly Report', 'Weekly administrative report', 'email',
'{organization_name} Weekly Report - {current_date}',
'<h2>{organization_name} Weekly Report</h2><p><strong>Period:</strong> {report_period}</p><h3>Summary:</h3><ul><li>New Members: {new_members}</li><li>Expired Members: {expired_members}</li><li>Total Active Members: {active_members}</li></ul><p>This is an automated weekly report from {organization_name}.</p>',
NULL,
'["organization_name", "current_date", "report_period", "new_members", "expired_members", "active_members"]', 'reports', 1),

('monthly_report', 'Monthly Report', 'Monthly administrative report', 'email',
'{organization_name} Monthly Report - {current_date}',
'<h2>{organization_name} Monthly Report</h2><p><strong>Month:</strong> {report_period}</p><h3>Summary:</h3><ul><li>New Members: {new_members}</li><li>Expired Members: {expired_members}</li><li>Revenue: ${revenue}</li></ul><p>This is an automated monthly report from {organization_name}.</p>',
NULL,
'["organization_name", "current_date", "report_period", "new_members", "expired_members", "revenue"]', 'reports', 1);

-- Insert default notification triggers
INSERT IGNORE INTO `notification_triggers` (`name`, `description`, `trigger_type`, `trigger_condition`, `recipient_group`, `notification_template`, `schedule_pattern`, `next_run`, `status`, `email_enabled`, `sms_enabled`, `created_by`) VALUES
('Membership Expiry 30 Days', 'Send reminder 30 days before membership expiry', 'membership_expiry', '{"days_before_expiry": 30}', 'expiring_soon', 'membership_expiry_30', '{"type": "daily", "time": "09:00"}', DATE_ADD(NOW(), INTERVAL 1 DAY), 'active', 1, 1, 1),

('Membership Expiry 15 Days', 'Send reminder 15 days before membership expiry', 'membership_expiry', '{"days_before_expiry": 15}', 'expiring_soon', 'membership_expiry_15', '{"type": "daily", "time": "09:00"}', DATE_ADD(NOW(), INTERVAL 1 DAY), 'active', 1, 1, 1),

('Membership Expiry 7 Days', 'Send final reminder 7 days before membership expiry', 'membership_expiry', '{"days_before_expiry": 7}', 'expiring_soon', 'membership_expiry_7', '{"type": "daily", "time": "09:00"}', DATE_ADD(NOW(), INTERVAL 1 DAY), 'active', 1, 1, 1),

('Payment Overdue Reminder', 'Send reminder for overdue payments', 'payment_overdue', '{"status": "Expired"}', 'expired_members', 'payment_overdue', '{"type": "weekly", "day_of_week": 1, "time": "10:00"}', DATE_ADD(NOW(), INTERVAL 1 DAY), 'active', 1, 1, 1),

('Welcome New Members', 'Send welcome message to new members', 'welcome', '{"days_since_join": 1}', 'new_members', 'welcome_new_member', '{"type": "daily", "time": "11:00"}', DATE_ADD(NOW(), INTERVAL 1 DAY), 'active', 1, 1, 1),

('Weekly Reports', 'Send weekly reports to administrators', 'custom', '{}', 'admins', 'weekly_report', '{"type": "weekly", "day_of_week": 1, "time": "08:00"}', DATE_ADD(NOW(), INTERVAL 1 DAY), 'active', 1, 0, 1),

('Monthly Reports', 'Send monthly reports to administrators', 'custom', '{}', 'admins', 'monthly_report', '{"type": "monthly", "day_of_month": 1, "time": "08:00"}', DATE_ADD(NOW(), INTERVAL 1 DAY), 'active', 1, 0, 1);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_notification_triggers_next_run` ON `notification_triggers` (`next_run`);
CREATE INDEX IF NOT EXISTS `idx_notification_trigger_log_executed_at` ON `notification_trigger_log` (`executed_at`);
CREATE INDEX IF NOT EXISTS `idx_notification_recipients_status` ON `notification_recipients` (`email_status`, `sms_status`);

-- Add foreign key constraints if users table exists
-- ALTER TABLE `notification_triggers` ADD CONSTRAINT `fk_notification_triggers_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;
-- ALTER TABLE `notification_templates` ADD CONSTRAINT `fk_notification_templates_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;

-- Create view for trigger status overview
CREATE OR REPLACE VIEW `notification_trigger_status` AS
SELECT 
    nt.id,
    nt.name,
    nt.trigger_type,
    nt.status,
    nt.next_run,
    nt.last_run,
    nt.run_count,
    CASE 
        WHEN nt.next_run <= NOW() AND nt.status = 'active' THEN 'due'
        WHEN nt.status = 'active' THEN 'scheduled'
        ELSE nt.status
    END as current_status,
    COUNT(ntl.id) as total_executions,
    SUM(CASE WHEN ntl.execution_status = 'completed' THEN 1 ELSE 0 END) as successful_executions,
    SUM(CASE WHEN ntl.execution_status = 'failed' THEN 1 ELSE 0 END) as failed_executions,
    MAX(ntl.executed_at) as last_execution
FROM notification_triggers nt
LEFT JOIN notification_trigger_log ntl ON nt.id = ntl.trigger_id
GROUP BY nt.id, nt.name, nt.trigger_type, nt.status, nt.next_run, nt.last_run, nt.run_count;

-- Create stored procedure for trigger cleanup
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS CleanupNotificationLogs(IN days_to_keep INT)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Delete old notification recipient records
    DELETE nr FROM notification_recipients nr
    INNER JOIN notification_trigger_log ntl ON nr.trigger_log_id = ntl.id
    WHERE ntl.executed_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
    
    -- Delete old notification trigger logs
    DELETE FROM notification_trigger_log 
    WHERE executed_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
    
    COMMIT;
END //
DELIMITER ;

-- Create event for automatic cleanup (runs weekly)
-- Note: Events require EVENT scheduler to be enabled
-- SET GLOBAL event_scheduler = ON;

/*
CREATE EVENT IF NOT EXISTS cleanup_notification_logs
ON SCHEDULE EVERY 1 WEEK
STARTS CURRENT_TIMESTAMP
DO
  CALL CleanupNotificationLogs(90);
*/

-- Insert sample data for testing (optional)
-- This can be removed in production
/*
INSERT INTO `notification_triggers` (`name`, `description`, `trigger_type`, `trigger_condition`, `recipient_group`, `notification_template`, `schedule_pattern`, `next_run`, `status`, `email_enabled`, `sms_enabled`, `created_by`) VALUES
('Test Birthday Reminder', 'Test trigger for birthday reminders', 'birthday', '{"month": "current"}', 'active_members', 'birthday_wishes', '{"type": "daily", "time": "09:00"}', DATE_ADD(NOW(), INTERVAL 1 HOUR), 'inactive', 1, 0, 1);
*/