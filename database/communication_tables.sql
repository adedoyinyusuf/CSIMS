-- SQL script to create tables for the Member Communication Portal

-- Create announcements table
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    priority ENUM('normal', 'medium', 'high') DEFAULT 'normal',
    target_audience ENUM('all', 'active', 'expired') DEFAULT 'all',
    expiry_date DATETIME NULL,
    created_by INT NOT NULL,
    status ENUM('active', 'inactive', 'expired') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_expiry (expiry_date),
    INDEX idx_created_by (created_by)
);

-- Create message_templates table
CREATE TABLE IF NOT EXISTS message_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    message_type ENUM('general', 'reminder', 'announcement', 'welcome', 'renewal') DEFAULT 'general',
    variables TEXT NULL COMMENT 'JSON array of available variables',
    created_by INT NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_message_type (message_type)
);

-- Create scheduled_messages table
CREATE TABLE IF NOT EXISTS scheduled_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    recipient_ids TEXT NOT NULL COMMENT 'JSON array of recipient IDs',
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    priority ENUM('normal', 'medium', 'high') DEFAULT 'normal',
    message_type ENUM('general', 'reminder', 'announcement', 'welcome', 'renewal') DEFAULT 'general',
    scheduled_at DATETIME NOT NULL,
    status ENUM('pending', 'sent', 'failed', 'cancelled') DEFAULT 'pending',
    sent_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_scheduled_at (scheduled_at)
);

-- Create message_activity_log table
CREATE TABLE IF NOT EXISTS message_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    action ENUM('sent', 'read', 'deleted', 'replied') NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_message_id (message_id),
    INDEX idx_user_id (user_id),
    INDEX idx_action (action)
);

-- Add missing columns to existing messages table if they don't exist
ALTER TABLE messages 
ADD COLUMN IF NOT EXISTS priority ENUM('normal', 'medium', 'high') DEFAULT 'normal' AFTER message,
ADD COLUMN IF NOT EXISTS message_type ENUM('general', 'reminder', 'announcement', 'welcome', 'renewal') DEFAULT 'general' AFTER priority,
ADD COLUMN IF NOT EXISTS read_at TIMESTAMP NULL AFTER is_read,
ADD COLUMN IF NOT EXISTS status ENUM('sent', 'delivered', 'read', 'failed') DEFAULT 'sent' AFTER message_type;

-- Add indexes to messages table for better performance
ALTER TABLE messages 
ADD INDEX IF NOT EXISTS idx_priority (priority),
ADD INDEX IF NOT EXISTS idx_message_type (message_type),
ADD INDEX IF NOT EXISTS idx_status (status),
ADD INDEX IF NOT EXISTS idx_read_at (read_at);

-- Insert some default message templates
INSERT IGNORE INTO message_templates (name, subject, content, message_type, variables, created_by) VALUES
('Welcome Message', 'Welcome to Our Community!', 
'Dear {first_name} {last_name},\n\nWelcome to our community! We are excited to have you as a member.\n\nYour membership details:\n- Member ID: {member_id}\n- Membership Type: {membership_type}\n- Expiry Date: {expiry_date}\n\nIf you have any questions, please don\'t hesitate to contact us.\n\nBest regards,\nThe Management Team', 
'welcome', 
'["first_name", "last_name", "member_id", "membership_type", "expiry_date"]', 
1),

('Membership Renewal Reminder', 'Membership Renewal Reminder', 
'Dear {first_name} {last_name},\n\nThis is a friendly reminder that your membership will expire on {expiry_date}.\n\nTo continue enjoying our services, please renew your membership before the expiry date.\n\nYou can renew your membership by:\n- Visiting our office\n- Calling us at our contact number\n- Using our online portal\n\nThank you for being a valued member.\n\nBest regards,\nThe Management Team', 
'renewal', 
'["first_name", "last_name", "expiry_date"]', 
1),

('General Announcement', 'Important Announcement', 
'Dear Members,\n\n{announcement_content}\n\nFor more information, please contact us.\n\nBest regards,\nThe Management Team', 
'announcement', 
'["announcement_content"]', 
1),

('Payment Reminder', 'Payment Reminder', 
'Dear {first_name} {last_name},\n\nThis is a reminder that your payment of {amount} is due on {due_date}.\n\nPlease make the payment at your earliest convenience to avoid any inconvenience.\n\nThank you for your cooperation.\n\nBest regards,\nThe Management Team', 
'reminder', 
'["first_name", "last_name", "amount", "due_date"]', 
1);

-- Create notification_log table for tracking sent notifications
CREATE TABLE IF NOT EXISTS notification_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_type ENUM('member', 'admin', 'all') NOT NULL,
    recipient_id INT NULL COMMENT 'NULL for broadcast messages',
    notification_type ENUM('email', 'sms', 'both') NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    sent_by INT NOT NULL,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    error_message TEXT NULL,
    INDEX idx_recipient (recipient_type, recipient_id),
    INDEX idx_status (status),
    INDEX idx_sent_by (sent_by)
);

-- Create email_queue table for managing email sending
CREATE TABLE IF NOT EXISTS email_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    to_email VARCHAR(255) NOT NULL,
    to_name VARCHAR(255) NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    priority ENUM('low', 'normal', 'high') DEFAULT 'normal',
    status ENUM('pending', 'sending', 'sent', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    scheduled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_scheduled_at (scheduled_at),
    INDEX idx_priority (priority)
);

-- Create sms_queue table for managing SMS sending
CREATE TABLE IF NOT EXISTS sms_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    to_phone VARCHAR(20) NOT NULL,
    to_name VARCHAR(255) NULL,
    message TEXT NOT NULL,
    priority ENUM('low', 'normal', 'high') DEFAULT 'normal',
    status ENUM('pending', 'sending', 'sent', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    scheduled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_scheduled_at (scheduled_at),
    INDEX idx_priority (priority)
);

-- Add some sample announcements
INSERT IGNORE INTO announcements (title, content, priority, target_audience, created_by) VALUES
('Welcome to Our New Communication Portal', 
'We are excited to announce the launch of our new member communication portal. This platform will help us stay connected and keep you informed about important updates, events, and announcements.\n\nFeatures include:\n- Direct messaging\n- Announcements\n- Notifications\n- Member updates\n\nThank you for being part of our community!', 
'high', 
'all', 
1),

('Membership Renewal Season', 
'Dear Members,\n\nIt\'s that time of the year again! Membership renewal season is here.\n\nPlease ensure you renew your membership before the expiry date to continue enjoying our services without interruption.\n\nFor any assistance with the renewal process, please contact our office.', 
'medium', 
'active', 
1),

('System Maintenance Notice', 
'Please be informed that we will be conducting system maintenance on the weekend.\n\nDuring this time, some services may be temporarily unavailable.\n\nWe apologize for any inconvenience and appreciate your understanding.', 
'normal', 
'all', 
1);