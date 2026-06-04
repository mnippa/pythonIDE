-- Migration 059
-- Add optional admin feedback comment for manual review flow.
-- Safe additive change: old application code continues to work unchanged.

ALTER TABLE user_tasks
  ADD COLUMN admin_feedback_comment TEXT NULL AFTER submission_comment;
