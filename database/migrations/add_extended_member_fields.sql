-- Migration to add extended member fields for comprehensive member records
-- This adds fields identified from the typical member record analysis

-- Add additional personal information fields
ALTER TABLE members 
ADD COLUMN IF NOT EXISTS middle_name VARCHAR(50) AFTER first_name,
ADD COLUMN IF NOT EXISTS marital_status ENUM('Single', 'Married', 'Divorced', 'Widowed', 'Other') AFTER gender,
ADD COLUMN IF NOT EXISTS highest_qualification VARCHAR(100) AFTER occupation,
ADD COLUMN IF NOT EXISTS years_of_residence INT AFTER address;

-- Add employment information fields
ALTER TABLE members 
ADD COLUMN IF NOT EXISTS employee_rank VARCHAR(50) AFTER occupation,
ADD COLUMN IF NOT EXISTS grade_level VARCHAR(20) AFTER employee_rank,
ADD COLUMN IF NOT EXISTS position VARCHAR(100) AFTER grade_level,
ADD COLUMN IF NOT EXISTS department VARCHAR(100) AFTER position,
ADD COLUMN IF NOT EXISTS date_of_first_appointment DATE AFTER department,
ADD COLUMN IF NOT EXISTS date_of_retirement DATE AFTER date_of_first_appointment;

-- Add banking information fields
ALTER TABLE members 
ADD COLUMN IF NOT EXISTS bank_name VARCHAR(100) AFTER phone,
ADD COLUMN IF NOT EXISTS account_number VARCHAR(20) AFTER bank_name,
ADD COLUMN IF NOT EXISTS account_name VARCHAR(100) AFTER account_number;

-- Add next of kin information fields
ALTER TABLE members 
ADD COLUMN IF NOT EXISTS next_of_kin_name VARCHAR(100) AFTER account_name,
ADD COLUMN IF NOT EXISTS next_of_kin_relationship VARCHAR(50) AFTER next_of_kin_name,
ADD COLUMN IF NOT EXISTS next_of_kin_phone VARCHAR(20) AFTER next_of_kin_relationship,
ADD COLUMN IF NOT EXISTS next_of_kin_address TEXT AFTER next_of_kin_phone;

-- Add indexes for better performance
ALTER TABLE members 
ADD INDEX IF NOT EXISTS idx_employee_rank (employee_rank),
ADD INDEX IF NOT EXISTS idx_department (department),
ADD INDEX IF NOT EXISTS idx_marital_status (marital_status);

SELECT 'Extended member fields migration completed successfully!' as Status;