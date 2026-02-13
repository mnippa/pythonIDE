<?php
require_once __DIR__ . '/config/database.php';

$conn = getDbConnection();

echo "=== Tasks Table Structure ===\n";
$result = $conn->query("SHOW COLUMNS FROM tasks");
while ($row = $result->fetch_assoc()) {
    if (in_array($row['Field'], ['task_type', 'question_text', 'image_url', 'correct_answer', 'variable_overrides'])) {
        echo sprintf("%-20s | %-40s\n", $row['Field'], $row['Type']);
    }
}

echo "\n=== task_options Table ===\n";
$check = $conn->query("SHOW TABLES LIKE 'task_options'");
if ($check->num_rows > 0) {
    echo "✓ Table exists\n";
    $result = $conn->query("SHOW COLUMNS FROM task_options");
    while ($row = $result->fetch_assoc()) {
        echo sprintf("%-20s | %-40s\n", $row['Field'], $row['Type']);
    }
} else {
    echo "✗ Table does NOT exist\n";
}

echo "\n=== user_tasks Extensions ===\n";
$result = $conn->query("SHOW COLUMNS FROM user_tasks");
while ($row = $result->fetch_assoc()) {
    if (in_array($row['Field'], ['selected_options', 'text_answer', 'variable_values'])) {
        echo sprintf("%-20s | %-40s\n", $row['Field'], $row['Type']);
    }
}
