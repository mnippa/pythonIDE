<?php
require_once __DIR__ . '/../config/database.php';

$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $pdo->prepare('SELECT id, title, task_type, position, LEFT(task_text, 400) AS task_text_preview, LEFT(hint1, 200) AS hint1_preview, LEFT(hint2, 200) AS hint2_preview, LEFT(hint3, 200) AS hint3_preview, LENGTH(code_template) AS code_len, LENGTH(solution_code) AS solution_len FROM tasks WHERE id = 169');
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
var_export($row);
echo PHP_EOL;
