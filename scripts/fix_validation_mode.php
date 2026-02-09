<?php
/**
 * Fix Validation Mode for Input Examples
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

// Find tasks with validation_mode = 0
$stmt = $conn->prepare("
    UPDATE tasks 
    SET validation_mode = 'strict'
    WHERE assignment_id = (
        SELECT id FROM assignments 
        WHERE title = 'Funktionen mit verschiedenen Eingaben'
        LIMIT 1
    )
    AND validation_mode = '0'
");

$stmt->execute();
$affected = $stmt->affected_rows;

echo "✓ Fixed validation_mode for $affected tasks\n";

// Verify
$verifyStmt = $conn->prepare("
    SELECT id, title, validation_mode 
    FROM tasks 
    WHERE assignment_id = (
        SELECT id FROM assignments 
        WHERE title = 'Funktionen mit verschiedenen Eingaben'
        LIMIT 1
    )
");
$verifyStmt->execute();
$result = $verifyStmt->get_result();

echo "\nVerification:\n";
while ($task = $result->fetch_assoc()) {
    echo "  Task {$task['id']}: {$task['title']} - Mode: {$task['validation_mode']}\n";
}

$conn->close();
