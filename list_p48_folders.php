<?php
require_once __DIR__ . '/config/database.php';
$conn = getDbConnection();

// List folders
$result = $conn->query('SELECT id, name, sort_order FROM project_folders WHERE project_id=48 ORDER BY sort_order');
while ($row = $result->fetch_assoc()) {
    echo $row['id'] . ' | ' . $row['sort_order'] . ' | ' . $row['name'] . "\n";
}
