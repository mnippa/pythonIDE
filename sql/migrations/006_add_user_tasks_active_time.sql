-- Add active time tracking columns to user_tasks
-- Tracks accumulated active seconds and last activity timestamp

ALTER TABLE user_tasks
ADD COLUMN active_seconds INT NOT NULL DEFAULT 0,
ADD COLUMN last_active_at DATETIME NULL,
ADD COLUMN is_active TINYINT NOT NULL DEFAULT 0;

-- Create index for efficient queries on active status and last_active_at
CREATE INDEX idx_user_tasks_is_active ON user_tasks(is_active);
CREATE INDEX idx_user_tasks_last_active_at ON user_tasks(last_active_at);
