<?php
require_once __DIR__ . '/../config/database.php';

$pdo = getPdoConnection();
$stmt = $pdo->query('SELECT id, title, LEFT(solution_code, 260) AS preview FROM tasks WHERE id IN (21,169) ORDER BY id');

foreach ($stmt as $row) {
    echo 'ID ' . $row['id'] . ' | ' . $row['title'] . PHP_EOL;
    echo $row['preview'] . PHP_EOL;
    echo '---' . PHP_EOL;
}
