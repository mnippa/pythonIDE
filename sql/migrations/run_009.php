<?php
/**
 * Migration 009: Add show_solution field to tasks table
 * Run this file directly: php run_009.php
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $conn = getDbConnection();
    
    echo "Running Migration 009: Add show_solution field...\n";
    
    // Check if column already exists
    echo "Checking if show_solution column exists...\n";
    $check = $conn->query("SHOW COLUMNS FROM tasks LIKE 'show_solution'");
    
    if ($check && $check->num_rows > 0) {
        echo "⚠ show_solution column already exists (skipping)\n";
    } else {
        // Add show_solution column with DEFAULT 1
        echo "Adding show_solution column to tasks table...\n";
        $sql = "ALTER TABLE tasks
            ADD COLUMN show_solution TINYINT(1) NOT NULL DEFAULT 1 AFTER max_attempts";
        
        if ($conn->query($sql)) {
            echo "✓ Added show_solution column with DEFAULT 1\n";
        } else {
            throw new Exception("Failed to add show_solution column: " . $conn->error);
        }
        
        // Update any existing records
        echo "Updating existing records...\n";
        $update = "UPDATE tasks SET show_solution = 1 WHERE show_solution IS NULL";
        if ($conn->query($update)) {
            echo "✓ Updated existing records\n";
        } else {
            echo "⚠ Warning updating records: " . $conn->error . "\n";
        }
    }
    
    echo "\n✅ Migration 009 completed successfully!\n";
    
} catch (Exception $e) {
    echo "\n❌ Migration 009 failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
