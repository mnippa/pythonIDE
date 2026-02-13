<?php
/**
 * Migration 007: Add task types and options for quiz-style tasks
 * Run this file directly: php run_007.php
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $conn = getDbConnection();
    
    echo "Running Migration 007: Add task types and options...\n";
    
    // Execute ALTER TABLE tasks to add new columns
    echo "Adding columns to tasks table...\n";
    $sql = "ALTER TABLE tasks 
        ADD COLUMN task_type ENUM('code', 'single_choice', 'multiple_choice', 'free_text', 'code_reading') 
            NOT NULL DEFAULT 'code' AFTER position,
        ADD COLUMN question_text TEXT NULL AFTER task_type,
        ADD COLUMN image_url VARCHAR(512) NULL AFTER question_text,
        ADD COLUMN correct_answer TEXT NULL AFTER image_url,
        ADD COLUMN variable_overrides JSON NULL AFTER correct_answer,
        ADD INDEX idx_task_type (task_type)";
    
    if ($conn->query($sql)) {
        echo "✓ Extended tasks table with task_type and quiz fields\n";
    } else {
        // Check if columns already exist
        $check = $conn->query("SHOW COLUMNS FROM tasks LIKE 'task_type'");
        if ($check->num_rows > 0) {
            echo "⚠ tasks table already has new columns (skipping)\n";
        } else {
            throw new Exception("Failed to alter tasks table: " . $conn->error);
        }
    }
    
    // Create task_options table
    echo "Creating task_options table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS task_options (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        task_id INT UNSIGNED NOT NULL,
        option_text TEXT NOT NULL,
        image_url VARCHAR(512) NULL,
        is_correct TINYINT(1) NOT NULL DEFAULT 0,
        order_num INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
        INDEX idx_task_id (task_id),
        INDEX idx_order (order_num)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql)) {
        echo "✓ Created task_options table\n";
    } else {
        throw new Exception("Failed to create task_options table: " . $conn->error);
    }
    
    // Add columns to user_tasks
    echo "Adding columns to user_tasks table...\n";
    $sql = "ALTER TABLE user_tasks
        ADD COLUMN selected_options JSON NULL AFTER status,
        ADD COLUMN text_answer TEXT NULL AFTER selected_options,
        ADD COLUMN variable_values JSON NULL AFTER text_answer";
    
    if ($conn->query($sql)) {
        echo "✓ Extended user_tasks table for quiz submissions\n";
    } else {
        // Check if columns already exist
        $check = $conn->query("SHOW COLUMNS FROM user_tasks LIKE 'selected_options'");
        if ($check->num_rows > 0) {
            echo "⚠ user_tasks table already has new columns (skipping)\n";
        } else {
            throw new Exception("Failed to alter user_tasks table: " . $conn->error);
        }
    }
    
    // Update existing tasks
    echo "Updating existing tasks...\n";
    $sql = "UPDATE tasks SET task_type = 'code' WHERE task_type IS NULL OR task_type = ''";
    $conn->query($sql); // Ignore errors if column doesn't exist yet
    echo "✓ Updated existing tasks to task_type='code'\n";
    
    echo "\n✅ Migration 007: Successfully added task types and options!\n";
    echo "New task types available: single_choice, multiple_choice, free_text, code_reading\n";
    
} catch (Exception $e) {
    echo "\n❌ Migration 007 failed: " . $e->getMessage() . "\n";
    exit(1);
}
