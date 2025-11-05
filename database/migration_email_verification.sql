-- Email Verification Migration for PortionPro
-- Adds email verification fields to users table

USE portionpro;

-- Add email verification fields to users table
ALTER TABLE users 
ADD COLUMN is_verified BOOLEAN DEFAULT FALSE AFTER role,
ADD COLUMN verification_token VARCHAR(255) UNIQUE AFTER is_verified,
ADD COLUMN verification_expires_at TIMESTAMP NULL AFTER verification_token,
ADD COLUMN verified_at TIMESTAMP NULL AFTER verification_expires_at;

-- Create index for verification token lookups
CREATE INDEX idx_users_verification_token ON users(verification_token);

-- Create index for verification status
CREATE INDEX idx_users_is_verified ON users(is_verified);

-- Update existing users to be verified (for migration)
UPDATE users SET is_verified = TRUE, verified_at = created_at WHERE is_verified IS NULL;
