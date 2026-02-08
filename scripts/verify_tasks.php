<?php
require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

echo "Tasks for Assignment 3:\n";
echo "========================================\n\n";

$result = $conn->query('
    SELECT 
        id, title, position, validation_mode,
        CASE 
            WHEN test_cases IS NOT NULL THEN "✓" 
            ELSE "✗" 
        END as has_tests,
        CASE 
            WHEN solution_code IS NOT NULL THEN "✓" 
            ELSE "✗" 
        END as has_solution
    FROM tasks 
    WHERE assignment_id = 3
    ORDER BY position
');

while ($row = $result->fetch_assoc()) {
    echo "Task {$row['position']}: {$row['title']}\n";
    echo "  - Validation: {$row['validation_mode']}\n";
    echo "  - Test Cases: {$row['has_tests']}\n";
    echo "  - Solution: {$row['has_solution']}\n\n";
}

// Show one example in detail
echo "========================================\n";
echo "Task 3 Details (Mehrwertsteuer):\n";
echo "========================================\n";
$result = $conn->query('
    SELECT title, description, test_cases, solution_code 
    FROM tasks 
    WHERE assignment_id = 3 AND position = 3
');

$task = $result->fetch_assoc();
echo "\nTitle: {$task['title']}\n";
echo "\nDescription:\n" . substr($task['description'], 0, 200) . "...\n";
echo "\nTest Cases:\n{$task['test_cases']}\n";
echo "\nSolution (first 150 chars):\n" . substr($task['solution_code'], 0, 150) . "...\n";

?>
