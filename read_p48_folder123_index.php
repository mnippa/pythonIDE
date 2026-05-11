<?php
require_once __DIR__ . '/config/database.php';
$conn = getDbConnection();

$stmt = $conn->prepare('SELECT content FROM project_files WHERE project_id=? AND folder_id=? AND name=? LIMIT 1');
$projectId = 48;
$folderId = 123;
$name = 'index.html';
$stmt->bind_param('iis', $projectId, $folderId, $name);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
echo $row ? $row['content'] : 'NOT FOUND';
