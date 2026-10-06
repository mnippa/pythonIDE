<?php
require_once __DIR__ . '/../config/database.php';
$db = getDbConnection();
$r = $db->query('SELECT a.id AS assignment_id, a.title AS assignment_title, t.id AS task_id, t.title AS task_title, t.position, t.code_template, t.solution_code, t.test_cases FROM assignments a INNER JOIN tasks t ON t.assignment_id = a.id WHERE a.id = 34 AND t.id = 380 LIMIT 1')->fetch_assoc();
if (!$r) {
    echo "not_found\n";
    exit(1);
}
echo 'assignment=' . $r['assignment_id'] . ' ' . $r['assignment_title'] . PHP_EOL;
echo 'task=' . $r['task_id'] . ' ' . $r['task_title'] . ' pos=' . $r['position'] . PHP_EOL;
echo 'template_has_solution_call=' . (strpos($r['code_template'], 'print(ergebnis)') !== false || strpos($r['code_template'], 'geschwindkeit(') !== false ? 'yes' : 'no') . PHP_EOL;
echo 'solution_has_top_level_call=' . (strpos($r['solution_code'], 'print(ergebnis)') !== false ? 'yes' : 'no') . PHP_EOL;
echo 'test_cases=' . $r['test_cases'] . PHP_EOL;
