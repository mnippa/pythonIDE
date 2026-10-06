-- Migration 062: Add assignment-level lock for task/template mutations

ALTER TABLE assignments
  ADD COLUMN locked TINYINT(1) NOT NULL DEFAULT 0 AFTER allow_late_submission;
