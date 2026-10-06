-- Migration 063: Add UML task mode support
-- Created: 2026-07-31
-- Status: planned / for later live deployment

-- NOTE:
-- This migration is prepared for the UML task mode feature.
-- It should be executed after local validation and before any production rollout.

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
    'file_submission',
    'uml'
) NOT NULL DEFAULT 'code';

ALTER TABLE tasks
ADD COLUMN uml_model JSON NULL AFTER file_submission_max_size_bytes,
ADD COLUMN uml_solution JSON NULL AFTER uml_model,
ADD COLUMN uml_template JSON NULL AFTER uml_solution;
