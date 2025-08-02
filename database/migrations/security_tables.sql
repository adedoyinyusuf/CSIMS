-- Security and Authentication Enhancement Tables
-- Run this script to add security features to the CSIMS database

-- Add security-related columns to existing users table
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS account_locked TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS locked_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS lock_reason VARCHAR(255) NULL,
ADD COLUMN IF NOT EXISTS two_factor_secret VARCHAR(64) NULL,
ADD COLUMN IF NOT EXISTS two_factor_enabled TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS password_updated_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS failed_login_attempts INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS last_failed_login TIMESTAMP NULL;

-- Create security_logs table for audit trail
CREATE TABLE IF NOT EXISTS security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    user_id INT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_type (event_type),
    INDEX idx_user_id (user_id),
    INDEX idx_ip_address (ip_address),
    INDEX idx_severity (severity),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Create login_attempts table for tracking failed logins
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    success TINYINT(1) DEFAULT 0,
    failure_reason VARCHAR(255) NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_ip_address (ip_address),
    INDEX idx_success (success),
    INDEX idx_attempted_at (attempted_at)
);

-- Create session_tokens table for secure session management
CREATE TABLE IF NOT EXISTS session_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    INDEX idx_user_id (user_id),
    INDEX idx_token_hash (token_hash),
    INDEX idx_expires_at (expires_at),
    INDEX idx_is_active (is_active),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create password_reset_tokens table
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used_at TIMESTAMP NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_token_hash (token_hash),
    INDEX idx_expires_at (expires_at),
    INDEX idx_used (used),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create api_keys table for API access management
CREATE TABLE IF NOT EXISTS api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    key_name VARCHAR(100) NOT NULL,
    key_hash VARCHAR(255) NOT NULL UNIQUE,
    permissions JSON NULL,
    last_used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_key_hash (key_hash),
    INDEX idx_is_active (is_active),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create user_permissions table for granular permissions
CREATE TABLE IF NOT EXISTS user_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    permission_name VARCHAR(100) NOT NULL,
    granted_by INT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    is_active TINYINT(1) DEFAULT 1,
    INDEX idx_user_id (user_id),
    INDEX idx_permission_name (permission_name),
    INDEX idx_is_active (is_active),
    UNIQUE KEY unique_user_permission (user_id, permission_name),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Create system_settings table for security configuration
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    description TEXT NULL,
    is_public TINYINT(1) DEFAULT 0,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key),
    INDEX idx_is_public (is_public),
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert default security settings
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, description, is_public) VALUES
('max_login_attempts', '5', 'integer', 'Maximum failed login attempts before account lockout', 0),
('lockout_duration', '15', 'integer', 'Account lockout duration in minutes', 0),
('session_timeout', '1440', 'integer', 'Session timeout in minutes (24 hours)', 0),
('password_min_length', '8', 'integer', 'Minimum password length', 0),
('password_require_special', 'true', 'boolean', 'Require special characters in passwords', 0),
('password_require_numbers', 'true', 'boolean', 'Require numbers in passwords', 0),
('password_require_uppercase', 'true', 'boolean', 'Require uppercase letters in passwords', 0),
('two_factor_required', 'false', 'boolean', 'Require two-factor authentication for all users', 0),
('api_rate_limit', '100', 'integer', 'API requests per minute per user', 0),
('security_audit_enabled', 'true', 'boolean', 'Enable automatic security audits', 0);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_users_account_locked ON users(account_locked);
CREATE INDEX IF NOT EXISTS idx_users_two_factor_enabled ON users(two_factor_enabled);
CREATE INDEX IF NOT EXISTS idx_users_last_login ON users(last_login);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);

