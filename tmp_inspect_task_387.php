<?php
$pdo = new PDO('mysql:host=localhost;dbname=pythonide;charset=utf8mb4', 'root', 'start123');
$stmt = $pdo->query("SELECT id, title, task_type, task_text FROM tasks WHERE id=387");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo json_encode($row, JSON_UNESCAPED_UNICODE);
