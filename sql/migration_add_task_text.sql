-- Migration: Add task_text column to unify question_text and description
-- Date: 2026-02-20
-- Purpose: Simplify task content handling by using a single field

-- Step 1: Add new column
ALTER TABLE tasks 
ADD COLUMN task_text TEXT AFTER description;

-- Step 2: Migrate data (prioritize question_text, fallback to description)
UPDATE tasks 
SET task_text = COALESCE(NULLIF(TRIM(question_text), ''), TRIM(description));

-- Step 3: Verify migration (optional check)
-- SELECT 
--   id, 
--   title,
--   task_type,
--   CASE 
--     WHEN question_text IS NOT NULL AND question_text != '' THEN 'question_text'
--     WHEN description IS NOT NULL AND description != '' THEN 'description'
--     ELSE 'none'
--   END as source,
--   LENGTH(task_text) as task_text_length
-- FROM tasks
-- ORDER BY task_type, id;

-- Note: description and question_text columns are kept for backward compatibility
-- They can be deprecated/removed in a future migration once all code is updated