-- Create view for security dashboard
CREATE OR REPLACE VIEW security_dashboard_view AS
SELECT 
    (SELECT COUNT(*) FROM users WHERE account_locked = 1) as locked_accounts,
    (SELECT COUNT(*) FROM users WHERE two_factor_enabled = 1) as users_with_2fa,
    (SELECT COUNT(*) FROM users) as total_users,
    (SELECT COUNT(*) FROM security_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)) as events_24h,
    (SELECT COUNT(*) FROM security_logs WHERE severity IN ('high', 'critical') AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)) as critical_events_week,
    (SELECT COUNT(DISTINCT ip_address) FROM login_attempts WHERE success = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)) as failed_ips_hour;

-- Create stored procedure for security cleanup
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS CleanupSecurityData()
BEGIN
    -- Clean up old security logs (keep 90 days)
    DELETE FROM security_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
    
    -- Clean up old login attempts (keep 30 days)
    DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    -- Clean up expired session tokens
    DELETE FROM session_tokens WHERE expires_at < NOW() OR is_active = 0;
    
    -- Clean up expired password reset tokens
    DELETE FROM password_reset_tokens WHERE expires_at < NOW() OR used = 1;
    
    -- Clean up expired API keys
    UPDATE api_keys SET is_active = 0 WHERE expires_at < NOW();
    
    -- Reset failed login attempts for unlocked accounts
    UPDATE users SET failed_login_attempts = 0, last_failed_login = NULL 
    WHERE account_locked = 0 AND last_failed_login < DATE_SUB(NOW(), INTERVAL 1 HOUR);
END//
DELIMITER ;

-- Create event scheduler for automatic cleanup (if not exists)
-- Note: This requires SUPER privileges and event_scheduler to be ON
-- CREATE EVENT IF NOT EXISTS security_cleanup_event
-- ON SCHEDULE EVERY 1 DAY
-- STARTS CURRENT_TIMESTAMP
-- DO CALL CleanupSecurityData();

-- Create triggers for automatic logging
DELIMITER //

-- Trigger for user login tracking
CREATE TRIGGER IF NOT EXISTS after_user_login
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    IF NEW.last_login != OLD.last_login AND NEW.last_login IS NOT NULL THEN
        INSERT INTO security_logs (event_type, description, user_id, ip_address, severity)
        VALUES ('user_login', CONCAT('User ', NEW.username, ' logged in'), NEW.id, 'system', 'low');
    END IF;
END//

-- Trigger for account lockout logging
CREATE TRIGGER IF NOT EXISTS after_account_lockout
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    IF NEW.account_locked = 1 AND OLD.account_locked = 0 THEN
        INSERT INTO security_logs (event_type, description, user_id, ip_address, severity)
        VALUES ('account_locked', CONCAT('Account ', NEW.username, ' was locked: ', COALESCE(NEW.lock_reason, 'Unknown reason')), NEW.id, 'system', 'high');
    END IF;
    
    IF NEW.account_locked = 0 AND OLD.account_locked = 1 THEN
        INSERT INTO security_logs (event_type, description, user_id, ip_address, severity)
        VALUES ('account_unlocked', CONCAT('Account ', NEW.username, ' was unlocked'), NEW.id, 'system', 'medium');
    END IF;
END//

DELIMITER ;

-- Grant necessary permissions (adjust as needed for your setup)
-- GRANT SELECT, INSERT, UPDATE, DELETE ON csims.* TO 'csims_user'@'localhost';
-- GRANT EXECUTE ON PROCEDURE csims.CleanupSecurityData TO 'csims_user'@'localhost';

-- Create sample security log entries for testing
INSERT IGNORE INTO security_logs (event_type, description, user_id, ip_address, severity) VALUES
('system_start', 'Security system initialized', NULL, '127.0.0.1', 'low'),
('database_migration', 'Security tables created successfully', NULL, '127.0.0.1', 'medium');

COMMIT;

-- Display completion message
SELECT 'Security tables and features have been successfully created!' as Status;
SELECT 'Remember to run CleanupSecurityData() procedure periodically for maintenance.' as Maintenance_Note;
SELECT 'Consider enabling the event scheduler for automatic cleanup.' as Scheduler_Note;