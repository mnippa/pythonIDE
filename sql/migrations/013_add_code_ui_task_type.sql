-- Migration 013: Add code_ui to tasks.task_type enum
ALTER TABLE tasks
MODIFY COLUMN task_type ENUM('code', 'code_ui', 'single_choice', 'multiple_choice', 'free_text', 'code_reading', 'code_random_complex') NOT NULL DEFAULT 'code';
