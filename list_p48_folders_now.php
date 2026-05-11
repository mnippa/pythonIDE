<?php
require_once __DIR__ . '/config/database.php';
$conn = getDbConnection();
$res = $conn->query("SELECT id, name, parent_folder_id FROM project_folders WHERE project_id=48 ORDER BY id");
while ($row = $res->fetch_assoc()) {
    echo $row['id'] . ' | ' . $row['name'] . ' | parent=' . (is_null($row['parent_folder_id']) ? 'NULL' : $row['parent_folder_id']) . "\n";
}
