<?php
/**
 * Migration: Add hint1, hint2, hint3, and stoff columns to tasks table
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

$columns = [
    'hint1' => 'ALTER TABLE tasks ADD COLUMN hint1 LONGTEXT AFTER hint',
    'hint2' => 'ALTER TABLE tasks ADD COLUMN hint2 LONGTEXT AFTER hint1',
    'hint3' => 'ALTER TABLE tasks ADD COLUMN hint3 LONGTEXT AFTER hint2',
    'stoff' => 'ALTER TABLE tasks ADD COLUMN stoff LONGTEXT AFTER hint3'
];

foreach ($columns as $colName => $sql) {
    // Check if column already exists
    $check = $conn->query("SHOW COLUMNS FROM tasks LIKE '$colName'");
    
    if ($check->num_rows === 0) {
        if ($conn->query($sql)) {
            echo "✓ Added $colName column\n";
        } else {
            echo "✗ Failed to add $colName column: " . $conn->error . "\n";
        }
    } else {
        echo "ℹ Column $colName already exists\n";
    }
}

echo "\n✓ Migration complete\n";
?>
