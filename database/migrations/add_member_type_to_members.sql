-- Migration to add member_type field to members table
ALTER TABLE members 
ADD COLUMN IF NOT EXISTS member_type ENUM('member','non-member') DEFAULT 'member' AFTER marital_status;

SELECT 'member_type column added to members table.' as Status;