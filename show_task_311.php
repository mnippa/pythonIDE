<?php
require 'config/database.php';

$db = getDbConnection();
$r = $db->query('
    SELECT t.id, t.title, t.task_type, t.problem_type, t.code_template, t.solution_code, 
           t.test_cases, t.description, t.randomizer_code, t.folderstructure, t.assignment_id,
           a.title as assignment_title
    FROM tasks t 
    JOIN assignments a ON t.assignment_id = a.id 
    WHERE t.id = 311
');

$row = $r->fetch_assoc();
echo "ID: " . $row['id'] . "\n";
echo "TITLE: " . $row['title'] . "\n";
echo "FOLDERSTRUCTURE: " . $row['folderstructure'] . "\n";
echo "ASSIGNMENT: " . $row['assignment_id'] . " - " . $row['assignment_title'] . "\n";
echo "\n=== CODE_TEMPLATE ===\n" . $row['code_template'] . "\n";
echo "\n=== SOLUTION_CODE ===\n" . $row['solution_code'] . "\n";
echo "\n=== TEST_CASES ===\n" . $row['test_cases'] . "\n";
echo "\n=== DESCRIPTION ===\n" . $row['description'] . "\n";
