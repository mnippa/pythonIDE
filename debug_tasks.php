<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Connecting to database...\n";
$db = new mysqli('localhost', 'root', '', 'python_ide');
if ($db->connect_error) die('Connection failed: ' . $db->connect_error . "\n");

echo "Connected! Querying tasks 173, 174...\n";
$result = $db->query("SELECT id, title, task_type, folderstructure FROM tasks WHERE id IN (173, 174)");

if ($result) {
    $count = 0;
    while ($row = $result->fetch_assoc()) {
        $count++;
        echo "Task #{$row['id']}: {$row['title']} | Type: {$row['task_type']} | Folder: {$row['folderstructure']}\n";
    }
    if ($count === 0) {
        echo "No tasks found matching IDs 173, 174\n";
    }
} else {
    echo 'Query failed: ' . $db->error . "\n";
}

$db->close();
echo "Done.\n";

