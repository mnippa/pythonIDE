<?php
// Check if there are any other CODE_RANDOM_COMPLEX tasks
require_once 'config/database.php';
$conn = getDbConnection();

$result = $conn->query("
    SELECT id, assignment_id, title, task_type, randomizer_code 
    FROM tasks 
    WHERE task_type = 'code_random_complex' 
    ORDER BY id
");

$count = 0;
while ($row = $result->fetch_assoc()) {
    $count++;
    echo "Task {$row['id']} (Assignment {$row['assignment_id']}): {$row['title']}\n";
    // Show first 100 chars of randomizer
    $snippet = substr($row['randomizer_code'], 0, 100);
    echo "  Randomizer: " . (strlen($row['randomizer_code']) > 100 ? $snippet . "..." : $snippet) . "\n";
}

echo "\nTotal CODE_RANDOM_COMPLEX tasks: $count\n";
?>
