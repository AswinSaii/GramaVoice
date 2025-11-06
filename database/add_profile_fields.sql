-- Migration: Add profile-related fields used by PHP code
-- Run this file against the existing grama_voice database to add missing columns.

USE grama_voice;

-- Add email and address to users table if they don't exist
ALTER TABLE users 
  ADD COLUMN IF NOT EXISTS email VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS address TEXT DEFAULT NULL;

-- Add email and profile_image to panchayat_admins table if they don't exist
ALTER TABLE panchayat_admins
  ADD COLUMN IF NOT EXISTS email VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS profile_image VARCHAR(500) DEFAULT NULL;

-- Note: Some versions of MySQL/MariaDB do not support ADD COLUMN IF NOT EXISTS in older releases.
-- If your server reports a syntax error, run the following instead (uncommented):
-- ALTER TABLE users ADD COLUMN email VARCHAR(255) DEFAULT NULL;
-- ALTER TABLE users ADD COLUMN address TEXT DEFAULT NULL;
-- ALTER TABLE panchayat_admins ADD COLUMN email VARCHAR(255) DEFAULT NULL;
-- ALTER TABLE panchayat_admins ADD COLUMN profile_image VARCHAR(500) DEFAULT NULL;

SELECT 'Migration completed';
