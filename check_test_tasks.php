<?php
require 'config/database.php';
$conn = getDbConnection();

echo "Checking Code Tasks 47 and 50:\n\n";

foreach ([47, 50] as $taskId) {
    $sql = "SELECT id, title, task_type, assignment_id, solution_code FROM tasks WHERE id = $taskId";
    $result = $conn->query($sql);
    $task = $result->fetch_assoc();
    
    if ($task) {
        echo "Task $taskId: {$task['title']}\n";
        echo "  Type: {$task['task_type']}\n";
        echo "  Assignment: {$task['assignment_id']}\n";
        echo "  solution_code: " . ($task['solution_code'] ? 'YES (' . strlen($task['solution_code']) . ' chars)' : 'NO') . "\n";
        echo "  Status: " . ($task['solution_code'] ? 'Ready' : 'Missing') . "\n\n";
    }
}
?>
