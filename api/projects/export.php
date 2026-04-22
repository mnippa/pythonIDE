<?php
/**
 * Export project as a single JSON file.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

$user = requireAuth();
$conn = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
if ($projectId <= 0) {
    jsonResponse(['ok' => false, 'error' => 'Project ID required'], 400);
}

$projectStmt = $conn->prepare('SELECT id, user_id, name, description, project_type, visibility, created_at, updated_at FROM projects WHERE id = ? LIMIT 1');
$projectStmt->bind_param('i', $projectId);
$projectStmt->execute();
$project = $projectStmt->get_result()->fetch_assoc();

if (!$project || ($project['user_id'] != $user['id'] && $user['role'] !== 'admin')) {
    jsonResponse(['ok' => false, 'error' => 'Access denied'], 403);
}

$folderStmt = $conn->prepare('SELECT id, parent_folder_id, name FROM project_folders WHERE project_id = ? ORDER BY id ASC');
$folderStmt->bind_param('i', $projectId);
$folderStmt->execute();
$folderResult = $folderStmt->get_result();

$folders = [];
while ($row = $folderResult->fetch_assoc()) {
    $folders[] = [
        'id' => (int)$row['id'],
        'parent_folder_id' => $row['parent_folder_id'] === null ? null : (int)$row['parent_folder_id'],
        'name' => (string)$row['name']
    ];
}

$folderPathById = buildFolderPathMap($folders);
$folderPaths = [];
foreach ($folders as $folder) {
    $fid = (int)$folder['id'];
    if (isset($folderPathById[$fid])) {
        $folderPaths[] = $folderPathById[$fid];
    }
}
$folderPaths = array_values(array_unique($folderPaths));
sort($folderPaths, SORT_NATURAL | SORT_FLAG_CASE);

$fileStmt = $conn->prepare('SELECT id, folder_id, name, content, mime_type, file_size, created_at, updated_at FROM project_files WHERE project_id = ? ORDER BY id ASC');
$fileStmt->bind_param('i', $projectId);
$fileStmt->execute();
$fileResult = $fileStmt->get_result();

$files = [];
while ($row = $fileResult->fetch_assoc()) {
    $folderPath = '';
    if ($row['folder_id'] !== null) {
        $folderId = (int)$row['folder_id'];
        $folderPath = $folderPathById[$folderId] ?? '';
    }

    $name = (string)$row['name'];
    $fullPath = $folderPath !== '' ? ($folderPath . '/' . $name) : $name;

    $files[] = [
        'path' => $fullPath,
        'content' => (string)($row['content'] ?? ''),
        'mime_type' => (string)($row['mime_type'] ?? 'text/plain'),
        'file_size' => (int)($row['file_size'] ?? strlen((string)($row['content'] ?? ''))),
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null
    ];
}

$payload = [
    'format' => 'pythonide-project-v1',
    'exported_at' => date('c'),
    'project' => [
        'id' => (int)$project['id'],
        'name' => (string)$project['name'],
        'description' => (string)($project['description'] ?? ''),
        'project_type' => (string)($project['project_type'] ?? 'python'),
        'visibility' => (string)($project['visibility'] ?? 'private'),
        'created_at' => $project['created_at'] ?? null,
        'updated_at' => $project['updated_at'] ?? null
    ],
    'folders' => $folderPaths,
    'files' => $files
];

$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    jsonResponse(['ok' => false, 'error' => 'Failed to encode export'], 500);
}

$base = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string)$project['name']);
$base = trim($base, '._-');
if ($base === '') {
    $base = 'project';
}
$downloadName = $base . '.pyideproj';

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

echo $json;
exit;

function buildFolderPathMap($folders)
{
    $byId = [];
    foreach ($folders as $f) {
        $byId[(int)$f['id']] = $f;
    }

    $paths = [];
    $remaining = true;
    $safety = 0;

    while ($remaining && $safety < 5000) {
        $remaining = false;
        $safety++;

        foreach ($byId as $id => $folder) {
            if (isset($paths[$id])) {
                continue;
            }

            $parentId = $folder['parent_folder_id'];
            if ($parentId === null) {
                $paths[$id] = (string)$folder['name'];
                continue;
            }

            $parentId = (int)$parentId;
            if (isset($paths[$parentId])) {
                $paths[$id] = $paths[$parentId] . '/' . (string)$folder['name'];
            } else {
                $remaining = true;
            }
        }
    }

    foreach ($byId as $id => $folder) {
        if (!isset($paths[$id])) {
            $paths[$id] = (string)$folder['name'];
        }
    }

    return $paths;
}
