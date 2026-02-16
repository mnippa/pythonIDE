<?php
require_once __DIR__ . '/config/database.php';
$conn = getDbConnection();

$stmt = $conn->prepare('SELECT id, title, code_template, variable_overrides FROM tasks WHERE task_type = ?');
$type = 'code_reading';
$stmt->bind_param('s', $type);
$stmt->execute();
$r = $stmt->get_result();

echo "Code Reading Tasks:\n";
while($row = $r->fetch_assoc()) {
    echo "\nTask " . $row['id'] . ": " . $row['title'] . "\n";
    echo "Template:\n" . substr($row['code_template'], 0, 300) . "\n...";
    echo "\nOverrides: " . ($row['variable_overrides'] ? $row['variable_overrides'] : 'null') . "\n";
    echo "---\n";
}
