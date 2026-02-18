<?php
require 'config/database.php';
$conn = getDbConnection();

$sql = "SELECT id, title, task_type, code_template, solution_code, variable_overrides, correct_answer 
        FROM tasks WHERE id = 74";
$result = $conn->query($sql);
$task = $result->fetch_assoc();

if ($task) {
    echo "Task #74: {$task['title']}\n";
    echo "Type: {$task['task_type']}\n\n";
    
    echo "code_template:\n";
    echo $task['code_template'] . "\n\n";
    
    echo "solution_code:\n";
    echo $task['solution_code'] . "\n\n";
    
    echo "correct_answer: {$task['correct_answer']}\n";
    echo "variable_overrides: {$task['variable_overrides']}\n";
}
?>
