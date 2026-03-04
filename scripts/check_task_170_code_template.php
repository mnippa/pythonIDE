<?php
require_once __DIR__ . '/../config/database.php';

$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $pdo->query('SELECT id, title, LENGTH(code_template) AS code_len FROM tasks WHERE id = 170');
$row = $stmt->fetch(PDO::FETCH_ASSOC);

var_export($row);
echo PHP_EOL;
