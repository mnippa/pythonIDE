<?php
require 'config/database.php';
$conn = getDbConnection();

// Test: Fetch Task 79 via the API logic
$test_mode = 1;
$admin = true;

// Get base task info
$sql = "SELECT id, title, task_type, question_text, solution_code FROM tasks WHERE id = 79";
$result = $conn->query($sql);

if (!$result) {
    echo "ERROR: " . $conn->error . "\n";
    die();
}

$task = $result->fetch_assoc();

if (!$task) {
    echo "Task 79 not found\n";
    die();
}

echo "✓ Task 79 successfully fetched\n\n";
echo "Key fields:\n";
echo "- ID: " . $task['id'] . "\n";
echo "- Title: " . $task['title'] . "\n";
echo "- Type: " . $task['task_type'] . "\n";
echo "- solution_code exists: " . (!!$task['solution_code'] ? 'YES' : 'NO') . "\n";
echo "\n";

if ($task['solution_code']) {
    echo "solution_code content (first 200 chars):\n";
    echo substr($task['solution_code'], 0, 200) . "\n\n";
}

echo "Full JSON output:\n";
echo json_encode($task, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
