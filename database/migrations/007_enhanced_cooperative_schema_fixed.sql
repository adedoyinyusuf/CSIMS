-- Enhanced Cooperative Society Database Schema - Fixed Version
-- This migration adds comprehensive features for loan and contribution management
-- specifically designed for cooperative societies and compatible with existing CSIMS schema

-- ===================================================================
-- LOAN SYSTEM ENHANCEMENTS
-- ===================================================================

-- Create loan guarantors table
CREATE TABLE IF NOT EXISTS loan_guarantors (
    guarantor_id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    guarantor_member_id INT NOT NULL,
    guarantee_amount DECIMAL(15,2) NOT NULL,
    guarantee_percentage DECIMAL(5,2) DEFAULT 100.00,
    guarantee_type ENUM('full', 'partial', 'joint') DEFAULT 'full',
    relationship_to_borrower VARCHAR(100),
    guarantee_date DATE NOT NULL,
    status ENUM('active', 'released', 'called', 'defaulted') DEFAULT 'active',
    release_date DATE NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (loan_id) REFERENCES loans(loan_id) ON DELETE CASCADE,
    FOREIGN KEY (guarantor_member_id) REFERENCES members(member_id),
    INDEX idx_loan_guarantors_loan (loan_id),
    INDEX idx_loan_guarantors_member (guarantor_member_id),
    INDEX idx_loan_guarantors_status (status),
    
    CONSTRAINT chk_guarantee_percentage CHECK (guarantee_percentage > 0 AND guarantee_percentage <= 100),
    CONSTRAINT chk_guarantee_amount_positive CHECK (guarantee_amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create loan collateral table
CREATE TABLE IF NOT EXISTS loan_collateral (
    collateral_id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    collateral_type ENUM('property', 'vehicle', 'shares', 'savings', 'gold', 'equipment', 'other') NOT NULL,
    description TEXT NOT NULL,
    estimated_value DECIMAL(15,2) NOT NULL,
    current_value DECIMAL(15,2),
    location VARCHAR(255),
    document_reference VARCHAR(100),
    valuation_date DATE,
    valuation_by VARCHAR(100),
    insurance_details TEXT,
    status ENUM('pledged', 'held', 'released', 'liquidated') DEFAULT 'pledged',
    pledge_date DATE NOT NULL,
    release_date DATE NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (loan_id) REFERENCES loans(loan_id) ON DELETE CASCADE,
    INDEX idx_loan_collateral_loan (loan_id),
    INDEX idx_loan_collateral_type (collateral_type),
    INDEX idx_loan_collateral_status (status),
    
    CONSTRAINT chk_estimated_value_positive CHECK (estimated_value > 0),
    CONSTRAINT chk_current_value_positive CHECK (current_value IS NULL OR current_value > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create loan payment schedule table
CREATE TABLE IF NOT EXISTS loan_payment_schedule (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    payment_number INT NOT NULL,
    due_date DATE NOT NULL,
    opening_balance DECIMAL(15,2) NOT NULL,
    principal_amount DECIMAL(15,2) NOT NULL,
    interest_amount DECIMAL(15,2) NOT NULL,
    penalty_amount DECIMAL(15,2) DEFAULT 0.00,
    total_amount DECIMAL(15,2) NOT NULL,
    closing_balance DECIMAL(15,2) NOT NULL,
    payment_status ENUM('pending', 'partial', 'paid', 'overdue', 'waived') DEFAULT 'pending',
    payment_date DATE NULL,
    amount_paid DECIMAL(15,2) DEFAULT 0.00,
    grace_period_days INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (loan_id) REFERENCES loans(loan_id) ON DELETE CASCADE,
    INDEX idx_schedule_loan (loan_id),
    INDEX idx_schedule_due_date (due_date),
    INDEX idx_schedule_status (payment_status),
    UNIQUE KEY unique_loan_payment (loan_id, payment_number),
    
    CONSTRAINT chk_amounts_positive CHECK (
        opening_balance >= 0 AND principal_amount >= 0 AND interest_amount >= 0 
        AND penalty_amount >= 0 AND total_amount >= 0 AND closing_balance >= 0
    ),
    CONSTRAINT chk_payment_number_positive CHECK (payment_number > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create loan penalty configurations table
CREATE TABLE IF NOT EXISTS loan_penalty_config (
    config_id INT AUTO_INCREMENT PRIMARY KEY,
    loan_type VARCHAR(50) DEFAULT 'standard',
    grace_period_days INT DEFAULT 7,
    penalty_type ENUM('flat', 'percentage', 'compound') DEFAULT 'percentage',
    penalty_rate DECIMAL(8,4) NOT NULL,
    penalty_basis ENUM('overdue_amount', 'outstanding_balance', 'monthly_payment') DEFAULT 'overdue_amount',
    max_penalty_percentage DECIMAL(5,2) DEFAULT 50.00,
    compound_frequency ENUM('daily', 'weekly', 'monthly') DEFAULT 'monthly',
    waiver_authority ENUM('staff', 'manager', 'board') DEFAULT 'manager',
    is_active BOOLEAN DEFAULT TRUE,
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_penalty_config_type (loan_type),
    INDEX idx_penalty_config_active (is_active),
    INDEX idx_penalty_config_dates (effective_from, effective_to),
    
    CONSTRAINT chk_penalty_rate_positive CHECK (penalty_rate > 0),
    CONSTRAINT chk_max_penalty_valid CHECK (max_penalty_percentage > 0 AND max_penalty_percentage <= 100),
    CONSTRAINT chk_grace_period_valid CHECK (grace_period_days >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default penalty configuration
INSERT IGNORE INTO loan_penalty_config (
    loan_type, grace_period_days, penalty_type, penalty_rate, penalty_basis, 
    max_penalty_percentage, effective_from
) VALUES (
    'standard', 7, 'percentage', 2.0000, 'overdue_amount', 25.00, '2024-01-01'
);

-- ===================================================================
-- CONTRIBUTION SYSTEM ENHANCEMENTS
-- ===================================================================

-- Add status column to contributions table if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name = 'contributions' 
     AND table_schema = DATABASE()
     AND column_name = 'status') = 0,
    'ALTER TABLE contributions ADD COLUMN status ENUM(''Pending'', ''Confirmed'', ''Rejected'', ''Reversed'') DEFAULT ''Confirmed''',
    'SELECT ''Column status already exists'' as info'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create contribution targets table
CREATE TABLE IF NOT EXISTS contribution_targets (
    target_id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    target_type ENUM('monthly', 'quarterly', 'annual', 'special', 'share_capital') NOT NULL,
    target_amount DECIMAL(15,2) NOT NULL,
    target_period_start DATE NOT NULL,
    target_period_end DATE NOT NULL,
    description TEXT,
    priority ENUM('mandatory', 'recommended', 'optional') DEFAULT 'recommended',
    achievement_status ENUM('not_started', 'in_progress', 'achieved', 'overdue', 'waived') DEFAULT 'not_started',
    amount_achieved DECIMAL(15,2) DEFAULT 0.00,
    achievement_percentage DECIMAL(5,2) GENERATED ALWAYS AS (
        CASE 
            WHEN target_amount > 0 THEN (amount_achieved / target_amount) * 100 
            ELSE 0 
        END
    ) STORED,
    auto_deduct BOOLEAN DEFAULT FALSE,
    reminder_enabled BOOLEAN DEFAULT TRUE,
    created_by INT,
    status ENUM('active', 'suspended', 'completed', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES admins(admin_id) ON DELETE SET NULL,
    INDEX idx_targets_member (member_id),
    INDEX idx_targets_type (target_type),
    INDEX idx_targets_period (target_period_start, target_period_end),
    INDEX idx_targets_status (status),
    INDEX idx_targets_priority (priority),
    
    CONSTRAINT chk_target_amount_positive CHECK (target_amount > 0),
    CONSTRAINT chk_achievement_amount_valid CHECK (amount_achieved >= 0),
    CONSTRAINT chk_target_period_valid CHECK (target_period_end >= target_period_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create contribution withdrawals table
CREATE TABLE IF NOT EXISTS contribution_withdrawals (
    withdrawal_id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    withdrawal_type ENUM('partial', 'emergency', 'resignation', 'loan_offset', 'investment') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    contribution_types JSON NOT NULL COMMENT 'Array of contribution types to withdraw from',
    withdrawal_date DATE NOT NULL,
    reason TEXT NOT NULL,
    supporting_documents TEXT COMMENT 'JSON array of document references',
    approval_status ENUM('pending', 'approved', 'rejected', 'processed') DEFAULT 'pending',
    approved_by INT NULL,
    approval_date DATE NULL,
    processed_by INT NULL,
    processing_date DATE NULL,
    processing_method ENUM('cash', 'bank_transfer', 'cheque', 'offset') DEFAULT 'bank_transfer',
    reference_number VARCHAR(100),
    withdrawal_fee DECIMAL(10,2) DEFAULT 0.00,
    net_amount DECIMAL(15,2) GENERATED ALWAYS AS (amount - withdrawal_fee) STORED,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (member_id) REFERENCES members(member_id),
    FOREIGN KEY (approved_by) REFERENCES admins(admin_id) ON DELETE SET NULL,
    FOREIGN KEY (processed_by) REFERENCES admins(admin_id) ON DELETE SET NULL,
    INDEX idx_withdrawals_member (member_id),
    INDEX idx_withdrawals_status (approval_status),
    INDEX idx_withdrawals_date (withdrawal_date),
    INDEX idx_withdrawals_type (withdrawal_type),
    
    CONSTRAINT chk_withdrawal_amount_positive CHECK (amount > 0),
    CONSTRAINT chk_withdrawal_fee_valid CHECK (withdrawal_fee >= 0),
    CONSTRAINT chk_processing_date_after_approval CHECK (
        processing_date IS NULL OR approval_date IS NULL OR processing_date >= approval_date
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create share capital management table
CREATE TABLE IF NOT EXISTS share_capital (
    share_id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    share_type ENUM('ordinary', 'preference', 'founder', 'special') DEFAULT 'ordinary',
    number_of_shares INT NOT NULL,
    par_value DECIMAL(10,2) NOT NULL,
    total_value DECIMAL(15,2) GENERATED ALWAYS AS (number_of_shares * par_value) STORED,
    purchase_date DATE NOT NULL,
    certificate_number VARCHAR(50),
    payment_status ENUM('paid', 'partial', 'pending') DEFAULT 'paid',
    amount_paid DECIMAL(15,2) DEFAULT 0.00,
    dividend_eligible BOOLEAN DEFAULT TRUE,
    transfer_restrictions TEXT,
    status ENUM('active', 'transferred', 'cancelled', 'suspended') DEFAULT 'active',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (member_id) REFERENCES members(member_id),
    INDEX idx_shares_member (member_id),
    INDEX idx_shares_type (share_type),
    INDEX idx_shares_status (status),
    INDEX idx_shares_certificate (certificate_number),
    
    CONSTRAINT chk_shares_positive CHECK (number_of_shares > 0),
    CONSTRAINT chk_par_value_positive CHECK (par_value > 0),
    CONSTRAINT chk_amount_paid_valid CHECK (amount_paid >= 0 AND amount_paid <= (number_of_shares * par_value))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create dividend declarations table
CREATE TABLE IF NOT EXISTS dividend_declarations (
    dividend_id INT AUTO_INCREMENT PRIMARY KEY,
    financial_year INT NOT NULL,
    dividend_rate DECIMAL(8,4) NOT NULL COMMENT 'Dividend rate as percentage',
    declaration_date DATE NOT NULL,
    record_date DATE NOT NULL COMMENT 'Date to determine eligible shareholders',
    payment_date DATE NOT NULL,
    total_dividend_amount DECIMAL(15,2) NOT NULL,
    eligible_shares INT NOT NULL,
    dividend_per_share DECIMAL(10,4) GENERATED ALWAYS AS (
        CASE WHEN eligible_shares > 0 THEN total_dividend_amount / eligible_shares ELSE 0 END
    ) STORED,
    payment_status ENUM('declared', 'approved', 'paid') DEFAULT 'declared',
    approved_by INT NULL,
    approval_date DATE NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (approved_by) REFERENCES admins(admin_id) ON DELETE SET NULL,
    INDEX idx_dividend_year (financial_year),
    INDEX idx_dividend_status (payment_status),
    UNIQUE KEY unique_year_dividend (financial_year),
    
    CONSTRAINT chk_dividend_rate_valid CHECK (dividend_rate >= 0 AND dividend_rate <= 100),
    CONSTRAINT chk_dividend_amount_positive CHECK (total_dividend_amount > 0),
    CONSTRAINT chk_eligible_shares_positive CHECK (eligible_shares > 0),
    CONSTRAINT chk_dividend_dates_valid CHECK (
        record_date >= declaration_date AND payment_date >= record_date
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create member dividend payments table
CREATE TABLE IF NOT EXISTS member_dividend_payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    dividend_id INT NOT NULL,
    member_id INT NOT NULL,
    eligible_shares INT NOT NULL,
    dividend_amount DECIMAL(15,2) NOT NULL,
    tax_deduction DECIMAL(15,2) DEFAULT 0.00,
    net_dividend DECIMAL(15,2) GENERATED ALWAYS AS (dividend_amount - tax_deduction) STORED,
    payment_method ENUM('cash', 'bank_transfer', 'share_reinvestment', 'contribution_credit') DEFAULT 'bank_transfer',
    payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
    payment_date DATE NULL,
    reference_number VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (dividend_id) REFERENCES dividend_declarations(dividend_id),
    FOREIGN KEY (member_id) REFERENCES members(member_id),
    INDEX idx_member_dividends_dividend (dividend_id),
    INDEX idx_member_dividends_member (member_id),
    INDEX idx_member_dividends_status (payment_status),
    UNIQUE KEY unique_member_dividend (dividend_id, member_id),
    
    CONSTRAINT chk_eligible_shares_valid CHECK (eligible_shares >= 0),
    CONSTRAINT chk_dividend_amount_valid CHECK (dividend_amount >= 0),
    CONSTRAINT chk_tax_deduction_valid CHECK (tax_deduction >= 0 AND tax_deduction <= dividend_amount)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- AUDIT AND WORKFLOW TABLES
-- ===================================================================

-- Create audit trail table for financial transactions
CREATE TABLE IF NOT EXISTS financial_audit_trail (
    audit_id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_type ENUM('loan', 'contribution', 'dividend', 'withdrawal', 'penalty', 'fee') NOT NULL,
    transaction_id INT NOT NULL,
    member_id INT NOT NULL,
    action ENUM('create', 'update', 'delete', 'approve', 'reject', 'disburse', 'pay', 'waive') NOT NULL,
    old_values JSON NULL COMMENT 'Previous values before change',
    new_values JSON NULL COMMENT 'New values after change',
    amount_involved DECIMAL(15,2) NULL,
    performed_by INT NOT NULL,
    performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    reason TEXT,
    
    FOREIGN KEY (member_id) REFERENCES members(member_id),
    FOREIGN KEY (performed_by) REFERENCES admins(admin_id),
    INDEX idx_audit_type (transaction_type),
    INDEX idx_audit_transaction (transaction_type, transaction_id),
    INDEX idx_audit_member (member_id),
    INDEX idx_audit_user (performed_by),
    INDEX idx_audit_date (performed_at),
    INDEX idx_audit_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create workflow approvals table
CREATE TABLE IF NOT EXISTS workflow_approvals (
    approval_id INT AUTO_INCREMENT PRIMARY KEY,
    workflow_type ENUM('loan_application', 'loan_disbursement', 'contribution_withdrawal', 'penalty_waiver', 'dividend_declaration') NOT NULL,
    reference_id INT NOT NULL COMMENT 'ID of the record requiring approval',
    member_id INT NULL,
    current_stage INT DEFAULT 1,
    total_stages INT NOT NULL,
    approval_chain JSON NOT NULL COMMENT 'Array of approver user IDs and roles',
    status ENUM('pending', 'in_progress', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    submitted_by INT NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    rejection_reason TEXT NULL,
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    deadline DATE NULL,
    notes TEXT,
    
    FOREIGN KEY (member_id) REFERENCES members(member_id),
    FOREIGN KEY (submitted_by) REFERENCES admins(admin_id),
    FOREIGN KEY (approved_by) REFERENCES admins(admin_id) ON DELETE SET NULL,
    INDEX idx_workflow_type (workflow_type),
    INDEX idx_workflow_reference (workflow_type, reference_id),
    INDEX idx_workflow_status (status),
    INDEX idx_workflow_stage (current_stage),
    INDEX idx_workflow_priority (priority),
    INDEX idx_workflow_deadline (deadline),
    
    CONSTRAINT chk_stages_valid CHECK (current_stage > 0 AND current_stage <= total_stages AND total_stages > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create notification queue table
CREATE TABLE IF NOT EXISTS notification_queue (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_type ENUM('member', 'admin', 'role', 'all_members') NOT NULL,
    recipient_id INT NULL COMMENT 'Member ID or Admin ID',
    recipient_role VARCHAR(50) NULL COMMENT 'Role name for role-based notifications',
    notification_type ENUM('loan_due', 'contribution_due', 'loan_approved', 'loan_rejected', 'dividend_declared', 'withdrawal_approved', 'penalty_applied', 'payment_received', 'general') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    data_payload JSON NULL COMMENT 'Additional structured data',
    delivery_methods JSON NOT NULL COMMENT 'Array of delivery methods: email, sms, in_app',
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    scheduled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    delivery_status ENUM('pending', 'sent', 'failed', 'read') DEFAULT 'pending',
    delivery_attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    last_attempt_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    read_at TIMESTAMP NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_notification_recipient (recipient_type, recipient_id),
    INDEX idx_notification_type (notification_type),
    INDEX idx_notification_status (delivery_status),
    INDEX idx_notification_scheduled (scheduled_at),
    INDEX idx_notification_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- ADDITIONAL INDEXES FOR EXISTING TABLES
-- ===================================================================

-- Add indexes to loans table for better performance
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_name = 'loans' 
     AND table_schema = DATABASE()
     AND index_name = 'idx_loans_status_amount') = 0,
    'ALTER TABLE loans ADD INDEX idx_loans_status_amount (status, amount)',
    'SELECT ''Index idx_loans_status_amount already exists'' as info'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_name = 'loans' 
     AND table_schema = DATABASE()
     AND index_name = 'idx_loans_dates') = 0,
    'ALTER TABLE loans ADD INDEX idx_loans_dates (application_date, approval_date, disbursement_date)',
    'SELECT ''Index idx_loans_dates already exists'' as info'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add indexes to contributions table for better performance
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_name = 'contributions' 
     AND table_schema = DATABASE()
     AND index_name = 'idx_contributions_type_date') = 0,
    'ALTER TABLE contributions ADD INDEX idx_contributions_type_date (contribution_type, contribution_date)',
    'SELECT ''Index idx_contributions_type_date already exists'' as info'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_name = 'contributions' 
     AND table_schema = DATABASE()
     AND index_name = 'idx_contributions_amount') = 0,
    'ALTER TABLE contributions ADD INDEX idx_contributions_amount (amount)',
    'SELECT ''Index idx_contributions_amount already exists'' as info'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ===================================================================
-- SAMPLE DATA INSERTION
-- ===================================================================

-- Insert sample share capital records for existing members (optional)
INSERT IGNORE INTO share_capital (member_id, share_type, number_of_shares, par_value, purchase_date, status)
SELECT member_id, 'ordinary', 10, 100.00, join_date, 'active'
FROM members 
WHERE status = 'Active' 
LIMIT 5;

-- ===================================================================
-- MIGRATION COMPLETION
-- ===================================================================

-- Record the migration as completed
INSERT IGNORE INTO schema_migrations (migration_name, status, notes)
VALUES ('007_enhanced_cooperative_schema_fixed', 'success', 'Enhanced cooperative schema applied successfully');
