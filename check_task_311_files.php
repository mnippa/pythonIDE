<?php
require 'config/database.php';

$db = getDbConnection();

// Check task_files table
$r = $db->query('SELECT * FROM task_files WHERE task_id = 311');
if (!$r) {
    echo "table task_files fehlt oder Fehler: " . $db->error . "\n";
} else {
    $rows = [];
    while ($row = $r->fetch_assoc()) $rows[] = $row;
    if (empty($rows)) {
        echo "Keine Einträge in task_files für task_id=311\n";
    } else {
        foreach ($rows as $row) {
            echo print_r($row, true) . "\n";
        }
    }
}

// Check storage/tasks/folders for task 311
$folderPath = __DIR__ . '/storage/tasks/folders/311';
echo "\nFolder: $folderPath\n";
if (is_dir($folderPath)) {
    foreach (scandir($folderPath) as $f) {
        if ($f === '.' || $f === '..') continue;
        echo "  FILE: $f\n";
    }
} else {
    echo "  → Ordner existiert NICHT\n";
}
