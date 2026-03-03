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
$stmt = $conn->prepare('SELECT id, folderstructure, code_template, task_type, allow_code_ui_web_edit FROM tasks WHERE id = ?');
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

$folderPath = __DIR__ . '/../../storage/tasks/folders/task_' . $taskId;
$isCodeUiTask = ($task['task_type'] ?? '') === 'code_ui';
$allowStudentWebEdit = (int)($task['allow_code_ui_web_edit'] ?? 1) === 1;

$loadPolicies = function (string $baseFolderPath): array {
    $policyPath = $baseFolderPath . '/.file-policies.json';
    if (!is_file($policyPath)) {
        return ['files' => []];
    }

    $raw = file_get_contents($policyPath);
    if ($raw === false || trim($raw) === '') {
        return ['files' => []];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['files' => []];
    }

    if (!isset($decoded['files']) || !is_array($decoded['files'])) {
        $decoded['files'] = [];
    }

    return $decoded;
};

$resolveReadOnly = function (string $relativePath, array $policies, bool $codeUiTask, bool $studentWebEdit): bool {
    $normalized = ltrim(str_replace('\\', '/', $relativePath), '/');

    $readOnly = false;
    if ($codeUiTask) {
        if ($normalized === 'ui-runtime.readonly.js') {
            $readOnly = true;
        }
        if (!$studentWebEdit && ($normalized === 'index.html' || $normalized === 'style.css')) {
            $readOnly = true;
        }
    }

    if (isset($policies['files'][$normalized]) && is_array($policies['files'][$normalized]) && array_key_exists('read_only', $policies['files'][$normalized])) {
        $readOnly = (bool)$policies['files'][$normalized]['read_only'];
    }

    return $readOnly;
};

$policies = $loadPolicies($folderPath);

// Add virtual init.py file
$files[] = [
    'name' => 'init.py',
    'type' => 'file',
    'virtual' => true,
    'content' => $task['code_template'] ?? '',
    'path' => 'init.py',
    'read_only' => false
];

// List real files and folders from folder (recursive)
$scanDirectoryWithPolicy = function ($dir, $basePath = '') use (&$scanDirectoryWithPolicy, $resolveReadOnly, $policies, $isCodeUiTask, $allowStudentWebEdit) {
    $items = [];
    
    if (!is_dir($dir)) {
        return $items;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    
    foreach ($files as $file) {
        if (substr($file, 0, 1) === '.') {
            continue;
        }

        $filePath = $dir . '/' . $file;
        $relativePath = $basePath ? $basePath . '/' . $file : $file;

        if (is_dir($filePath)) {
            $items[] = [
                'name' => $file,
                'type' => 'folder',
                'virtual' => false,
                'path' => $relativePath,
                'children' => $scanDirectoryWithPolicy($filePath, $relativePath)
            ];
        } else {
            $items[] = [
                'name' => $file,
                'type' => 'file',
                'virtual' => false,
                'size' => filesize($filePath),
                'path' => $relativePath,
                'read_only' => $resolveReadOnly($relativePath, $policies, $isCodeUiTask, $allowStudentWebEdit)
            ];
        }
    }
    
    return $items;
};

if (is_dir($folderPath)) {
    $realItems = $scanDirectoryWithPolicy($folderPath);
    $files = array_merge($files, $realItems);
}

jsonResponse([
    'ok' => true,
    'files' => $files
]);

?>
