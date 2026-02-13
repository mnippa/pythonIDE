<?php
require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

echo "=== Debugging Task 63 ===\n\n";

// Check task
$result = $conn->query("SELECT id, title, task_type FROM tasks WHERE id = 63");
$task = $result->fetch_assoc();
if ($task) {
    echo "Task 63: {$task['title']} (type: {$task['task_type']})\n\n";
} else {
    echo "Task 63 not found!\n";
    exit;
}

// Check options
echo "Options:\n";
$result = $conn->query("SELECT id, option_text, is_correct FROM task_options WHERE task_id = 63 ORDER BY order_num");
while ($opt = $result->fetch_assoc()) {
    echo sprintf("  ID %d: %s (correct: %s)\n", $opt['id'], $opt['option_text'], $opt['is_correct'] ? 'YES' : 'NO');
}

// Check user_tasks for all users
echo "\nUser attempts:\n";
$result = $conn->query("SELECT user_id, status, attempts, selected_options FROM user_tasks WHERE task_id = 63");
if ($result->num_rows === 0) {
    echo "  No attempts found\n";
} else {
    while ($ut = $result->fetch_assoc()) {
        echo sprintf("  User %d: status='%s', attempts=%d, selected=[%s]\n", 
            $ut['user_id'], $ut['status'], $ut['attempts'], $ut['selected_options']);
    }
}
