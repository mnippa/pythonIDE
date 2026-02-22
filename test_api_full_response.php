<?php
/**
 * Test: API Response for Assignment 12 in test mode
 */
require 'config/database.php';
$conn = getDbConnection();

echo "=== API Response Test (Test Mode) ===\n\n";

// Simulate the API call with test_mode parameter
$assignmentId = 12;
$test_mode = true;

// Build the SQL like the API does
$selectColumns = 'id, assignment_id, title, description, position, task_type, question_text';

if ($test_mode) {
    $selectColumns .= ', solution_code';
}

$sql = "SELECT $selectColumns FROM tasks WHERE assignment_id = ? ORDER BY position ASC";

echo "SQL: $sql\n\n";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo "ERROR: " . $conn->error . "\n";
    die();
}

$stmt->bind_param('i', $assignmentId);
$stmt->execute();
$result = $stmt->get_result();

$taskCount = 0;
while ($task = $result->fetch_assoc()) {
    $taskCount++;
    if (in_array($task['id'], [47, 50, 79])) {
        echo "Task {$task['id']}: {$task['title']}\n";
        echo "  Type: {$task['task_type']}\n";
        echo "  solution_code: " . (isset($task['solution_code']) && $task['solution_code'] ? 'YES' : 'NO') . "\n";
        if (isset($task['solution_code']) && $task['solution_code']) {
            echo "  Preview: " . substr($task['solution_code'], 0, 50) . "...\n";
        }
        echo "\n";
    }
}

echo "Total tasks in assignment: $taskCount\n";

// Now simulate JSON response
echo "\n=== Simulated JSON Response (tasks) ===\n";
$stmt->execute();
$result = $stmt->get_result();
$tasks = [];
while ($row = $result->fetch_assoc()) {
    if (in_array($row['id'], [47, 50, 79])) {
        $tasks[] = $row;
    }
}
echo json_encode(['tasks' => $tasks], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
