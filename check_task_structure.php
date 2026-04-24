<?php
// Simple Task 4 insert - check what fields are actually needed
$pdo = new PDO(
    'mysql:host=localhost;dbname=pythonide',
    'root',
    'start123'
);

// First, let's check what tasks 303, 304, 305 look like
$sql = "SELECT * FROM tasks WHERE assignment_id = 29 ORDER BY id DESC LIMIT 3";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Existing Assignment 29 tasks:\n";
foreach ($results as $task) {
    echo "\n=== Task ID " . $task['id'] . " ===\n";
    echo "Title: " . $task['title'] . "\n";
    echo "Task Type: " . $task['task_type'] . "\n";
    echo "Problem Type: " . $task['problem_type'] . "\n";
    echo "Has code_template: " . (strlen($task['code_template'] ?? '') > 0 ? 'YES' : 'NO') . "\n";
    echo "Has solution_code: " . (strlen($task['solution_code'] ?? '') > 0 ? 'YES' : 'NO') . "\n";
    echo "Has randomizer_code: " . (strlen($task['randomizer_code'] ?? '') > 0 ? 'YES' : 'NO') . "\n";
    echo "Has test_cases: " . (strlen($task['test_cases'] ?? '') > 0 ? 'YES' : 'NO') . "\n";
    echo "Iterations count: " . ($task['iterations_count'] ?? 'NULL') . "\n";
    echo "Max attempts: " . ($task['max_attempts'] ?? 'NULL') . "\n";
}
?>
