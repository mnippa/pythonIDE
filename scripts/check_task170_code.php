<?php
$conn = new mysqli('localhost', 'root', 'start123', 'pythonide');
$conn->set_charset('utf8mb4');

// Get Task 170 solution code
$stmt = $conn->prepare('SELECT id, title, solution_code FROM tasks WHERE id = 170');
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();

echo "Task 170 Title: " . $task['title'] . "\n";
echo "Solution Code (first 500 chars):\n";
echo substr($task['solution_code'], 0, 500) . "\n";
