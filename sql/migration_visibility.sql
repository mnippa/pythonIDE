-- Migration: Add visibility and settings
-- Run after initial schema

USE pythonide;

-- Add visibility and share_token to projects
ALTER TABLE projects 
ADD COLUMN visibility ENUM('private', 'public') DEFAULT 'private' AFTER code,
ADD COLUMN share_token VARCHAR(64) NULL UNIQUE AFTER visibility,
ADD INDEX idx_share_token (share_token),
ADD INDEX idx_visibility (visibility);

-- Settings table for configurable limits
CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default limits
INSERT INTO settings (setting_key, setting_value, description) VALUES
('project_limit_per_user', '50', 'Maximum projects per user'),
('project_code_max_size', '102400', 'Maximum code size in bytes (100KB)'),
('allow_public_projects', '1', 'Allow users to create public projects')
ON DUPLICATE KEY UPDATE setting_key=setting_key;
