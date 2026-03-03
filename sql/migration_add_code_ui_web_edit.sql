-- Migration: Add separate field for Code-UI web-edit permission
-- Separates 'allowDownload' (file downloads) from 'allow_code_ui_web_edit' (student editing of HTML/CSS)
-- Date: 2026-03-03

USE pythonide;

-- Add new column with default value
ALTER TABLE tasks 
ADD COLUMN allow_code_ui_web_edit TINYINT(1) DEFAULT 1 COMMENT 'For code_ui tasks: allow students to edit HTML/CSS (1=yes, 0=no)' 
AFTER allowDownload;

-- Populate with existing allowDownload values for backward compatibility
UPDATE tasks SET allow_code_ui_web_edit = allowDownload WHERE task_type = 'code_ui';

-- Create index for better performance
ALTER TABLE tasks ADD INDEX idx_allow_code_ui_web_edit (allow_code_ui_web_edit);

-- Add index for combined queries
ALTER TABLE tasks ADD INDEX idx_code_ui_web_edit (task_type, allow_code_ui_web_edit);

COMMIT;
