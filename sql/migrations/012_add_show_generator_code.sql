-- Migration 012: Add show_generator_code for code_random_complex tasks
-- Created: 2026-02-13

USE pythonide;

-- Add show_generator_code column to tasks table
ALTER TABLE tasks 
ADD COLUMN show_generator_code TINYINT(1) NOT NULL DEFAULT 0
AFTER show_solution;

-- Update existing code_random_complex tasks to hide generator code by default (more realistic)
UPDATE tasks SET show_generator_code = 0 WHERE task_type = 'code_random_complex';
