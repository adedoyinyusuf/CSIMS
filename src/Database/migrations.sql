-- CSIMS Database Migration Script
-- This script updates the existing database schema to match the refactored system requirements

-- Create migrations table to track applied migrations
CREATE TABLE IF NOT EXISTS migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration_name VARCHAR(255) NOT NULL UNIQUE,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Migration 001: Update members table structure
-- Check if members table needs updating
SET @table_exists = (
    SELECT COUNT(*)
    FROM information_schema.tables 
    WHERE table_schema = DATABASE() 
    AND table_name = 'members'
);

-- Update members table if needed
SET @migration_name = '001_update_members_table';
SET @migration_exists = (
    SELECT COUNT(*) FROM migrations WHERE migration_name = @migration_name
);

-- Only run if migration hasn't been applied
-- Note: In production, wrap these in proper migration logic

-- Members table structure
CREATE TABLE IF NOT EXISTS members (
    member_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20) UNIQUE NOT NULL,
    date_of_birth DATE,
    gender ENUM('Male', 'Female', 'Other'),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    postal_code VARCHAR(20),
    country VARCHAR(100) DEFAULT 'Nigeria',
    occupation VARCHAR(100),
    monthly_income DECIMAL(15,2),
    join_date DATE NOT NULL,
    status ENUM('Active', 'Inactive', 'Suspended') DEFAULT 'Active',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_members_email (email),
    INDEX idx_members_phone (phone),
    INDEX idx_members_status (status),
    INDEX idx_members_join_date (join_date)
);

-- Migration 002: Update loans table structure
CREATE TABLE IF NOT EXISTS loans (
    loan_id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    interest_rate DECIMAL(5,2) NOT NULL,
    term_months INT NOT NULL,
    monthly_payment DECIMAL(15,2),
    current_balance DECIMAL(15,2),
    application_date DATE NOT NULL,
    approval_date DATE NULL,
    disbursement_date DATE NULL,
    next_payment_date DATE NULL,
    last_payment_date DATE NULL,
    last_payment_amount DECIMAL(15,2) NULL,
    paid_date DATE NULL,
    purpose TEXT,
    collateral_description TEXT,
    collateral_value DECIMAL(15,2),
    guarantor_name VARCHAR(200),
    guarantor_phone VARCHAR(20),
    guarantor_address TEXT,
    status ENUM('Pending', 'Approved', 'Rejected', 'Disbursed', 'Active', 'Paid', 'Defaulted') DEFAULT 'Pending',
    approved_by VARCHAR(100),
    disbursed_by VARCHAR(100),
    rejection_reason TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    INDEX idx_loans_member_id (member_id),
    INDEX idx_loans_status (status),
    INDEX idx_loans_application_date (application_date),
    INDEX idx_loans_next_payment_date (next_payment_date),
    INDEX idx_loans_amount (amount)
);

-- Migration 003: Update contributions table structure
CREATE TABLE IF NOT EXISTS contributions (
    contribution_id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    contribution_date DATE NOT NULL,
    contribution_type ENUM('Monthly', 'Special', 'Share Capital', 'Development Levy', 'Entrance Fee', 'Other') NOT NULL,
    payment_method ENUM('Cash', 'Bank Transfer', 'Mobile Money', 'Cheque', 'Direct Debit', 'Card Payment') NOT NULL,
    receipt_number VARCHAR(50) UNIQUE,
    notes TEXT,
    status ENUM('Pending', 'Confirmed', 'Rejected', 'Reversed') DEFAULT 'Confirmed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    INDEX idx_contributions_member_id (member_id),
    INDEX idx_contributions_date (contribution_date),
    INDEX idx_contributions_type (contribution_type),
    INDEX idx_contributions_status (status),
    INDEX idx_contributions_receipt (receipt_number),
    INDEX idx_contributions_amount (amount)
);

-- Migration 004: Create transactions table (for audit trail)
CREATE TABLE IF NOT EXISTS transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    loan_id INT NULL,
    contribution_id INT NULL,
    transaction_type ENUM('Loan Disbursement', 'Loan Payment', 'Contribution', 'Fee', 'Interest', 'Penalty', 'Refund', 'Transfer') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    balance_before DECIMAL(15,2),
    balance_after DECIMAL(15,2),
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    description TEXT,
    reference_number VARCHAR(100),
    processed_by VARCHAR(100),
    status ENUM('Pending', 'Completed', 'Failed', 'Cancelled') DEFAULT 'Completed',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    FOREIGN KEY (loan_id) REFERENCES loans(loan_id) ON DELETE SET NULL,
    FOREIGN KEY (contribution_id) REFERENCES contributions(contribution_id) ON DELETE SET NULL,
    INDEX idx_transactions_member_id (member_id),
    INDEX idx_transactions_loan_id (loan_id),
    INDEX idx_transactions_contribution_id (contribution_id),
    INDEX idx_transactions_type (transaction_type),
    INDEX idx_transactions_date (transaction_date),
    INDEX idx_transactions_status (status),
    INDEX idx_transactions_reference (reference_number)
);

