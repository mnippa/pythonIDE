<?php
require_once __DIR__ . '/config/database.php';
$conn = getDbConnection();
$res = $conn->query("SELECT name, content FROM project_files WHERE project_id=48 AND folder_id=122 AND name IN ('index.html','init.py') ORDER BY name");
while ($row = $res->fetch_assoc()) {
    echo "=== " . $row['name'] . " ===\n" . $row['content'] . "\n\n";
}
