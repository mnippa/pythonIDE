<?php
require_once __DIR__ . '/config/database.php';
$conn = getDbConnection();
$result = $conn->query('SELECT name, content FROM project_files WHERE project_id=48 AND folder_id=121 ORDER BY name');
while ($row = $result->fetch_assoc()) {
    echo '=== ' . $row['name'] . " ===\n" . $row['content'] . "\n\n";
}
