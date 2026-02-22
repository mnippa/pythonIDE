-- Migration 024: Rename show_generator_code to show_solution_code
-- Created: 2026-02-21

USE pythonide;

ALTER TABLE tasks
CHANGE COLUMN show_generator_code show_solution_code TINYINT(1) NOT NULL DEFAULT 0;
