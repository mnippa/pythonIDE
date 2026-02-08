<?php
/**
 * Add Project Files & Folders Structure
 * Creates tables for folder and file management in projects
 * Run: php scripts/add_files_structure.php
 */

require_once __DIR__ . '/../config/database.php';

echo "\n========================================\n";
echo "Adding Project Files & Folders Structure\n";
echo "========================================\n\n";

try {
    $conn = getDbConnection();
    
    // ============================================
    // 1. Create folders table
    // ============================================
    echo "1. Creating folders table...\n";
    
    $result = $conn->query("SHOW TABLES LIKE 'folders'");
    
    if ($result->num_rows > 0) {
        echo "   ✓ Folders table already exists\n";
    } else {
        echo "   → Creating new folders table...\n";
        
        $sql = "
            CREATE TABLE IF NOT EXISTS folders (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                project_id INT UNSIGNED NOT NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                path VARCHAR(1024) NOT NULL,
                parent_folder_id INT UNSIGNED DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                FOREIGN KEY (parent_folder_id) REFERENCES folders(id) ON DELETE CASCADE,
                UNIQUE KEY unique_folder_path (project_id, path),
                INDEX idx_project_id (project_id),
                INDEX idx_parent_id (parent_folder_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        if ($conn->query($sql)) {
            echo "   ✓ Folders table created successfully\n";
        } else {
            throw new Exception("Failed to create folders table: " . $conn->error);
        }
    }
    
    echo "\n";
    
    // ============================================
    // 2. Create files table
    // ============================================
    echo "2. Creating files table...\n";
    
    $result = $conn->query("SHOW TABLES LIKE 'files'");
    
    if ($result->num_rows > 0) {
        echo "   ✓ Files table already exists\n";
    } else {
        echo "   → Creating new files table...\n";
        
        $sql = "
            CREATE TABLE IF NOT EXISTS files (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                folder_id INT UNSIGNED NOT NULL,
                project_id INT UNSIGNED NOT NULL,
                name VARCHAR(255) NOT NULL,
                file_type ENUM('python', 'json', 'image', 'text', 'other') DEFAULT 'other',
                extension VARCHAR(20),
                mime_type VARCHAR(100),
                content LONGTEXT,
                file_path VARCHAR(1024),
                file_size INT UNSIGNED DEFAULT 0,
                is_binary BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE CASCADE,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                UNIQUE KEY unique_file_path (project_id, file_path),
                INDEX idx_project_id (project_id),
                INDEX idx_folder_id (folder_id),
                INDEX idx_file_type (file_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        if ($conn->query($sql)) {
            echo "   ✓ Files table created successfully\n";
        } else {
            throw new Exception("Failed to create files table: " . $conn->error);
        }
    }
    
    echo "\n";
    
    // ============================================
    // 3. Create assignment_files table
    // ============================================
    echo "3. Creating assignment_files table...\n";
    
    $result = $conn->query("SHOW TABLES LIKE 'assignment_files'");
    
    if ($result->num_rows > 0) {
        echo "   ✓ Assignment files table already exists\n";
    } else {
        echo "   → Creating new assignment_files table...\n";
        
        $sql = "
            CREATE TABLE IF NOT EXISTS assignment_files (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                assignment_id INT UNSIGNED NOT NULL,
                task_id INT UNSIGNED DEFAULT NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                file_type ENUM('python', 'json', 'image', 'text', 'other') DEFAULT 'other',
                extension VARCHAR(20),
                mime_type VARCHAR(100),
                content LONGTEXT,
                file_path VARCHAR(1024),
                is_template BOOLEAN DEFAULT TRUE,
                is_starter_code BOOLEAN DEFAULT FALSE,
                is_solution BOOLEAN DEFAULT FALSE,
                is_hidden BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
                FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL,
                INDEX idx_assignment_id (assignment_id),
                INDEX idx_task_id (task_id),
                INDEX idx_file_type (file_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        if ($conn->query($sql)) {
            echo "   ✓ Assignment files table created successfully\n";
        } else {
            throw new Exception("Failed to create assignment_files table: " . $conn->error);
        }
    }
    
    echo "\n========================================\n";
    echo "✓ Files structure created successfully!\n";
    echo "========================================\n\n";
    
    echo "Tables created:\n";
    echo "  1. folders - Project folder structure\n";
    echo "     Fields: id, project_id, name, path, parent_folder_id\n\n";
    echo "  2. files - Project files\n";
    echo "     Fields: id, folder_id, project_id, name, file_type, content\n";
    echo "     Types: python, json, image, text, other\n\n";
    echo "  3. assignment_files - Template files for assignments\n";
    echo "     Fields: id, assignment_id, task_id, name, file_type, content\n";
    echo "     Flags: is_template, is_starter_code, is_solution, is_hidden\n\n";
    
    echo "File Type Support:\n";
    echo "  - python (.py) - Python source code\n";
    echo "  - json (.json) - JSON data files\n";
    echo "  - image (.png, .jpg, .jpeg, .gif, .webp) - Image files\n";
    echo "  - text (.txt, .md, .csv) - Text files\n";
    echo "  - other - Other file types\n\n";
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n\n";
    exit(1);
}
