<?php
require_once 'config/database.php';

$conn = getDbConnection();

echo "=== Current user_tasks entries ===\n\n";

$result = $conn->query("
    SELECT ut.*, t.title as task_title, u.email 
    FROM user_tasks ut
    LEFT JOIN tasks t ON ut.task_id = t.id
    LEFT JOIN users u ON ut.user_id = u.id
    ORDER BY ut.updated_at DESC
    LIMIT 10
");

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "----------------------------------------\n";
        echo "ID: " . $row['id'] . "\n";
        echo "User: " . $row['email'] . " (ID: " . $row['user_id'] . ")\n";
        echo "Task: " . $row['task_title'] . " (ID: " . $row['task_id'] . ")\n";
        echo "Status: " . $row['status'] . "\n";
        echo "Attempts: " . $row['attempts'] . "\n";
        echo "Code length: " . strlen($row['current_code']) . " chars\n";
        echo "Hints revealed: " . $row['hints_revealed'] . "\n";
        echo "Started: " . $row['started_at'] . "\n";
        echo "Completed: " . ($row['completed_at'] ?? 'NULL') . "\n";
        echo "Updated: " . $row['updated_at'] . "\n";
        echo "\nCode snippet (first 100 chars):\n";
        echo substr($row['current_code'], 0, 100) . "...\n";
    }
} else {
    echo "No entries found.\n";
}

$conn->close();
