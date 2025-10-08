-- Migration 008: Add extra member-submitted fields to loans table
ALTER TABLE loans
    ADD COLUMN savings DECIMAL(10,2) NULL AFTER amount_paid,
    ADD COLUMN month_deduction_started VARCHAR(7) NULL AFTER savings, -- format YYYY-MM
    ADD COLUMN month_deduction_end VARCHAR(7) NULL AFTER month_deduction_started, -- format YYYY-MM
    ADD COLUMN other_payment_plans TEXT NULL AFTER month_deduction_end,
    ADD COLUMN remarks TEXT NULL AFTER other_payment_plans;
