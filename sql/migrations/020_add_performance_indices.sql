-- Performance optimization: Add composite indices for batch loading queries

-- Index for user_tasks lookups by user and task
CREATE INDEX IF NOT EXISTS idx_user_tasks_user_task ON user_tasks(user_id, task_id);

-- Index for task_options lookups by task (used in batch load with IN clause)
CREATE INDEX IF NOT EXISTS idx_task_options_task ON task_options(task_id);

-- Index for faster task lookup by assignment
CREATE INDEX IF NOT EXISTS idx_tasks_assignment ON tasks(assignment_id, position);
