<?php
require_once __DIR__ . '/../config/database.php';

$c = getDbConnection();

echo "=== assignment_id=32 ===\n";
$q = $c->query('SELECT id, assignment_id, position, title, task_type, folderstructure FROM tasks WHERE assignment_id=32 ORDER BY position, id');
while ($r = $q->fetch_assoc()) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

echo "\n=== search goldspiel/doppelschritt ===\n";
$q2 = $c->query("SELECT id, assignment_id, position, title, task_type, folderstructure FROM tasks WHERE title LIKE '%Doppelschritt%' OR title LIKE '%goldspiel%' OR title LIKE '%Goldspiel%' ORDER BY id DESC LIMIT 100");
while ($r = $q2->fetch_assoc()) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
