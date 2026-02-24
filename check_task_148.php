<?php
require_once 'config/database.php';
$conn = getDbConnection();

// Check task 148 configuration
$result = $conn->query("SELECT id, title, solution_code, correct_answer, randomizer_code FROM tasks WHERE id = 148");
$task = $result->fetch_assoc();

echo "Task 148 Configuration:\n";
echo "Title: {$task['title']}\n";
echo "Correct Answer: {$task['correct_answer']}\n";
echo "Solution Code:\n";
echo "  {$task['solution_code']}\n";
echo "Randomizer Code:\n";
echo "  {$task['randomizer_code']}\n";
?>
