<?php
require_once __DIR__ . '/../config/database.php';

$db = getDbConnection();
$taskId = 79;

$sql = "SELECT id, title, task_type, question_text, code_template, solution_code, correct_answer, variable_overrides, test_cases, validation_mode FROM tasks WHERE id = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param('i', $taskId);
$stmt->execute();
$result = $stmt->get_result();
$task = $result->fetch_assoc();

if (!$task) {
    echo "Task $taskId not found\n";
    exit(1);
}

echo "Task #$taskId: {$task['title']}\n";
echo str_repeat("=", 70) . "\n\n";

echo "Task Type: {$task['task_type']}\n";
echo "Question Text:\n" . ($task['question_text'] ?: 'EMPTY') . "\n\n";

echo "Code Template:\n";
echo str_repeat("-", 70) . "\n";
echo $task['code_template'] ?: 'EMPTY';
echo "\n" . str_repeat("-", 70) . "\n\n";

echo "Solution Code:\n";
echo str_repeat("-", 70) . "\n";
echo $task['solution_code'] ?: 'EMPTY';
echo "\n" . str_repeat("-", 70) . "\n\n";

echo "Correct Answer (variable name): " . ($task['correct_answer'] ?: 'EMPTY') . "\n\n";

echo "Variable Overrides:\n";
echo str_repeat("-", 70) . "\n";
echo $task['variable_overrides'] ?: 'EMPTY';
echo "\n" . str_repeat("-", 70) . "\n\n";

echo "Test Cases: " . ($task['test_cases'] ?: 'EMPTY') . "\n";
echo "Validation Mode: " . ($task['validation_mode'] ?: 'EMPTY') . "\n";

$db->close();
