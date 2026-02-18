<?php
require 'config/database.php';
$conn = getDbConnection();

// Directly add the column
$sql = "ALTER TABLE tasks ADD COLUMN generator_code LONGTEXT NULL DEFAULT NULL COMMENT 'Generator code for code_random_complex tasks' AFTER solution_code";

echo "Executing: ALTER TABLE tasks ADD COLUMN generator_code...\n";

if ($conn->query($sql)) {
    echo "✓ Column created successfully\n";
    
    // Update existing code_random_complex tasks
    $updateSql = "UPDATE tasks SET generator_code = '# Generator code placeholder' WHERE task_type = 'code_random_complex' AND generator_code IS NULL";
    if ($conn->query($updateSql)) {
        echo "✓ Placeholder values added\n";
    } else {
        echo "⚠ Update failed: " . $conn->error . "\n";
    }
} else {
    // Check if column already exists
    if (strpos($conn->error, 'Duplicate column') !== false) {
        echo "⚠ Column already exists\n";
    } else {
        echo "❌ Error: " . $conn->error . "\n";
    }
}
?>
