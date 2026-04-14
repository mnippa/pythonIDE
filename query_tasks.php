<?php
require_once __DIR__ . '/config/database.php';

$conn = getDbConnection();

// Get tasks in assignment 23
echo "=== Tasks in Assignment 23 ===\n";
$result = $conn->query("SELECT id, title, task_type, position FROM tasks WHERE assignment_id=23 ORDER BY position");
while ($row = $result->fetch_assoc()) {
  echo "ID: {$row['id']}, Title: {$row['title']}, Type: {$row['task_type']}, Pos: {$row['position']}\n";
}

// Get task #176 structure
echo "\n=== Task #176 Structure ===\n";
$result = $conn->query("SELECT id, title, task_type, randomizer_code, variable_overrides, solution_code FROM tasks WHERE id=176");
$task = $result->fetch_assoc();

echo "ID: {$task['id']}\n";
echo "Title: {$task['title']}\n";
echo "Type: {$task['task_type']}\n";
echo "\n--- Randomizer Code ---\n";
echo $task['randomizer_code'] . "\n";
echo "\n--- Variable Overrides ---\n";
echo substr($task['variable_overrides'], 0, 500) . "...\n";
echo "\n--- Solution Code ---\n";
echo substr($task['solution_code'], 0, 500) . "...\n";
