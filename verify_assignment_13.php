<?php
require 'config/database.php';
$conn = getDbConnection();

// Check if assignment 13 exists
$result = $conn->query('SELECT id, title FROM assignments WHERE id = 13');
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "✓ Assignment " . $row['id'] . " exists: " . $row['title'] . "\n\n";
    
    // Count tasks
    $taskResult = $conn->query('SELECT COUNT(*) as cnt FROM tasks WHERE assignment_id = 13');
    $taskRow = $taskResult->fetch_assoc();
    echo "Tasks in Assignment 13: " . $taskRow['cnt'] . "\n\n";
    
    // List all tasks
    echo "=== TASK LIST ===\n";
    $tasksResult = $conn->query('SELECT id, position, title, task_type FROM tasks WHERE assignment_id = 13 ORDER BY position');
    while ($task = $tasksResult->fetch_assoc()) {
        echo $task['position'] . ". ({$task['task_type']}) {$task['title']}\n";
    }
    
    // Check options for single_choice
    echo "\n=== SINGLE_CHOICE OPTIONS ===\n";
    $optResult = $conn->query('SELECT to.task_id, to.option_text, to.is_correct FROM task_options to 
                              INNER JOIN tasks t ON t.id = to.task_id 
                              WHERE t.assignment_id = 13 AND t.task_type = "single_choice"
                              ORDER BY to.task_id, to.order_num');
    while ($opt = $optResult->fetch_assoc()) {
        $correct = $opt['is_correct'] ? '✓' : ' ';
        echo "  [$correct] Task {$opt['task_id']}: {$opt['option_text']}\n";
    }
} else {
    echo "✗ Assignment 13 not found. Creating now...\n";
    system('php create_assignment_13_fixed.php');
}
?>
