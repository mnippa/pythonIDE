<?php
require_once __DIR__ . '/config/database.php';
$conn = getDbConnection();

// Check Task 13
echo "=== Task 13 ===\n";
$stmt = $conn->prepare('SELECT id, title, assignment_id, task_type FROM tasks WHERE id = 13');
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    echo "Task ID {$row['id']}: {$row['title']}\n";
    echo "Assignment ID: {$row['assignment_id']}\n";
    echo "Type: {$row['task_type']}\n";
} else {
    echo "Task 13 not found.\n";
}

$conn->close();
