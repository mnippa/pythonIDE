-- Add UNIQUE constraint to prevent duplicate user_tasks entries
-- This ensures each user can only have one entry per task

-- First check if constraint already exists
SELECT COUNT(*) as constraint_exists
FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA = 'pythonide'
AND TABLE_NAME = 'user_tasks'
AND CONSTRAINT_NAME = 'unique_user_task';

-- If result is 0, run this:
ALTER TABLE user_tasks
ADD CONSTRAINT unique_user_task UNIQUE (user_id, task_id);
