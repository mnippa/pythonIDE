<?php
require_once __DIR__ . '/config/database.php';

$conn = getDbConnection();

echo "=== Tasks in Assignment 21 ===\n";
$result = $conn->query("
    SELECT id, title, task_type, folderstructure, allow_code_ui_web_edit
    FROM tasks 
    WHERE assignment_id = 21
    ORDER BY position
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $folder_exists = is_dir(__DIR__ . "/storage/tasks/folders/task_{$row['id']}") ? "✓" : "✗";
        echo "{$folder_exists} Task #{$row['id']}: {$row['title']}\n";
        echo "   Type: {$row['task_type']} | Folder: {$row['folderstructure']} | WebEdit: {$row['allow_code_ui_web_edit']}\n\n";
    }
} else {
    echo "Query failed: " . $conn->error . "\n";
}

$conn->close();
