-- Add 'Pending' to the members table status ENUM
ALTER TABLE members 
MODIFY COLUMN status ENUM('Pending', 'Active', 'Inactive', 'Suspended', 'Expired') DEFAULT 'Active';