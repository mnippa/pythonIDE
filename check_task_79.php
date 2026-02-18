<?php
require 'config/database.php';
$conn = getDbConnection();

// Check what data Task 79 has
$sql = "SELECT id, title, task_type, solution_code, expected_output FROM tasks WHERE id = 79";
$result = $conn->query($sql);

if (!$result) {
    die("Query error: " . $conn->error . "\n");
}

$task = $result->fetch_assoc();

if (!$task) {
    die("Task 79 not found\n");
}

echo "Task 79 Data:\n";
echo "=============\n";
echo "Title: " . $task['title'] . "\n";
echo "Type: " . $task['task_type'] . "\n\n";

echo "solution_code:\n";
if ($task['solution_code']) {
    echo "  ✓ Exists (" . strlen($task['solution_code']) . " chars)\n";
    echo "  Content:\n";
    echo "  ---\n";
    echo "  " . str_replace("\n", "\n  ", $task['solution_code']) . "\n";
    echo "  ---\n\n";
} else {
    echo "  ✗ Empty/NULL\n\n";
}

echo "expected_output:\n";
if ($task['expected_output']) {
    echo "  ✓ Exists (" . strlen($task['expected_output']) . " chars)\n";
    echo "  Content: " . substr($task['expected_output'], 0, 200) . "\n\n";
} else {
    echo "  ✗ Empty/NULL\n\n";
}
?>
