<?php
/**
 * Migration 010: Add min_keywords_required field to tasks table
 * Run this file directly: php run_010.php
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $conn = getDbConnection();
    
    echo "Running Migration 010: Add min_keywords_required field...\n";
    
    // Check if column already exists
    echo "Checking if min_keywords_required column exists...\n";
    $check = $conn->query("SHOW COLUMNS FROM tasks LIKE 'min_keywords_required'");
    
    if ($check && $check->num_rows > 0) {
        echo "⚠ min_keywords_required column already exists (skipping)\n";
    } else {
        // Add min_keywords_required column (NULL = all required)
        echo "Adding min_keywords_required column to tasks table...\n";
        $sql = "ALTER TABLE tasks
            ADD COLUMN min_keywords_required INT NULL DEFAULT NULL AFTER show_solution";
        
        if ($conn->query($sql)) {
            echo "✓ Added min_keywords_required column (NULL = all keywords required)\n";
        } else {
            throw new Exception("Failed to add min_keywords_required column: " . $conn->error);
        }
        
        // Keep existing behavior: NULL means all keywords required
        echo "Setting default behavior for existing free_text tasks...\n";
        $update = "UPDATE tasks SET min_keywords_required = NULL WHERE task_type = 'free_text'";
        if ($conn->query($update)) {
            echo "✓ Updated existing free_text tasks\n";
        } else {
            echo "⚠ Warning updating records: " . $conn->error . "\n";
        }
    }
    
    echo "\n✅ Migration 010 completed successfully!\n";
    
} catch (Exception $e) {
    echo "\n❌ Migration 010 failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
