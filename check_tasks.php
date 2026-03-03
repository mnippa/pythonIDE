<?php
require 'config/database.php';
$pdo = getPdoConnection();

echo "Task #21 Details:\n";
echo "================\n";

$stmt = $pdo->prepare('SELECT id, title, task_type, solution_code FROM tasks WHERE id = 21');
$stmt->execute();
$task = $stmt->fetch(PDO::FETCH_ASSOC);

if ($task) {
    echo "✅ Task found!\n";
    echo "ID: " . $task['id'] . "\n";
    echo "Title: " . $task['title'] . "\n";
    echo "Type: " . $task['task_type'] . "\n";
    echo "Solution: " . (strlen($task['solution_code']) > 50 ? substr($task['solution_code'], 0, 50) . "..." : $task['solution_code']) . "\n";
} else {
    echo "❌ Task not found\n";
}
?>



