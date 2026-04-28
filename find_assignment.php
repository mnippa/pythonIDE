<?php
require 'config/database.php';

$db = getDbConnection();
$r = $db->query('
    SELECT t.id, t.title, a.id as assignment_id, a.title as assignment_title
    FROM tasks t 
    JOIN assignments a ON t.assignment_id = a.id 
    WHERE t.id IN (312, 314)
');

while ($row = $r->fetch_assoc()) {
    echo "Task " . $row['id'] . ": " . $row['title'] . "\n";
    echo "  → Assignment " . $row['assignment_id'] . ": " . $row['assignment_title'] . "\n\n";
}
