-- Add DB-backed "last opened project" tracking for users
USE pythonide;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS last_opened_project_id INT UNSIGNED NULL DEFAULT NULL AFTER role;

CREATE INDEX idx_last_opened_project_id ON users(last_opened_project_id);
