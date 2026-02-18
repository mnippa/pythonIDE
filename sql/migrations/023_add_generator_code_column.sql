-- Migration 023: Add generator_code column to tasks table
-- Purpose: Store the Python code generator for code_random_complex tasks

ALTER TABLE tasks ADD COLUMN generator_code LONGTEXT NULL DEFAULT NULL COMMENT 'Generator code for code_random_complex tasks' AFTER solution_code;

-- Update existing code_random_complex tasks with placeholder
-- In real use, these would be populated from the original data
UPDATE tasks SET generator_code = '# TODO: Add generator code for random complex tasks' WHERE task_type = 'code_random_complex' AND generator_code IS NULL;
