<?php
$pdo = new PDO('mysql:host=localhost;dbname=pythonide', 'root', 'start123', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$stmt = $pdo->query("SHOW COLUMNS FROM tasks LIKE 'task_difficulty'");
$row = $stmt->fetch(PDO::FETCH_ASSOC);

$out = $row ? json_encode($row, JSON_UNESCAPED_SLASHES) : 'NOT_FOUND';
file_put_contents(__DIR__ . '/tmp_get_task_difficulty.txt', $out . "\n");
echo $out;
?>
