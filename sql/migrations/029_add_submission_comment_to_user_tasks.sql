-- Migration 029: Add optional submission comment to user_tasks
-- Stores a short free-text note written by the student on submission.

ALTER TABLE user_tasks
ADD COLUMN submission_comment TEXT NULL AFTER current_code;