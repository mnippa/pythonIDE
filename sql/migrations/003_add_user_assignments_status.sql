-- Migration 003: Add status to user_assignments
-- Execute: mysql -u root -p python_ide < sql/migrations/003_add_user_assignments_status.sql

ALTER TABLE user_assignments
  ADD COLUMN status ENUM('assigned', 'in_progress', 'submitted', 'passed', 'failed') NOT NULL DEFAULT 'assigned' AFTER assignment_id;

CREATE INDEX idx_user_assignments_status ON user_assignments(status);
