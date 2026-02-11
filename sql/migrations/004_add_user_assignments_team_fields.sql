-- Migration 004: Add team assignment fields to user_assignments
-- Execute: mysql -u root -p python_ide < sql/migrations/004_add_user_assignments_team_fields.sql

ALTER TABLE user_assignments
  ADD COLUMN team_id INT NULL AFTER user_id,
  ADD COLUMN assigned_by INT NULL AFTER assigned_at,
  ADD COLUMN due_date DATETIME NULL AFTER assigned_by;

ALTER TABLE user_assignments
  ADD CONSTRAINT fk_user_assignments_team
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
  ADD CONSTRAINT fk_user_assignments_assigned_by
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL;

CREATE INDEX idx_user_assignments_team ON user_assignments(team_id);
