-- Migration: Remove username column
-- Created: 2026-02-08
-- Purpose: Username is no longer needed, email serves as unique identifier

USE pythonide;

-- Drop the old index on username
DROP INDEX idx_username ON users;

-- Remove the username column
ALTER TABLE users DROP COLUMN username;

-- Verify changes
SELECT 'Migration completed successfully' as status;
