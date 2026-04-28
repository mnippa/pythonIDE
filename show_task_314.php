<?php
require 'config/database.php';

$db = getDbConnection();
$r = $db->query('SELECT id, title, task_type, problem_type, code_template, solution_code, randomizer_code, test_cases, description, stoff FROM tasks WHERE id=314');
$row = $r->fetch_assoc();

if (!$row) {
    echo 'TASK 314 NOT_FOUND';
    exit;
}

echo "=== TASK 314 (NEW - Intelligent Vars) ===\n";
echo "ID: " . $row['id'] . "\n";
echo "TITLE: " . $row['title'] . "\n";
echo "TASK_TYPE: " . $row['task_type'] . "\n";
echo "PROBLEM_TYPE: " . $row['problem_type'] . "\n";
echo "\n=== CODE_TEMPLATE ===\n";
echo $row['code_template'] . "\n";
echo "\n=== SOLUTION_CODE ===\n";
echo $row['solution_code'] . "\n";
echo "\n=== RANDOMIZER_CODE ===\n";
echo $row['randomizer_code'] . "\n";
echo "\n=== TEST_CASES ===\n";
echo $row['test_cases'] . "\n";
