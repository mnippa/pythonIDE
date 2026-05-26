<?php
require_once __DIR__ . '/../config/database.php';

$c = getDbConnection();
$q = $c->query("SELECT id, title, task_type, code_template FROM tasks WHERE title LIKE 'RC BFS %' ORDER BY id");
while ($r = $q->fetch_assoc()) {
    $tpl = (string)$r['code_template'];
    $hasValues = preg_match('/\\bvalues\\b/', $tpl) ? 1 : 0;
    $hasPlaceholder = preg_match('/\\{[a-zA-Z_][a-zA-Z0-9_]*\\}/', $tpl) ? 1 : 0;
    echo $r['id'] . ' | ' . $r['title'] . ' | hasValues=' . $hasValues . ' | hasPlaceholder=' . $hasPlaceholder . PHP_EOL;
    echo "--- template ---\n" . $tpl . "\n\n";
}
