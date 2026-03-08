<?php
require_once __DIR__ . '/config/database.php';

$conn = getDbConnection();

// Suche nach dynamischen UI Tasks
echo "=== Tasks mit 'dynamisch' oder 'idegui' im Titel ===\n";
$result = $conn->query("
    SELECT id, title, task_type, folderstructure 
    FROM tasks 
    WHERE title LIKE '%dynamisch%' OR title LIKE '%idegui%' OR title LIKE '%Dynamisch%'
    ORDER BY id
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Task #{$row['id']}: {$row['title']} | Type: {$row['task_type']} | Folder: {$row['folderstructure']}\n";
    }
} else {
    echo "Keine Tasks gefunden.\n";
}

// Zeige alle Tasks mit folderstructure=1
echo "\n=== Alle Tasks mit Ordnerstruktur (folderstructure=1) ===\n";
$result = $conn->query("
    SELECT id, title, task_type, folderstructure 
    FROM tasks 
    WHERE folderstructure = 1
    ORDER BY id DESC
    LIMIT 15
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $folder = "storage/tasks/folders/task_{$row['id']}";
        $exists = is_dir($folder) ? "✓" : "✗";
        echo "{$exists} Task #{$row['id']}: {$row['title']} | Type: {$row['task_type']}\n";
    }
} else {
    echo "Keine Tasks mit folderstructure gefunden.\n";
}

$conn->close();
