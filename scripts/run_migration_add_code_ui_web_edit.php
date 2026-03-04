<?php
/**
 * Migration: Add allow_code_ui_web_edit field to tasks table
 * Separates file download permission from Code-UI web-edit permission
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

echo "Starting migration: Add allow_code_ui_web_edit field...\n";

try {
    // Check if column already exists
    $result = $conn->query("SHOW COLUMNS FROM tasks LIKE 'allow_code_ui_web_edit'");
    
    if ($result && $result->num_rows > 0) {
        echo "✓ Column 'allow_code_ui_web_edit' already exists.\n";
    } else {
        echo "→ Adding column 'allow_code_ui_web_edit'...\n";
        
        $sql = "ALTER TABLE tasks 
                ADD COLUMN allow_code_ui_web_edit TINYINT(1) DEFAULT 1 
                COMMENT 'For code_ui tasks: allow students to edit HTML/CSS (1=yes, 0=no)' 
                AFTER allowDownload";
        
        if ($conn->query($sql)) {
            echo "✓ Column added successfully.\n";
        } else {
            throw new Exception("Failed to add column: " . $conn->error);
        }
        
        // Populate with existing allowDownload values for backward compatibility
        echo "→ Populating column with backward-compatible values...\n";
        $updateSql = "UPDATE tasks SET allow_code_ui_web_edit = allowDownload WHERE task_type = 'code_ui'";
        if ($conn->query($updateSql)) {
            echo "✓ Column populated.\n";
        } else {
            echo "⚠ Warning: Could not auto-populate (may be OK if no code_ui tasks exist): " . $conn->error . "\n";
        }
        
        // Create indices
        echo "→ Creating indexes...\n";
        $indexSql1 = "ALTER TABLE tasks ADD INDEX idx_allow_code_ui_web_edit (allow_code_ui_web_edit)";
        $indexSql2 = "ALTER TABLE tasks ADD INDEX idx_code_ui_web_edit (task_type, allow_code_ui_web_edit)";
        
        $conn->query($indexSql1);
        $conn->query($indexSql2);
        echo "✓ Indexes created.\n";
    }
    
    echo "\n✅ Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
