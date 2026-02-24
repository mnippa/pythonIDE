<?php
require_once 'config/database.php';
$conn = getDbConnection();

// Verify Assignment 22 exists
$result = $conn->query('SELECT id, title, description FROM assignments WHERE id >= 20 ORDER BY id');
echo "Remaining Assignments (id >= 20):\n";
while ($row = $result->fetch_assoc()) {
    echo "  ID {$row['id']}: {$row['title']}\n";
}

// Count remaining tasks
$result = $conn->query('SELECT task_type, COUNT(*) as cnt FROM tasks GROUP BY task_type ORDER BY task_type');
echo "\nRemaining Tasks by Type:\n";
while ($row = $result->fetch_assoc()) {
    echo "  {$row['task_type']}: {$row['cnt']}\n";
}

// Check total assignments
$result = $conn->query('SELECT COUNT(*) as cnt FROM assignments');
$row = $result->fetch_assoc();
echo "\nTotal Assignments: {$row['cnt']}\n";

// Check total tasks
$result = $conn->query('SELECT COUNT(*) as cnt FROM tasks');
$row = $result->fetch_assoc();
echo "Total Tasks: {$row['cnt']}\n";
?>
