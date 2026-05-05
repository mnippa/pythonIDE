-- 027_add_graded_at_graded_by_to_user_assignments.sql
-- Adds grading-timestamp and grading-admin tracking to user_assignments.
-- Safe for production: uses ADD COLUMN IF NOT EXISTS.
-- Note: graded_at already existed on some deployments; both are idempotent.

ALTER TABLE user_assignments
    ADD COLUMN IF NOT EXISTS graded_at  DATETIME NULL AFTER submitted_at,
    ADD COLUMN IF NOT EXISTS graded_by  INT NULL      AFTER graded_at;

