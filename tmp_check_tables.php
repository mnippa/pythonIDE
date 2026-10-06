<?php
$pdo = new PDO('mysql:host=localhost;dbname=pythonide;charset=utf8mb4', 'root', 'start123');
$tables = ['assignment_tasks', 'tasks', 'assignments'];
foreach ($tables as $table) {
    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
    echo $table . ':' . ($stmt->rowCount() > 0 ? 'exists' : 'missing') . PHP_EOL;
}
