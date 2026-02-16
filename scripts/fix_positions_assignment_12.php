<?php
require_once __DIR__ . '/../config/database.php';

$db = getDbConnection();
$assignmentId = 12;

echo "Fixing duplicate positions in Assignment $assignmentId\n";
echo str_repeat("=", 70) . "\n\n";

// Get all tasks for assignment 12, sorted by position, then by id
$sql = "SELECT id, title, position FROM tasks WHERE assignment_id = ? ORDER BY position ASC, id ASC";
$stmt = $db->prepare($sql);
$stmt->bind_param('i', $assignmentId);
$stmt->execute();
$result = $stmt->get_result();

$tasks = [];
while ($row = $result->fetch_assoc()) {
    $tasks[] = $row;
}

echo "Current tasks:\n";
foreach ($tasks as $task) {
    echo "  Position {$task['position']}: Task {$task['id']} - {$task['title']}\n";
}
echo "\n";

// Now re-assign positions sequentially
$updateStmt = $db->prepare('UPDATE tasks SET position = ? WHERE id = ?');

echo "Updating positions:\n";
foreach ($tasks as $index => $task) {
    $newPosition = $index + 1;
    $updateStmt->bind_param('ii', $newPosition, $task['id']);
    
    if ($updateStmt->execute()) {
        if ($task['position'] != $newPosition) {
            echo "  Task {$task['id']}: {$task['position']} -> $newPosition\n";
        } else {
            echo "  Task {$task['id']}: $newPosition (unchanged)\n";
        }
    } else {
        echo "  ERROR updating Task {$task['id']}: " . $updateStmt->error . "\n";
    }
}

echo "\nDone!\n";

$updateStmt->close();
$db->close();
