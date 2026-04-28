<?php
require 'config/database.php';

$db = getDbConnection();
$db->query('UPDATE tasks SET assignment_id = 29 WHERE id = 314');
echo "✓ Task 314 in Assignment 29 verschoben\n";

// Verify
$r = $db->query('SELECT assignment_id FROM tasks WHERE id = 314');
$row = $r->fetch_assoc();
echo "Bestätigung: Task 314 ist jetzt in Assignment " . $row['assignment_id'] . "\n";
