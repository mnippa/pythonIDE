-- Migration 008: Add max_attempts field to tasks table
-- Created: 2026-02-13

USE pythonide;

-- Add max_attempts column with default value of 1
ALTER TABLE tasks
ADD COLUMN max_attempts INT NOT NULL DEFAULT 1 AFTER correct_answer;

-- Update existing tasks to explicitly set max_attempts = 1 where NULL
UPDATE tasks SET max_attempts = 1 WHERE max_attempts IS NULL OR max_attempts = 0;

-- Add index for faster queries
ALTER TABLE tasks
ADD INDEX idx_max_attempts (max_attempts);