-- Migration 005: Create users table for system authentication
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    role ENUM('Admin', 'Manager', 'Officer', 'Viewer') NOT NULL DEFAULT 'Officer',
    status ENUM('Active', 'Inactive', 'Suspended') DEFAULT 'Active',
    last_login TIMESTAMP NULL,
    failed_login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL,
    password_reset_token VARCHAR(255) NULL,
    password_reset_expires TIMESTAMP NULL,
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    two_factor_secret VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_username (username),
    INDEX idx_users_email (email),
    INDEX idx_users_role (role),
    INDEX idx_users_status (status)
);

-- Migration 006: Create user sessions table
CREATE TABLE IF NOT EXISTS user_sessions (
    session_id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_sessions_user_id (user_id),
    INDEX idx_sessions_expires_at (expires_at),
    INDEX idx_sessions_active (is_active)
);

-- Migration 007: Create audit log table
CREATE TABLE IF NOT EXISTS audit_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    table_name VARCHAR(100) NOT NULL,
    record_id INT NOT NULL,
    action ENUM('CREATE', 'UPDATE', 'DELETE', 'VIEW') NOT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_audit_user_id (user_id),
    INDEX idx_audit_table_record (table_name, record_id),
    INDEX idx_audit_action (action),
    INDEX idx_audit_timestamp (timestamp)
);

-- Migration 008: Create system settings table
CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_settings_public (is_public)
);

-- Migration 009: Create loan payments table (detailed payment history)
CREATE TABLE IF NOT EXISTS loan_payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    principal_amount DECIMAL(15,2) NOT NULL,
    interest_amount DECIMAL(15,2) NOT NULL,
    penalty_amount DECIMAL(15,2) DEFAULT 0,
    payment_date DATE NOT NULL,
    due_date DATE NOT NULL,
    days_late INT DEFAULT 0,
    payment_method ENUM('Cash', 'Bank Transfer', 'Mobile Money', 'Cheque', 'Direct Debit', 'Card Payment') NOT NULL,
    receipt_number VARCHAR(50),
    processed_by VARCHAR(100),
    notes TEXT,
    status ENUM('Pending', 'Completed', 'Failed', 'Reversed') DEFAULT 'Completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (loan_id) REFERENCES loans(loan_id) ON DELETE CASCADE,
    INDEX idx_payments_loan_id (loan_id),
    INDEX idx_payments_date (payment_date),
    INDEX idx_payments_due_date (due_date),
    INDEX idx_payments_status (status),
    INDEX idx_payments_receipt (receipt_number)
);

-- Migration 010: Create notifications table
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    member_id INT NULL,
    type ENUM('payment_due', 'payment_overdue', 'loan_approved', 'loan_rejected', 'contribution_received', 'system_alert') NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    data JSON NULL,
    is_read BOOLEAN DEFAULT FALSE,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    INDEX idx_notifications_user_id (user_id),
    INDEX idx_notifications_member_id (member_id),
    INDEX idx_notifications_type (type),
    INDEX idx_notifications_read (is_read),
    INDEX idx_notifications_created_at (created_at)
);

-- Insert default system settings
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, description, is_public) VALUES
('app_name', 'Credit and Savings Information Management System', 'string', 'Application name', TRUE),
('app_version', '2.0.0', 'string', 'Application version', TRUE),
('currency_symbol', '$', 'string', 'Currency symbol', TRUE),
('currency_code', 'USD', 'string', 'Currency code', TRUE),
('default_loan_interest_rate', '12.0', 'number', 'Default loan interest rate percentage', FALSE),
('max_loan_amount', '100000', 'number', 'Maximum loan amount allowed', FALSE),
('min_loan_amount', '100', 'number', 'Minimum loan amount allowed', FALSE),
('max_loan_term_months', '360', 'number', 'Maximum loan term in months', FALSE),
('monthly_contribution_required', '100', 'number', 'Required monthly contribution amount', FALSE),
('late_payment_penalty_rate', '2.0', 'number', 'Late payment penalty rate percentage', FALSE),
('session_timeout_minutes', '60', 'number', 'User session timeout in minutes', FALSE),
('max_login_attempts', '5', 'number', 'Maximum failed login attempts before lockout', FALSE),
('lockout_duration_minutes', '30', 'number', 'Account lockout duration in minutes', FALSE),
('backup_enabled', 'true', 'boolean', 'Enable automatic database backups', FALSE),
('notification_email_enabled', 'true', 'boolean', 'Enable email notifications', FALSE),
('maintenance_mode', 'false', 'boolean', 'Enable maintenance mode', FALSE);

