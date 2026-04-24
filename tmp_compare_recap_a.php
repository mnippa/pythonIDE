<?php
$pdo = new PDO('mysql:host=localhost;dbname=pythonide', 'root', 'start123', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$sql = "SELECT a.id AS assignment_id, a.title AS assignment_title, t.id, t.position, t.title, t.task_type, t.problem_type, t.solution_code, t.randomizer_code, t.test_cases
        FROM assignments a
        JOIN tasks t ON t.assignment_id = a.id
        WHERE a.title = 'A) Recap Theorie'
        ORDER BY t.position ASC";

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$lines = [];
if (!$rows) {
    $lines[] = 'NO_TASKS_FOUND';
} else {
    $lines[] = 'ASSIGNMENT_ID=' . $rows[0]['assignment_id'] . ' | TITLE=' . $rows[0]['assignment_title'];
    foreach ($rows as $r) {
        $lines[] = '---';
        $lines[] = 'TASK_ID=' . $r['id'] . ' | POS=' . $r['position'] . ' | TYPE=' . $r['task_type'] . ' | PROBLEM=' . $r['problem_type'];
        $lines[] = 'TITLE=' . str_replace(["\r", "\n"], [' ', ' '], (string)$r['title']);
        $lines[] = 'SOLUTION=' . str_replace(["\r", "\n"], ['\\r', '\\n'], (string)$r['solution_code']);
        $lines[] = 'RANDOMIZER=' . str_replace(["\r", "\n"], ['\\r', '\\n'], (string)$r['randomizer_code']);
        $lines[] = 'TEST_CASES=' . str_replace(["\r", "\n"], [' ', ' '], (string)$r['test_cases']);
    }
}

file_put_contents(__DIR__ . '/tmp_compare_recap_a.txt', implode("\n", $lines) . "\n");
echo 'OK';
?>