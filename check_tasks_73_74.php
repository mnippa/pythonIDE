<?php
require_once __DIR__ . '/config/database.php';
$conn = getDbConnection();

$stmt = $conn->prepare('SELECT id, title, task_type, code_template, variable_overrides FROM tasks WHERE id IN (73, 74)');
$stmt->execute();
$r = $stmt->get_result();

while($row = $r->fetch_assoc()) {
    echo 'Task ' . $row['id'] . ': ' . $row['title'] . ' (' . $row['task_type'] . ')' . PHP_EOL;
    echo 'Template: ' . substr($row['code_template'], 0, 200) . '...' . PHP_EOL;
    echo 'Overrides: ' . $row['variable_overrides'] . PHP_EOL;
    echo PHP_EOL;
}
