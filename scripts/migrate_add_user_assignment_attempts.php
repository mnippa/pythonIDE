<?php
/**
 * Migration: Add attempts column to user_assignments table
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

// Check if attempts column already exists
$check = $conn->query("SHOW COLUMNS FROM user_assignments LIKE 'attempts'");

if ($check->num_rows === 0) {
    $sql = 'ALTER TABLE user_assignments ADD COLUMN attempts INT DEFAULT 0 AFTER test_results';
    
    if ($conn->query($sql)) {
        echo "✓ Added attempts column to user_assignments\n";
    } else {
        echo "✗ Failed to add attempts column: " . $conn->error . "\n";
    }
} else {
    echo "ℹ Column attempts already exists in user_assignments\n";
}

echo "\n✓ Migration complete\n";
?>
