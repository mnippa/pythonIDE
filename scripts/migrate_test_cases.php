<?php
require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

// Check if test_cases already exists
$result = $conn->query("DESCRIBE tasks");
$hasTestCases = false;

while ($row = $result->fetch_assoc()) {
    if ($row['Field'] === 'test_cases') {
        $hasTestCases = true;
        break;
    }
}

if ($hasTestCases) {
    echo "✓ test_cases column already exists\n";
    exit(0);
}

echo "Adding test_cases columns...\n";

// Add test_cases to tasks
$sql1 = "ALTER TABLE tasks ADD COLUMN test_cases LONGTEXT COMMENT 'JSON array of test cases' AFTER expected_output";
if ($conn->query($sql1)) {
    echo "✓ Added test_cases to tasks\n";
} else {
    echo "✗ Error adding test_cases to tasks: " . $conn->error . "\n";
    exit(1);
}

// Add validation_mode to tasks
$sql2 = "ALTER TABLE tasks ADD COLUMN validation_mode VARCHAR(20) DEFAULT 'loose' COMMENT 'loose or strict' AFTER test_cases";
if (@$conn->query($sql2)) {
    echo "✓ Added validation_mode to tasks\n";
} else {
    // Column might already exist
    echo "- validation_mode already exists or error: " . $conn->error . "\n";
}

// Try to add to assignment_files if it exists
$check = $conn->query("DESCRIBE assignment_files LIMIT 1");
if ($check) {
    $sql3 = "ALTER TABLE assignment_files ADD COLUMN test_cases LONGTEXT COMMENT 'JSON test cases' AFTER content";
    if (@$conn->query($sql3)) {
        echo "✓ Added test_cases to assignment_files\n";
    }
}

echo "\n✓ Migration complete!\n";
?>
