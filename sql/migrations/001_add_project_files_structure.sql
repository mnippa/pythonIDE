-- Migration: Add Project Files/Folders Structure
-- Created: 2026-02-25
-- Purpose: Enable hierarchical file system for projects (projects.php)
--          Reusable for assignments and other modules

USE pythonide;

-- ============================================
-- PROJECT FOLDERS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS project_folders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    parent_folder_id INT UNSIGNED DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_folder_id) REFERENCES project_folders(id) ON DELETE CASCADE,
    
    INDEX idx_project_id (project_id),
    INDEX idx_parent_folder_id (parent_folder_id),
    UNIQUE KEY unique_folder_name (project_id, parent_folder_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PROJECT FILES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS project_files (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    folder_id INT UNSIGNED DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    content MEDIUMTEXT,
    mime_type VARCHAR(100) DEFAULT 'text/plain',
    file_size INT UNSIGNED DEFAULT 0,
    is_binary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (folder_id) REFERENCES project_folders(id) ON DELETE SET NULL,
    
    INDEX idx_project_id (project_id),
    INDEX idx_folder_id (folder_id),
    INDEX idx_name (name),
    UNIQUE KEY unique_file_name (project_id, folder_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- NOTES
-- ============================================
-- Folder Structure:
--   project_folders.parent_folder_id = NULL → Root folders (images/, includes/)
--   
-- File Storage:
--   mime_type = 'text/plain' for Python files
--   mime_type = 'image/*' for binary files
--   content = NULL for folders (security)
--
-- Reusability:
--   Can be extended for assignments (assignment_files, assignment_folders)
--   or test projects (test_project_files, etc.)
