-- Migration: Drop legacy hint column from tasks
USE pythonide;

ALTER TABLE tasks
  DROP COLUMN hint;
