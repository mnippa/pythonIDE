<?php
/**
 * Migration 013: Add code_ui task type
 * Run this file directly: php run_013.php
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $conn = getDbConnection();

    echo "Running Migration 013: Add code_ui task type...\n";

    $check = $conn->query("SHOW COLUMNS FROM tasks LIKE 'task_type'");
    if ($check && $check->num_rows > 0) {
        $row = $check->fetch_assoc();
        $typeDef = strtolower($row['Type'] ?? '');

        if (strpos($typeDef, 'code_ui') !== false) {
            echo "⚠ task_type already includes code_ui (skipping)\n";
        } else {
            $sql = "ALTER TABLE tasks MODIFY COLUMN task_type ENUM('code', 'code_ui', 'single_choice', 'multiple_choice', 'free_text', 'code_reading', 'code_random_complex') NOT NULL DEFAULT 'code'";
            if ($conn->query($sql)) {
                echo "✓ Added code_ui to task_type enum\n";
            } else {
                throw new Exception("Failed to alter tasks table: " . $conn->error);
            }
        }
    } else {
        throw new Exception("task_type column not found in tasks table");
    }

    echo "\n✅ Migration 013: Successfully updated task_type enum!\n";
} catch (Exception $e) {
    echo "\n❌ Migration 013 failed: " . $e->getMessage() . "\n";
    exit(1);
}
