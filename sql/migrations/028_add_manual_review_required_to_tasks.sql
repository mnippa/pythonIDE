-- Migration 028: Add manual review flag for tasks
ALTER TABLE tasks
ADD COLUMN manual_review_required TINYINT(1) NOT NULL DEFAULT 0 AFTER show_solution_code;

CREATE INDEX idx_tasks_manual_review_required ON tasks(manual_review_required);