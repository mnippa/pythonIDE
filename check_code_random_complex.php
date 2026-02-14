<?php
require_once __DIR__ . '/config/database.php';
$conn = getDbConnection();

echo "=== code_random_complex Tasks ===\n";
$result = $conn->query("SELECT id, title, show_solution, solution_code IS NOT NULL as has_solution FROM tasks WHERE task_type='code_random_complex'");

while($task = $result->fetch_assoc()) {
    printf("Task %d: %s\n", $task['id'], $task['title']);
    printf("  show_solution = %d\n", $task['show_solution']);
    printf("  has solution_code = %d\n\n", $task['has_solution']);
}

$conn->close();
