<?php
require_once 'config/database.php';
$conn = getDbConnection();
$result = $conn->query('SELECT id, randomizer_code, solution_code FROM tasks WHERE id IN (147, 148)');
while ($row = $result->fetch_assoc()) {
    echo "Task {$row['id']}:\n";
    echo "Randomizer:\n{$row['randomizer_code']}\n\n";
    echo "Solution:\n{$row['solution_code']}\n";
    echo "---\n";
}
?>
