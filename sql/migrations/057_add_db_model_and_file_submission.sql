-- Migration 057: Add db_model and file_submission task types + file submission config fields
-- Created: 2026-05-28

ALTER TABLE tasks
MODIFY COLUMN task_type ENUM(
    'code',
    'code_ui',
    'single_choice',
    'multiple_choice',
    'free_text',
    'code_reading',
    'code_random_complex',
    'db_model',
    'file_submission'
) NOT NULL DEFAULT 'code';

ALTER TABLE tasks
ADD COLUMN file_submission_allowed_types VARCHAR(255) NULL AFTER randomizer_code,
ADD COLUMN file_submission_max_size_bytes INT UNSIGNED NOT NULL DEFAULT 102400 AFTER file_submission_allowed_types;