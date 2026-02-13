-- Migration 009: Add show_solution field to tasks table
-- Created: 2026-02-13

USE pythonide;

-- Add show_solution column with default value of 1 (true)
ALTER TABLE tasks
ADD COLUMN show_solution TINYINT(1) NOT NULL DEFAULT 1 AFTER max_attempts;

-- Update existing tasks to show solution by default
UPDATE tasks SET show_solution = 1 WHERE show_solution IS NULL;
