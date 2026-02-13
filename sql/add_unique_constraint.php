<?php
require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

echo "=== Adding UNIQUE constraint to user_tasks ===\n\n";

// Check if constraint exists
$result = $conn->query("
    SELECT COUNT(*) as cnt
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = 'pythonide'
    AND TABLE_NAME = 'user_tasks'
    AND CONSTRAINT_NAME = 'unique_user_task'
");

$row = $result->fetch_assoc();

if ($row['cnt'] > 0) {
    echo "✓ UNIQUE constraint already exists.\n";
} else {
    echo "Adding UNIQUE constraint...\n";
    
    $sql = "ALTER TABLE user_tasks ADD CONSTRAINT unique_user_task UNIQUE (user_id, task_id)";
    
    if ($conn->query($sql)) {
        echo "✓ UNIQUE constraint added successfully!\n";
        echo "  This prevents duplicate entries for the same user/task combination.\n";
    } else {
        echo "✗ Error adding constraint: " . $conn->error . "\n";
    }
}
