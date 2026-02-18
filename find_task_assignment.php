<?php
require 'config/database.php';
$conn = getDbConnection();

$sql = "SELECT id, assignment_id, title FROM tasks WHERE id = 79";
$result = $conn->query($sql);
$task = $result->fetch_assoc();

if ($task) {
    echo "Task 79 is in Assignment ID: " . $task['assignment_id'] . "\n";
    echo "Task Title: " . $task['title'] . "\n";
    
    // Get assignment details
    $assignSql = "SELECT id, title FROM assignments WHERE id = " . (int)$task['assignment_id'];
    $assignResult = $conn->query($assignSql);
    $assign = $assignResult->fetch_assoc();
    echo "Assignment Title: " . ($assign ? $assign['title'] : 'Not found') . "\n";
} else {
    echo "Task not found";
}
?>
