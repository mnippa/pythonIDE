-- Migration 005: Add run_count to user_tasks
-- Execute: mysql -u root -p python_ide < sql/migrations/005_add_user_tasks_run_count.sql

ALTER TABLE user_tasks
  ADD COLUMN run_count INT NOT NULL DEFAULT 0 AFTER attempts;

CREATE INDEX idx_user_tasks_run_count ON user_tasks(run_count);
