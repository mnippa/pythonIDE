<?php
require 'config/database.php';
$conn = getDbConnection();

// Get all code_random_complex tasks with their fields
$sql = "SELECT id, title, code_template, solution_code, generator_code, show_generator_code 
         FROM tasks 
         WHERE task_type = 'code_random_complex'
         LIMIT 5";
$result = $conn->query($sql);

echo "=== code_random_complex Tasks Analysis ===\n\n";

while ($task = $result->fetch_assoc()) {
    echo "Task ID {$task['id']}: {$task['title']}\n";
    echo str_repeat("-", 50) . "\n";
    
    echo "show_generator_code: " . ($task['show_generator_code'] ? 'YES' : 'NO') . "\n\n";
    
    echo "code_template:\n";
    if ($task['code_template']) {
        echo substr($task['code_template'], 0, 300) . "\n\n";
    } else {
        echo "(NULL)\n\n";
    }
    
    echo "solution_code:\n";
    if ($task['solution_code']) {
        echo substr($task['solution_code'], 0, 300) . "\n\n";
    } else {
        echo "(NULL)\n\n";
    }
    
    echo "generator_code:\n";
    if ($task['generator_code']) {
        echo substr($task['generator_code'], 0, 300) . "\n\n";
    } else {
        echo "(NULL)\n\n";
    }
    
    echo "\n";
}
?>
