<?php
/**
 * Add max_attempts column to tasks table
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

echo "Adding max_attempts column to tasks table...\n";

// Check if column already exists
$stmt = $conn->prepare("DESCRIBE tasks");
$stmt->execute();
$result = $stmt->get_result();

$columnExists = false;
while ($row = $result->fetch_assoc()) {
    if ($row['Field'] === 'max_attempts') {
        $columnExists = true;
        break;
    }
}

if (!$columnExists) {
    $sql = "ALTER TABLE tasks ADD COLUMN max_attempts INT DEFAULT 10 AFTER solution_code";
    if ($conn->query($sql)) {
        echo "✓ Added max_attempts column (default: 10 attempts)\n";
    } else {
        echo "✗ Error: " . $conn->error . "\n";
    }
} else {
    echo "✓ Column max_attempts already exists\n";
}

// Show current structure
echo "\nTasks table structure:\n";
$stmt = $conn->prepare("DESCRIBE tasks");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    if (in_array($row['Field'], ['id', 'title', 'test_cases', 'validation_mode', 'solution_code', 'max_attempts'])) {
        echo "  - {$row['Field']}: {$row['Type']}\n";
    }
}

?>
