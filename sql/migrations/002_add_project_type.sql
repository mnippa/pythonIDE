-- Migration: Add project_type to projects table
-- Similar to task_type in assignments, controls UI layout

ALTER TABLE projects 
ADD COLUMN project_type ENUM('python', 'html', 'mixed') DEFAULT 'python' NOT NULL
AFTER description;

-- Add visibility and share_token if not already added by previous migrations
ALTER TABLE projects 
ADD COLUMN IF NOT EXISTS visibility ENUM('private', 'public') DEFAULT 'private' NOT NULL
AFTER project_type;

ALTER TABLE projects 
ADD COLUMN IF NOT EXISTS share_token VARCHAR(64) NULL DEFAULT NULL
AFTER visibility;

-- Create index for common lookups
ALTER TABLE projects 
ADD INDEX IF NOT EXISTS idx_project_type (project_type);

ALTER TABLE projects 
ADD INDEX IF NOT EXISTS idx_visibility (visibility);

ALTER TABLE projects 
ADD INDEX IF NOT EXISTS idx_share_token (share_token);
