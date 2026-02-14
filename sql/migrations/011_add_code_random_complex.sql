-- Migration 011: Add code_random_complex task type
-- Created: 2026-02-13

USE pythonide;

ALTER TABLE tasks
MODIFY COLUMN task_type ENUM('code', 'single_choice', 'multiple_choice', 'free_text', 'code_reading', 'code_random_complex')
    NOT NULL DEFAULT 'code';
