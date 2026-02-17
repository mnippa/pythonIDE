-- Migration 010: Add iteration tracking for code_reading and code_random_complex
-- Created: 2026-02-16

USE pythonide;

-- Add max_iterations to tasks
ALTER TABLE tasks
ADD COLUMN max_iterations INT NOT NULL DEFAULT 1 AFTER max_attempts;

-- Add current_iteration to user_tasks
ALTER TABLE user_tasks
ADD COLUMN current_iteration INT NOT NULL DEFAULT 1 AFTER attempts;

-- Normalize variable_overrides for code_reading to array format
UPDATE tasks
SET variable_overrides = JSON_ARRAY(variable_overrides)
WHERE task_type = 'code_reading'
  AND variable_overrides IS NOT NULL
  AND JSON_TYPE(variable_overrides) = 'OBJECT';

-- Derive max_iterations from number of sets for code_reading
UPDATE tasks
SET max_iterations = CASE
  WHEN variable_overrides IS NULL THEN 1
  WHEN JSON_TYPE(variable_overrides) = 'ARRAY' THEN JSON_LENGTH(variable_overrides)
  ELSE 1
END
WHERE task_type = 'code_reading';

-- Default iterations for random complex
UPDATE tasks
SET max_iterations = 3
WHERE task_type = 'code_random_complex' AND (max_iterations IS NULL OR max_iterations < 1);

-- Ensure max_iterations is at least 1 for all tasks
UPDATE tasks
SET max_iterations = 1
WHERE max_iterations IS NULL OR max_iterations < 1;
