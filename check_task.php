<?php
require_once 'config/database.php';

$conn = getDbConnection();
$stmt = $conn->prepare('SELECT id, title, test_cases FROM tasks WHERE id = 102');
$stmt->execute();
$result = $stmt->get_result();

while($row = $result->fetch_assoc()) {
    echo 'ID: ' . $row['id'] . PHP_EOL;
    echo 'Title: ' . $row['title'] . PHP_EOL;
    echo 'Test Cases Length: ' . strlen($row['test_cases']) . PHP_EOL;
    echo 'Test Cases: ' . ($row['test_cases'] ?: 'NULL/EMPTY') . PHP_EOL;
}
