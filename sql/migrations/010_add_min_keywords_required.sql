-- Migration 010: Add min_keywords_required field to tasks table
-- Created: 2026-02-13

USE pythonide;

-- Add min_keywords_required column (NULL = all required)
ALTER TABLE tasks
ADD COLUMN min_keywords_required INT NULL DEFAULT NULL AFTER show_solution;

-- Keep existing behavior: NULL means all keywords required
UPDATE tasks SET min_keywords_required = NULL WHERE task_type = 'free_text';
