<?php
require 'config/database.php';
$conn = getDbConnection();

echo "=== Export/Import Debug: Tasks 147, 148, 152, 153 ===\n\n";

$result = $conn->query("SELECT id, title, task_type, randomizer_code, solution_code, code_template FROM tasks WHERE id IN (147, 148, 152, 153) ORDER BY id");

while ($task = $result->fetch_assoc()) {
    echo "Task {$task['id']}: {$task['title']}\n";
    echo "  task_type: {$task['task_type']}\n";
    echo "  randomizer_code: " . ($task['randomizer_code'] ? substr($task['randomizer_code'], 0, 100) . '...' : 'NULL') . "\n";
    echo "  solution_code: " . ($task['solution_code'] ? substr($task['solution_code'], 0, 100) . '...' : 'NULL') . "\n";
    echo "  code_template: " . ($task['code_template'] ? substr($task['code_template'], 0, 100) . '...' : 'NULL') . "\n";
    echo "\n";
}

echo "=== All Unified Demo Tasks ===\n\n";
$result = $conn->query("SELECT id, assignment_id, title, task_type, randomizer_code, solution_code, code_template FROM tasks WHERE title LIKE '%Unified Demo%' ORDER BY id");
while ($task = $result->fetch_assoc()) {
    echo "Task {$task['id']} (assignment {$task['assignment_id']}): {$task['title']}\n";
    echo "  task_type: {$task['task_type']}\n";
    echo "  randomizer_code: " . ($task['randomizer_code'] ? substr($task['randomizer_code'], 0, 100) . '...' : 'NULL') . "\n";
    echo "  solution_code: " . ($task['solution_code'] ? substr($task['solution_code'], 0, 100) . '...' : 'NULL') . "\n";
    echo "  code_template: " . ($task['code_template'] ? substr($task['code_template'], 0, 100) . '...' : 'NULL') . "\n";
    echo "\n";
}
