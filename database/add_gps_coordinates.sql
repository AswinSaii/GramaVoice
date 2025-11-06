-- Add GPS coordinates to issues table
-- This migration adds latitude and longitude columns for precise location tracking

USE grama_voice;

-- Add GPS coordinate columns to issues table
ALTER TABLE issues 
ADD COLUMN latitude DECIMAL(10, 8) NULL AFTER location,
ADD COLUMN longitude DECIMAL(11, 8) NULL AFTER latitude,
ADD COLUMN location_accuracy DECIMAL(8, 2) NULL AFTER longitude;

-- Add index for location-based queries
CREATE INDEX idx_issues_location ON issues(latitude, longitude);

-- Add comment to explain the columns
ALTER TABLE issues 
MODIFY COLUMN latitude DECIMAL(10, 8) NULL COMMENT 'GPS latitude coordinate',
MODIFY COLUMN longitude DECIMAL(11, 8) NULL COMMENT 'GPS longitude coordinate',
MODIFY COLUMN location_accuracy DECIMAL(8, 2) NULL COMMENT 'GPS accuracy in meters';

-- Update existing issues with default coordinates (optional)
-- UPDATE issues SET latitude = 0, longitude = 0 WHERE latitude IS NULL;