-- Insert default admin user (password: admin123 - CHANGE THIS IN PRODUCTION!)
INSERT IGNORE INTO users (username, email, password_hash, first_name, last_name, role, status) VALUES
('admin', 'admin@csims.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System', 'Administrator', 'Admin', 'Active');

-- Create stored procedures for common operations

-- Procedure to calculate loan payment schedule
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS CalculateLoanSchedule(
    IN p_loan_amount DECIMAL(15,2),
    IN p_interest_rate DECIMAL(5,2),
    IN p_term_months INT,
    IN p_start_date DATE
)
BEGIN
    DECLARE v_monthly_rate DECIMAL(10,8);
    DECLARE v_monthly_payment DECIMAL(15,2);
    DECLARE v_remaining_balance DECIMAL(15,2);
    DECLARE v_interest_payment DECIMAL(15,2);
    DECLARE v_principal_payment DECIMAL(15,2);
    DECLARE v_payment_date DATE;
    DECLARE v_payment_number INT;
    
    SET v_monthly_rate = p_interest_rate / 100 / 12;
    SET v_monthly_payment = p_loan_amount * (v_monthly_rate * POWER(1 + v_monthly_rate, p_term_months)) / (POWER(1 + v_monthly_rate, p_term_months) - 1);
    SET v_remaining_balance = p_loan_amount;
    SET v_payment_number = 1;
    SET v_payment_date = DATE_ADD(p_start_date, INTERVAL 1 MONTH);
    
    DROP TEMPORARY TABLE IF EXISTS temp_payment_schedule;
    CREATE TEMPORARY TABLE temp_payment_schedule (
        payment_number INT,
        payment_date DATE,
        payment_amount DECIMAL(15,2),
        principal_amount DECIMAL(15,2),
        interest_amount DECIMAL(15,2),
        remaining_balance DECIMAL(15,2)
    );
    
    WHILE v_payment_number <= p_term_months AND v_remaining_balance > 0.01 DO
        SET v_interest_payment = v_remaining_balance * v_monthly_rate;
        SET v_principal_payment = v_monthly_payment - v_interest_payment;
        
        IF v_principal_payment > v_remaining_balance THEN
            SET v_principal_payment = v_remaining_balance;
            SET v_monthly_payment = v_principal_payment + v_interest_payment;
        END IF;
        
        SET v_remaining_balance = v_remaining_balance - v_principal_payment;
        
        INSERT INTO temp_payment_schedule VALUES (
            v_payment_number,
            v_payment_date,
            v_monthly_payment,
            v_principal_payment,
            v_interest_payment,
            v_remaining_balance
        );
        
        SET v_payment_number = v_payment_number + 1;
        SET v_payment_date = DATE_ADD(v_payment_date, INTERVAL 1 MONTH);
    END WHILE;
    
    SELECT * FROM temp_payment_schedule ORDER BY payment_number;
END//
DELIMITER ;

