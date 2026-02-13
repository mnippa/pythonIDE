-- Migration 007: Add task types (Single-Choice, Multiple-Choice, Free Text, Code Reading)
-- Created: 2026-02-12

USE pythonide;

-- Add new columns to tasks table for quiz-style tasks
ALTER TABLE tasks 
ADD COLUMN task_type ENUM('code', 'single_choice', 'multiple_choice', 'free_text', 'code_reading') 
    NOT NULL DEFAULT 'code' AFTER position,
ADD COLUMN question_text TEXT NULL AFTER task_type,
ADD COLUMN image_url VARCHAR(512) NULL AFTER question_text,
ADD COLUMN correct_answer TEXT NULL AFTER image_url,
ADD COLUMN variable_overrides JSON NULL AFTER correct_answer,
ADD INDEX idx_task_type (task_type);

-- Create task_options table for Single-Choice and Multiple-Choice answers
CREATE TABLE IF NOT EXISTS task_options (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id INT UNSIGNED NOT NULL,
    option_text TEXT NOT NULL,
    image_url VARCHAR(512) NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    order_num INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    INDEX idx_task_id (task_id),
    INDEX idx_order (order_num)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add new columns to user_tasks table for non-code submissions
ALTER TABLE user_tasks
ADD COLUMN selected_options JSON NULL AFTER status,
ADD COLUMN text_answer TEXT NULL AFTER selected_options,
ADD COLUMN variable_values JSON NULL AFTER text_answer;

-- Update existing tasks to have task_type = 'code' (already default, but explicit)
UPDATE tasks SET task_type = 'code' WHERE task_type IS NULL OR task_type = '';
