<?php
$pdo = new PDO('mysql:host=localhost;dbname=pythonide', 'root', 'start123', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$stmt = $pdo->prepare('SELECT id, assignment_id, title, task_type, problem_type, correct_answer, code_template, solution_code, randomizer_code, test_cases, variable_overrides FROM tasks WHERE id = :id LIMIT 1');
$stmt->execute([':id' => 310]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);

$lines = [];
if (!$r) {
    $lines[] = 'NOT_FOUND';
} else {
    foreach ($r as $k => $v) {
        $val = str_replace(["\r", "\n"], ['\\r', '\\n'], (string)$v);
        $lines[] = strtoupper($k) . '=' . $val;
    }
}

file_put_contents(__DIR__ . '/tmp_dump_task_310.txt', implode("\n", $lines) . "\n");
echo 'OK';
?>