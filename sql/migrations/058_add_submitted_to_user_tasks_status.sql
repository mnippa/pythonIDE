ALTER TABLE user_tasks
MODIFY COLUMN status ENUM('unbearbeitet', 'in-progress', 'submitted', 'passed', 'failed') NULL DEFAULT 'unbearbeitet';
