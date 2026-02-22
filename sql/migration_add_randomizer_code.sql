-- Migration: Add randomizer_code column for code_random_complex tasks
-- Date: 2026-02-21
-- Purpose: Allow hidden randomizer code separate from visible code_template

-- Step 1: Add new column for randomizer code
ALTER TABLE tasks 
ADD COLUMN randomizer_code LONGTEXT NULL AFTER code_template;

-- Step 2: Verify column added
-- SELECT * FROM tasks WHERE id = 69;
