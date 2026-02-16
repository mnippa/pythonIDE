<?php
require_once 'config/database.php';
$conn = getDbConnection();

echo "=== ASSIGNMENTS ===\n";
$result = $conn->query('SELECT id, title, description FROM assignments ORDER BY id DESC LIMIT 10');
while($row = $result->fetch_assoc()) {
    echo "ID {$row['id']}: {$row['title']}\n";
}

echo "\n=== TASK TYPES ===\n";
$result = $conn->query('SELECT DISTINCT task_type FROM tasks ORDER BY task_type');
while($row = $result->fetch_assoc()) {
    echo "- {$row['task_type']}\n";
}

echo "\n=== NEXT ASSIGNMENT ID ===\n";
$result = $conn->query('SELECT MAX(id) as max_id FROM assignments');
$row = $result->fetch_assoc();
echo 'Next ID: ' . ($row['max_id'] + 1) . "\n";

echo "\n=== SAMPLE TASK STRUCTURE (single_choice) ===\n";
$result = $conn->query('SELECT id, title, task_type, question_text, correct_answer FROM tasks WHERE task_type = "single_choice" LIMIT 1');
$row = $result->fetch_assoc();
if($row) {
    foreach($row as $k => $v) {
        echo "$k: " . substr($v, 0, 80) . "\n";
    }
}
?>
