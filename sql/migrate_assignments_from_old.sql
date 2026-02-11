-- Migrate assignments from assignments_old to new assignments table
USE pythonide;

-- Clear current assignments (only test data)
DELETE FROM assignments;

-- Migrate assignments (without code_template and time_limit_minutes - those are now in tasks)
INSERT INTO assignments (id, title, description, created_by, created_at, updated_at, is_active, difficulty)
SELECT 
    id,
    title,
    description,
    created_by,
    created_at,
    updated_at,
    is_active,
    difficulty
FROM assignments_old;

-- Note: code_template and time_limit_minutes are now task-level fields
-- If you need to convert old assignments to tasks, create a separate migration