-- Procedure to get member financial summary
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS GetMemberFinancialSummary(
    IN p_member_id INT
)
BEGIN
    SELECT 
        m.member_id,
        CONCAT(m.first_name, ' ', m.last_name) as member_name,
        m.status as member_status,
        
        -- Contribution summary
        COALESCE(c.total_contributions, 0) as total_contributions,
        COALESCE(c.monthly_contributions, 0) as monthly_contributions,
        COALESCE(c.special_contributions, 0) as special_contributions,
        COALESCE(c.last_contribution_date, NULL) as last_contribution_date,
        
        -- Loan summary
        COALESCE(l.total_loans, 0) as total_loans,
        COALESCE(l.active_loans, 0) as active_loans,
        COALESCE(l.total_borrowed, 0) as total_borrowed,
        COALESCE(l.outstanding_balance, 0) as outstanding_balance,
        COALESCE(l.next_payment_date, NULL) as next_payment_date,
        COALESCE(l.monthly_payment_due, 0) as monthly_payment_due
        
    FROM members m
    LEFT JOIN (
        SELECT 
            member_id,
            SUM(amount) as total_contributions,
            SUM(CASE WHEN contribution_type = 'Monthly' THEN amount ELSE 0 END) as monthly_contributions,
            SUM(CASE WHEN contribution_type != 'Monthly' THEN amount ELSE 0 END) as special_contributions,
            MAX(contribution_date) as last_contribution_date
        FROM contributions 
        WHERE status = 'Confirmed'
        GROUP BY member_id
    ) c ON m.member_id = c.member_id
    LEFT JOIN (
        SELECT 
            member_id,
            COUNT(*) as total_loans,
            SUM(CASE WHEN status IN ('Active', 'Disbursed') THEN 1 ELSE 0 END) as active_loans,
            SUM(amount) as total_borrowed,
            SUM(CASE WHEN status IN ('Active', 'Disbursed') THEN current_balance ELSE 0 END) as outstanding_balance,
            MIN(CASE WHEN status IN ('Active', 'Disbursed') THEN next_payment_date END) as next_payment_date,
            SUM(CASE WHEN status IN ('Active', 'Disbursed') THEN monthly_payment ELSE 0 END) as monthly_payment_due
        FROM loans 
        GROUP BY member_id
    ) l ON m.member_id = l.member_id
    WHERE m.member_id = p_member_id;
END//
DELIMITER ;

-- Create views for common queries

-- View for active loans with member information
CREATE OR REPLACE VIEW active_loans_view AS
SELECT 
    l.loan_id,
    l.member_id,
    CONCAT(m.first_name, ' ', m.last_name) as member_name,
    m.email as member_email,
    m.phone as member_phone,
    l.amount,
    l.interest_rate,
    l.term_months,
    l.monthly_payment,
    l.current_balance,
    l.next_payment_date,
    l.status,
    DATEDIFF(CURRENT_DATE, l.next_payment_date) as days_overdue,
    l.disbursement_date,
    l.purpose,
    l.created_at
FROM loans l
JOIN members m ON l.member_id = m.member_id
WHERE l.status IN ('Active', 'Disbursed')
ORDER BY l.next_payment_date ASC;

-- View for member contribution summary
CREATE OR REPLACE VIEW member_contributions_summary AS
SELECT 
    m.member_id,
    CONCAT(m.first_name, ' ', m.last_name) as member_name,
    m.email,
    m.status as member_status,
    COALESCE(SUM(c.amount), 0) as total_contributions,
    COALESCE(SUM(CASE WHEN c.contribution_type = 'Monthly' THEN c.amount ELSE 0 END), 0) as monthly_total,
    COALESCE(SUM(CASE WHEN c.contribution_type != 'Monthly' THEN c.amount ELSE 0 END), 0) as special_total,
    COUNT(c.contribution_id) as contribution_count,
    MAX(c.contribution_date) as last_contribution_date,
    MIN(c.contribution_date) as first_contribution_date
FROM members m
LEFT JOIN contributions c ON m.member_id = c.member_id AND c.status = 'Confirmed'
GROUP BY m.member_id, m.first_name, m.last_name, m.email, m.status
ORDER BY total_contributions DESC;

-- View for overdue loans
CREATE OR REPLACE VIEW overdue_loans_view AS
SELECT 
    l.*,
    CONCAT(m.first_name, ' ', m.last_name) as member_name,
    m.email as member_email,
    m.phone as member_phone,
    DATEDIFF(CURRENT_DATE, l.next_payment_date) as days_overdue,
    l.monthly_payment as amount_due
FROM loans l
JOIN members m ON l.member_id = m.member_id
WHERE l.status IN ('Active', 'Disbursed') 
    AND l.next_payment_date < CURRENT_DATE
ORDER BY days_overdue DESC;

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_members_name ON members(first_name, last_name);
CREATE INDEX IF NOT EXISTS idx_loans_member_status ON loans(member_id, status);
CREATE INDEX IF NOT EXISTS idx_contributions_member_type ON contributions(member_id, contribution_type);
CREATE INDEX IF NOT EXISTS idx_transactions_member_type ON transactions(member_id, transaction_type);

-- Record this migration as completed
INSERT IGNORE INTO migrations (migration_name) VALUES
('001_update_members_table'),
('002_update_loans_table'),
('003_update_contributions_table'),
('004_create_transactions_table'),
('005_create_users_table'),
('006_create_user_sessions_table'),
('007_create_audit_log_table'),
('008_create_system_settings_table'),
('009_create_loan_payments_table'),
('010_create_notifications_table');

-- Migration complete message
SELECT 'CSIMS database migration completed successfully. Please change default admin password!' as message;
