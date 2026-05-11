<?php
require_once __DIR__ . '/config/database.php';
$conn = getDbConnection();

$res = $conn->query("SELECT pf.folder_id, f.name AS folder_name, pf.name AS file_name, LEFT(pf.content, 220) AS preview
FROM project_files pf
JOIN project_folders f ON f.id = pf.folder_id
WHERE pf.project_id = 48 AND pf.folder_id IN (123, 124)
ORDER BY pf.folder_id, pf.name");

$current = null;
while ($row = $res->fetch_assoc()) {
    if ($current !== $row['folder_id']) {
        $current = $row['folder_id'];
        echo "\n=== Folder {$row['folder_id']} {$row['folder_name']} ===\n";
    }
    echo "- {$row['file_name']}: " . str_replace("\n", " ", $row['preview']) . "\n";
}
