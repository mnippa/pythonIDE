<?php
require 'config/database.php';

$db = getDbConnection();
$r = $db->query('SELECT id, title, task_type, problem_type, code_template, solution_code, test_cases, description, stoff FROM tasks WHERE id=312');
$row = $r->fetch_assoc();

if (!$row) {
    echo 'NOT_FOUND';
    exit;
}

echo "=== TASK 312 ===\n";
echo "ID: " . $row['id'] . "\n";
echo "TITLE: " . $row['title'] . "\n";
echo "TASK_TYPE: " . $row['task_type'] . "\n";
echo "PROBLEM_TYPE: " . $row['problem_type'] . "\n";
echo "\n=== CODE_TEMPLATE ===\n";
echo $row['code_template'] . "\n";
echo "\n=== SOLUTION_CODE ===\n";
echo $row['solution_code'] . "\n";
echo "\n=== DESCRIPTION ===\n";
echo $row['description'] . "\n";
echo "\n=== STOFF ===\n";
echo $row['stoff'] . "\n";
echo "\n=== TEST_CASES ===\n";
echo $row['test_cases'] . "\n";
