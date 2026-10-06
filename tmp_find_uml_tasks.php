<?php
$pdo = new PDO('mysql:host=localhost;dbname=pythonide;charset=utf8mb4', 'root', 'start123');
$stmt = $pdo->query("SELECT id, title, task_type FROM tasks WHERE task_type = 'uml' LIMIT 10");
foreach ($stmt as $row) {
    echo $row['id'] . '|' . $row['title'] . '|' . $row['task_type'] . PHP_EOL;
}
