-- Migration 066: Enable UML projects.
-- Apply only after the UML project files have been deployed and verified.

ALTER TABLE projects
MODIFY COLUMN project_type ENUM('python', 'html', 'mixed', 'db_small', 'uml') NOT NULL DEFAULT 'python';
