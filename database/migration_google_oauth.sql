-- Migration script to add Google OAuth support to existing users table
-- Run this script if you have an existing database

USE portionpro;

-- Add Google OAuth fields to users table
ALTER TABLE users 
ADD COLUMN google_id VARCHAR(50) UNIQUE AFTER password_hash,
ADD COLUMN picture VARCHAR(255) AFTER google_id;

-- Make username and password_hash nullable for Google OAuth users
ALTER TABLE users 
MODIFY COLUMN username VARCHAR(50) UNIQUE,
MODIFY COLUMN password_hash VARCHAR(255);

-- Add index for Google ID lookups
CREATE INDEX idx_users_google_id ON users(google_id);
