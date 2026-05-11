<?php
require_once __DIR__ . '/config/database.php';
$conn = getDbConnection();

$res = $conn->query("SELECT f.id AS folder_id, f.name AS folder_name, pf.name AS file_name, pf.content
FROM project_folders f
JOIN project_files pf ON pf.folder_id = f.id AND pf.project_id = 48
WHERE f.project_id = 48 AND f.id IN (119,120,121,122,123,124)
ORDER BY f.id, pf.name");

$current = null;
while ($row = $res->fetch_assoc()) {
    if ($current !== $row['folder_id']) {
        $current = $row['folder_id'];
        echo "\n\n===== FOLDER {$row['folder_id']} {$row['folder_name']} =====\n";
    }
    echo "\n--- FILE {$row['file_name']} ---\n";
    echo $row['content'] . "\n";
}
