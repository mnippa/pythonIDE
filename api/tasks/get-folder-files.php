<?php
/**
 * Get folder files for a task
 * Returns folder structure with files and virtual init.py
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

$user = requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$taskId = isset($_GET['task_id']) ? (int)$_GET['task_id'] : null;
if (!$taskId) {
    jsonResponse(['ok' => false, 'error' => 'Task ID required'], 400);
}

// Get task from database to check folderstructure flag and code_template
$conn = getDbConnection();
$stmt = $conn->prepare('SELECT id, folderstructure, code_template FROM tasks WHERE id = ?');
$stmt->bind_param('i', $taskId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    jsonResponse(['ok' => false, 'error' => 'Task not found'], 404);
}

$task = $result->fetch_assoc();

if (!$task['folderstructure']) {
    jsonResponse(['ok' => false, 'error' => 'Task has no folder structure'], 400);
}

$files = [];

// Add virtual init.py file
$files[] = [
    'name' => 'init.py',
    'type' => 'file',
    'virtual' => true,
    'content' => $task['code_template'] ?? '',
    'path' => 'init.py'
];

// List real files and folders from folder (recursive)
$folderPath = __DIR__ . '/../../storage/tasks/folders/task_' . $taskId;

function scanDirectory($dir, $basePath = '') {
    $items = [];
    
    if (!is_dir($dir)) {
        return $items;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    
    foreach ($files as $file) {
        $filePath = $dir . '/' . $file;
        $relativePath = $basePath ? $basePath . '/' . $file : $file;
        
        if (is_dir($filePath)) {
            $items[] = [
                'name' => $file,
                'type' => 'folder',
                'virtual' => false,
                'path' => $relativePath,
                'children' => scanDirectory($filePath, $relativePath)
            ];
        } else {
            $items[] = [
                'name' => $file,
                'type' => 'file',
                'virtual' => false,
                'size' => filesize($filePath),
                'path' => $relativePath
            ];
        }
    }
    
    return $items;
}

if (is_dir($folderPath)) {
    $realItems = scanDirectory($folderPath);
    $files = array_merge($files, $realItems);
}

jsonResponse([
    'ok' => true,
    'files' => $files
]);

?>
