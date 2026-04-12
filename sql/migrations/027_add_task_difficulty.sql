-- Migration 027: Add task-level difficulty with 3 levels
ALTER TABLE tasks
ADD COLUMN task_difficulty ENUM('basic', 'medium', 'hard') NOT NULL DEFAULT 'medium' AFTER task_type;

CREATE INDEX idx_tasks_task_difficulty ON tasks(task_difficulty);
