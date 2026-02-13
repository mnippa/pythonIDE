<?php
/**
 * Migration 008: Add max_attempts field to tasks table with DEFAULT 1
 * Run this file directly: php run_008.php
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $conn = getDbConnection();
    
    echo "Running Migration 008: Add max_attempts field...\n";
    
    // Check if column already exists
    echo "Checking if max_attempts column exists...\n";
    $check = $conn->query("SHOW COLUMNS FROM tasks LIKE 'max_attempts'");
    
    if ($check && $check->num_rows > 0) {
        echo "⚠ max_attempts column already exists (skipping)\n";
    } else {
        // Add max_attempts column with DEFAULT 1
        echo "Adding max_attempts column to tasks table...\n";
        $sql = "ALTER TABLE tasks
            ADD COLUMN max_attempts INT NOT NULL DEFAULT 1 AFTER correct_answer";
        
        if ($conn->query($sql)) {
            echo "✓ Added max_attempts column with DEFAULT 1\n";
        } else {
            throw new Exception("Failed to add max_attempts column: " . $conn->error);
        }
        
        // Update any existing records where max_attempts might be NULL or 0
        echo "Updating existing records...\n";
        $update = "UPDATE tasks SET max_attempts = 1 WHERE max_attempts IS NULL OR max_attempts = 0";
        if ($conn->query($update)) {
            echo "✓ Updated existing records\n";
        } else {
            echo "⚠ Warning updating records: " . $conn->error . "\n";
        }
        
        // Add index for faster queries
        echo "Adding index on max_attempts column...\n";
        $index = "ALTER TABLE tasks ADD INDEX idx_max_attempts (max_attempts)";
        if ($conn->query($index) || strpos($conn->error, "Duplicate key name") !== false) {
            echo "✓ Index added or already exists\n";
        } else {
            echo "⚠ Warning adding index: " . $conn->error . "\n";
        }
    }
    
    echo "\n✅ Migration 008 completed successfully!\n";
    
} catch (Exception $e) {
    echo "\n❌ Migration 008 failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
