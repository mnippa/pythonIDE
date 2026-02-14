<?php
/**
 * Migration 012: Add show_generator_code field
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $conn = getDbConnection();
    
    echo "Running Migration 012: Add show_generator_code field...\n";
    
    // Check if column already exists
    $result = $conn->query("SHOW COLUMNS FROM tasks LIKE 'show_generator_code'");
    if ($result->num_rows > 0) {
        echo "⚠ Column 'show_generator_code' already exists. Skipping.\n";
        exit(0);
    }
    
    // Add column
    $sql = "ALTER TABLE tasks 
            ADD COLUMN show_generator_code TINYINT(1) NOT NULL DEFAULT 0
            AFTER show_solution";
    
    if ($conn->query($sql)) {
        echo "✓ Added show_generator_code column\n";
        
        // Set default for existing code_random_complex tasks
        $conn->query("UPDATE tasks SET show_generator_code = 0 WHERE task_type = 'code_random_complex'");
        echo "✓ Updated existing code_random_complex tasks\n";
        
        echo "\n✅ Migration 012: Successfully added show_generator_code field!\n";
    } else {
        throw new Exception("Failed to add column: " . $conn->error);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Migration 012 failed: " . $e->getMessage() . "\n";
    exit(1);
}
