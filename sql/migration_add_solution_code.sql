-- Migration: Add solution_code column to tasks
-- Purpose: Store reference solution for each task

ALTER TABLE tasks ADD COLUMN solution_code LONGTEXT COMMENT 'Musterlösung für die Aufgabe' AFTER test_cases;
