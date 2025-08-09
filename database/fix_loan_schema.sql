-- Fix loan schema by adding missing fields and tables
-- This script adds the missing loan_repayments table and additional fields to loans table

-- Add missing fields to loans table
ALTER TABLE loans 
ADD COLUMN IF NOT EXISTS monthly_payment DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS disbursement_date DATE NULL,
ADD COLUMN IF NOT EXISTS last_payment_date DATE NULL,
ADD COLUMN IF NOT EXISTS amount_paid DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS collateral TEXT NULL,
ADD COLUMN IF NOT EXISTS notes TEXT NULL;

-- Create loan_repayments table if it doesn't exist
CREATE TABLE IF NOT EXISTS loan_repayments (
    repayment_id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method VARCHAR(50) NOT NULL DEFAULT 'Cash',
    receipt_number VARCHAR(100) NULL,
    notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (loan_id) REFERENCES loans(loan_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES admins(admin_id) ON DELETE SET NULL
);

-- Update existing loans to calculate monthly payment where it's missing
UPDATE loans 
SET monthly_payment = ROUND(
    (amount * (interest_rate/100/12) * POW(1 + (interest_rate/100/12), term)) / 
    (POW(1 + (interest_rate/100/12), term) - 1), 2
)
WHERE monthly_payment = 0.00 OR monthly_payment IS NULL;

-- Create trigger to update amount_paid when repayments are added
DELIMITER //
CREATE TRIGGER IF NOT EXISTS update_loan_amount_paid 
AFTER INSERT ON loan_repayments
FOR EACH ROW
BEGIN
    UPDATE loans 
    SET amount_paid = (
        SELECT COALESCE(SUM(amount), 0) 
        FROM loan_repayments 
        WHERE loan_id = NEW.loan_id
    ),
    last_payment_date = NEW.payment_date
    WHERE loan_id = NEW.loan_id;
END//
DELIMITER ;

-- Create trigger to update amount_paid when repayments are updated
DELIMITER //
CREATE TRIGGER IF NOT EXISTS update_loan_amount_paid_on_update 
AFTER UPDATE ON loan_repayments
FOR EACH ROW
BEGIN
    UPDATE loans 
    SET amount_paid = (
        SELECT COALESCE(SUM(amount), 0) 
        FROM loan_repayments 
        WHERE loan_id = NEW.loan_id
    ),
    last_payment_date = (
        SELECT MAX(payment_date) 
        FROM loan_repayments 
        WHERE loan_id = NEW.loan_id
    )
    WHERE loan_id = NEW.loan_id;
END//
DELIMITER ;

-- Create trigger to update amount_paid when repayments are deleted
DELIMITER //
CREATE TRIGGER IF NOT EXISTS update_loan_amount_paid_on_delete 
AFTER DELETE ON loan_repayments
FOR EACH ROW
BEGIN
    UPDATE loans 
    SET amount_paid = (
        SELECT COALESCE(SUM(amount), 0) 
        FROM loan_repayments 
        WHERE loan_id = OLD.loan_id
    ),
    last_payment_date = (
        SELECT MAX(payment_date) 
        FROM loan_repayments 
        WHERE loan_id = OLD.loan_id
    )
    WHERE loan_id = OLD.loan_id;
END//
DELIMITER ;