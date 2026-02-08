<?php
require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

// Check if solution_code already exists
$result = $conn->query("DESCRIBE tasks");
$hasSolution = false;

while ($row = $result->fetch_assoc()) {
    if ($row['Field'] === 'solution_code') {
        $hasSolution = true;
        break;
    }
}

if ($hasSolution) {
    echo "✓ solution_code column already exists\n";
} else {
    echo "Adding solution_code column...\n";
    $sql = "ALTER TABLE tasks ADD COLUMN solution_code LONGTEXT COMMENT 'Musterlösung' AFTER test_cases";
    if ($conn->query($sql)) {
        echo "✓ Added solution_code to tasks\n";
    } else {
        echo "✗ Error: " . $conn->error . "\n";
        exit(1);
    }
}

echo "\n✓ Migration complete!\n";
?>
