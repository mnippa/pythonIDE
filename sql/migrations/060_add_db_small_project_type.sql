-- Migration 060: Add db_small to projects.project_type enum

ALTER TABLE projects
  MODIFY COLUMN project_type ENUM('python', 'html', 'mixed', 'db_small') NOT NULL DEFAULT 'python';
