<?php
$pdo = new PDO('mysql:host=localhost;dbname=pythonide', 'root', 'start123', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$stmt = $pdo->prepare('SELECT id, assignment_id, title, task_type, task_difficulty FROM tasks WHERE id = :id LIMIT 1');
$stmt->execute([':id' => 307]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$lines = [];

if (!$rows) {
    $lines[] = "NOT_FOUND";
    file_put_contents(__DIR__ . '/tmp_check_static_task.txt', implode("\n", $lines) . "\n");
    echo implode("\n", $lines) . "\n";
    exit;
}

foreach ($rows as $r) {
    $lines[] = "ID=" . $r['id'] . " | assignment_id=" . $r['assignment_id'] . " | task_type=" . $r['task_type'] . " | task_difficulty=" . $r['task_difficulty'] . " | title=" . $r['title'];
}

file_put_contents(__DIR__ . '/tmp_check_static_task.txt', implode("\n", $lines) . "\n");
echo implode("\n", $lines) . "\n";
?>
