<?php
// Simulate the API request as if it comes from the frontend
require 'config/database.php';
$conn = getDbConnection();

// Simulate test_mode parameter
$_GET['test_mode'] = 1;
$test_mode = isset($_GET['test_mode']) ? intval($_GET['test_mode']) : 0;

echo "Test Mode: " . ($test_mode ? 'ON' : 'OFF') . "\n\n";

// Fetch Task 79 with the same logic as the API
$columns = "id, title, task_type, question_text, difficulty, max_attempts, user_id";

if ($test_mode) {
    $columns .= ", solution_code, generator_code";
}

$sql = "SELECT $columns FROM tasks WHERE id = 79";
echo "SQL Query: $sql\n\n";

$result = $conn->query($sql);
if (!$result) {
    echo "ERROR: " . $conn->error;
    die();
}

$task = $result->fetch_assoc();

if ($task) {
    echo "✓ Task 79 fetched successfully\n";
    echo "solution_code included: " . (isset($task['solution_code']) ? 'YES' : 'NO') . "\n";
    echo "generator_code included: " . (isset($task['generator_code']) ? 'YES' : 'NO') . "\n\n";
    
    if (isset($task['solution_code'])) {
        echo "solution_code content (first 150 chars):\n";
        echo substr($task['solution_code'], 0, 150) . "...\n";
    }
} else {
    echo "Task not found";
}
?>
