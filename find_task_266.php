<?php
require 'config/database.php';

$db = getDbConnection();

// Find tasks with "temperatur" in title or around id 266
$r = $db->query("
    SELECT t.id, t.title, t.folderstructure, a.title as assignment_title
    FROM tasks t 
    JOIN assignments a ON t.assignment_id = a.id 
    WHERE LOWER(t.title) LIKE '%temperatur%'
    ORDER BY t.id
");

echo "=== Tasks mit 'temperatur' ===\n";
while ($row = $r->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | folderstructure: " . $row['folderstructure'] . " | " . $row['title'] . " | Assignment: " . $row['assignment_title'] . "\n";
}

// Also show tasks around id 266
$r2 = $db->query("
    SELECT t.id, t.title, t.folderstructure
    FROM tasks t
    WHERE t.id BETWEEN 263 AND 270
    ORDER BY t.id
");
echo "\n=== Tasks ID 263-270 ===\n";
while ($row = $r2->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | folderstructure: " . $row['folderstructure'] . " | " . $row['title'] . "\n";
}
