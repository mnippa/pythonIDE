<?php
$pdo = new PDO('mysql:host=localhost;dbname=pythonide;charset=utf8mb4', 'root', 'start123');
$stmt = $pdo->query("SELECT at.assignment_id, at.task_id, t.title, t.task_type FROM assignment_tasks at LEFT JOIN tasks t ON t.id = at.task_id WHERE at.assignment_id = 35 ORDER BY at.position");
foreach ($stmt as $row) {
    echo $row['assignment_id'] . '|' . $row['task_id'] . '|' . $row['title'] . '|' . $row['task_type'] . PHP_EOL;
}
