<?php
require 'config/database.php';
$conn = getDbConnection();

echo "=== SINGLE_CHOICE EXAMPLE ===\n";
$result = $conn->query('SELECT * FROM tasks WHERE task_type = "single_choice" LIMIT 1');
$row = $result->fetch_assoc();
if($row) {
    echo "Question: " . substr($row['question_text'] , 0, 100) . "\n";
    echo "Correct Answer: " . $row['correct_answer'] . "\n\n";
}

echo "=== MULTIPLE_CHOICE EXAMPLE ===\n";
$result = $conn->query('SELECT * FROM tasks WHERE task_type = "multiple_choice" LIMIT 1');
$row = $result->fetch_assoc();
if($row) {
    echo "Question: " . substr($row['question_text'], 0, 100) . "\n";
    echo "Correct Answer: " . $row['correct_answer'] . "\n\n";
}

echo "=== CODE EXAMPLE ===\n";
$result = $conn->query('SELECT * FROM tasks WHERE task_type = "code" LIMIT 1');
$row = $result->fetch_assoc();
if($row) {
    echo "Question: " . substr($row['question_text'], 0, 100) . "\n";
    echo "Correct Answer: " . $row['correct_answer'] . "\n";
}
?>
