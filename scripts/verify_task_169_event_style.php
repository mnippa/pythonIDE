<?php
require_once __DIR__ . '/../config/database.php';

$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $pdo->query("SELECT id, title, LEFT(task_text, 280) AS task_text_preview, LEFT(hint1, 180) AS hint1_preview, LEFT(hint2, 180) AS hint2_preview, LEFT(hint3, 180) AS hint3_preview, LEFT(code_template, 260) AS code_preview FROM tasks WHERE id = 169");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
var_export($row);
echo PHP_EOL;
