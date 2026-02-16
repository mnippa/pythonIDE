<?php
require_once __DIR__ . '/../config/database.php';

$db = getDbConnection();

// Check tasks 79 and 80
$sql = "SELECT id, assignment_id, title, task_type, position FROM tasks WHERE id IN (79, 80) ORDER BY id";
$res = $db->query($sql);

echo "Tasks 79 and 80:\n";
echo str_repeat("=", 70) . "\n";

while ($row = $res->fetch_assoc()) {
    echo "Task {$row['id']}: {$row['title']}\n";
    echo "  Assignment: {$row['assignment_id']}\n";
    echo "  Type: {$row['task_type']}\n";
    echo "  Position: {$row['position']}\n\n";
}

// Check all tasks in assignment 12
$sql2 = "SELECT id, title, task_type, position FROM tasks WHERE assignment_id = 12 ORDER BY position";
$res2 = $db->query($sql2);

echo "\nAll tasks in Assignment 12:\n";
echo str_repeat("=", 70) . "\n";

while ($row = $res2->fetch_assoc()) {
    echo "Position {$row['position']}: Task {$row['id']} - {$row['title']} ({$row['task_type']})\n";
}

$db->close();
