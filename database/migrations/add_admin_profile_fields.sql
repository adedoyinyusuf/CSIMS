-- Add profile management fields to admins table
-- This migration adds profile_photo, phone, and address columns to support admin profile management

-- Add profile_photo column to store profile image path
ALTER TABLE admins 
ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(255) NULL COMMENT 'Path to profile photo' AFTER email;

-- Add phone column for contact information
ALTER TABLE admins 
ADD COLUMN IF NOT EXISTS phone VARCHAR(20) NULL COMMENT 'Phone number' AFTER profile_photo;

-- Add address column for address information
ALTER TABLE admins 
ADD COLUMN IF NOT EXISTS address TEXT NULL COMMENT 'Address information' AFTER phone;

-- Create uploads directory structure for profile photos
-- Note: This is a comment for manual creation of directory structure:
-- Create: assets/uploads/profiles/ directory with proper permissions (755)

-- Update existing admin records to have NULL values for new fields (already default)
-- No additional UPDATE statements needed as columns are added with NULL default

SELECT 'Admin profile fields migration completed successfully' AS status;