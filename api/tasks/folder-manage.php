<?php
/**
 * Task Folder Files Management API
 * Handles file and folder operations for task folder structures
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

$user = requireAdmin(); // Only admins can manage task files

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? null;

if (!$action) {
    jsonResponse(['ok' => false, 'error' => 'Action required'], 400);
}

$taskId = null;
if (isset($_GET['task_id'])) {
    $taskId = (int)$_GET['task_id'];
} elseif (isset($_POST['task_id'])) {
    $taskId = (int)$_POST['task_id'];
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    $taskId = isset($input['task_id']) ? (int)$input['task_id'] : null;
}

if (!$taskId) {
    jsonResponse(['ok' => false, 'error' => 'Task ID required'], 400);
}

$conn = getDbConnection();

try {
    // ============================================
    // SAVE TEMPLATE (can be called without folderstructure)
    // ============================================
    if ($action === 'save_template') {
        $input = json_decode(file_get_contents('php://input'), true);
        $content = isset($input['content']) ? $input['content'] : '';

        // Reuse centralized task access validation.
        requireAdminOwnedTask($conn, $taskId, $user);

        $stmt = $conn->prepare('UPDATE tasks SET code_template = ? WHERE id = ?');
        if (!$stmt) {
            jsonResponse(['ok' => false, 'error' => 'Database prepare failed: ' . $conn->error], 500);
        }

        $stmt->bind_param('si', $content, $taskId);
        if (!$stmt->execute()) {
            jsonResponse(['ok' => false, 'error' => 'Failed to save template: ' . $stmt->error], 500);
        }

        $affectedRows = $stmt->affected_rows;
        $stmt->close();

        jsonResponse(['ok' => true, 'message' => 'Template saved successfully', 'affected_rows' => $affectedRows]);
    }

    // For all other actions, verify task has folderstructure enabled
    $stmt = $conn->prepare('SELECT folderstructure, task_type, allow_code_ui_web_edit FROM tasks WHERE id = ?');
    $stmt->bind_param('i', $taskId);
    $stmt->execute();
    $task = $stmt->get_result()->fetch_assoc();

    if (!$task || !$task['folderstructure']) {
        jsonResponse(['ok' => false, 'error' => 'Task does not have folder structure enabled'], 400);
    }

    $folderPath = __DIR__ . '/../../storage/tasks/folders/task_' . $taskId;
    $taskType = (string)($task['task_type'] ?? '');
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

    $savePolicies = function (string $baseFolderPath, array $policies): bool {
        if (!is_dir($baseFolderPath)) {
            mkdir($baseFolderPath, 0755, true);
        }
        $policyPath = $baseFolderPath . '/.file-policies.json';
        $json = json_encode($policies, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return $json !== false && file_put_contents($policyPath, $json) !== false;
    };

    $resolveReadOnly = function (string $relativePath, array $policies) use ($taskType, $allowStudentWebEdit): bool {
        $normalized = ltrim(str_replace('\\', '/', $relativePath), '/');

        $readOnly = false;
        if ($taskType === 'code_ui') {
            if ($normalized === 'ui-runtime.readonly.js' || $normalized === 'idegui.py') {
                $readOnly = true;
            }
            if (!$allowStudentWebEdit && ($normalized === 'index.html' || $normalized === 'style.css')) {
                $readOnly = true;
            }
        }

        if (isset($policies['files'][$normalized]) && is_array($policies['files'][$normalized]) && array_key_exists('read_only', $policies['files'][$normalized])) {
            $readOnly = (bool)$policies['files'][$normalized]['read_only'];
        }

        return $readOnly;
    };

    $normalizedInputPath = function (string $path): string {
        $normalized = ltrim(str_replace('\\', '/', trim($path)), '/');
        if ($normalized === '' || strpos($normalized, '..') !== false) {
            return '';
        }
        return $normalized;
    };

    // Ensure folder exists
    if (!is_dir($folderPath)) {
        mkdir($folderPath, 0755, true);
    }

    // ============================================
    // SET READONLY FLAG
    // ============================================
    if ($action === 'set_readonly') {
        $input = json_decode(file_get_contents('php://input'), true);
        $path = $normalizedInputPath((string)($input['path'] ?? ''));
        $readOnly = (int)(bool)($input['read_only'] ?? 0) === 1;

        if ($path === '' || $path === 'init.py') {
            jsonResponse(['ok' => false, 'error' => 'Ungültiger Pfad'], 400);
        }

        $fullPath = $folderPath . '/' . $path;
        if (!is_file($fullPath)) {
            jsonResponse(['ok' => false, 'error' => 'Datei nicht gefunden'], 404);
        }

        $policies = $loadPolicies($folderPath);
        if (!isset($policies['files']) || !is_array($policies['files'])) {
            $policies['files'] = [];
        }
        if (!isset($policies['files'][$path]) || !is_array($policies['files'][$path])) {
            $policies['files'][$path] = [];
        }

        $policies['files'][$path]['read_only'] = $readOnly;
        $policies['updated_at'] = date('c');

        if (!$savePolicies($folderPath, $policies)) {
            jsonResponse(['ok' => false, 'error' => 'Readonly-Status konnte nicht gespeichert werden'], 500);
        }

        jsonResponse(['ok' => true, 'path' => $path, 'read_only' => $readOnly]);
    }

    // ============================================
    // CREATE FOLDER
    // ============================================
    if ($action === 'create_folder') {
        $input = json_decode(file_get_contents('php://input'), true);
        $name = trim($input['name'] ?? '');
        $parentPath = trim($input['parent_path'] ?? '');
        
        if (empty($name)) {
            jsonResponse(['ok' => false, 'error' => 'Folder name required'], 400);
        }
        
        if (!preg_match('/^[a-zA-Z0-9._\-äöüß ]{1,255}$/u', $name)) {
            jsonResponse(['ok' => false, 'error' => 'Invalid folder name'], 400);
        }
        
        $targetPath = $folderPath . '/' . ltrim($parentPath, '/') . '/' . $name;
        $targetPath = str_replace('//', '/', $targetPath);
        
        if (file_exists($targetPath)) {
            jsonResponse(['ok' => false, 'error' => 'Folder already exists'], 409);
        }
        
        if (!mkdir($targetPath, 0755, true)) {
            jsonResponse(['ok' => false, 'error' => 'Failed to create folder'], 500);
        }
        
        jsonResponse([
            'ok' => true,
            'folder' => [
                'name' => $name,
                'path' => ltrim($parentPath, '/') . '/' . $name,
                'type' => 'folder'
            ]
        ], 201);
    }
    
    // ============================================
    // CREATE FILE
    // ============================================
    elseif ($action === 'create_file') {
        $input = json_decode(file_get_contents('php://input'), true);
        $name = trim($input['name'] ?? '');
        $parentPath = trim($input['parent_path'] ?? '');
        $content = $input['content'] ?? '';
        
        if (empty($name)) {
            jsonResponse(['ok' => false, 'error' => 'File name required'], 400);
        }
        
        if (!preg_match('/^[a-zA-Z0-9._\-äöüß ]{1,255}$/u', $name)) {
            jsonResponse(['ok' => false, 'error' => 'Invalid file name'], 400);
        }
        
        $targetPath = $folderPath . '/' . ltrim($parentPath, '/') . '/' . $name;
        $targetPath = str_replace('//', '/', $targetPath);
        
        if (file_exists($targetPath)) {
            jsonResponse(['ok' => false, 'error' => 'File already exists'], 409);
        }
        
        if (file_put_contents($targetPath, $content) === false) {
            jsonResponse(['ok' => false, 'error' => 'Failed to create file'], 500);
        }
        
        jsonResponse([
            'ok' => true,
            'file' => [
                'name' => $name,
                'path' => ltrim($parentPath, '/') . '/' . $name,
                'type' => 'file',
                'size' => strlen($content)
            ]
        ], 201);
    }
    
    // ============================================
    // UPLOAD FILE
    // ============================================
    elseif ($action === 'upload') {
        if (!isset($_FILES['file'])) {
            jsonResponse(['ok' => false, 'error' => 'No file uploaded'], 400);
        }
        
        $file = $_FILES['file'];
        $parentPath = trim($_POST['parent_path'] ?? '');
        $fileName = basename($file['name']);
        
        // Validate file name
        if (!preg_match('/^[a-zA-Z0-9._\-äöüß ]{1,255}$/u', $fileName)) {
            jsonResponse(['ok' => false, 'error' => 'Invalid file name'], 400);
        }
        
        $targetPath = $folderPath . '/' . $parentPath . '/' . $fileName;
        $targetPath = str_replace('//', '/', $targetPath);
        
        if (file_exists($targetPath)) {
            jsonResponse(['ok' => false, 'error' => 'File already exists'], 409);
        }
        
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            jsonResponse(['ok' => false, 'error' => 'Failed to upload file'], 500);
        }
        
        jsonResponse([
            'ok' => true,
            'file' => [
                'name' => $fileName,
                'path' => $parentPath . '/' . $fileName,
                'type' => 'file',
                'size' => filesize($targetPath)
            ]
        ], 201);
    }
    
    // ============================================
    // RENAME
    // ============================================
    elseif ($action === 'rename') {
        $input = json_decode(file_get_contents('php://input'), true);
        $oldPath = trim($input['old_path'] ?? '');
        $newName = trim($input['new_name'] ?? '');
        
        if (empty($oldPath) || empty($newName)) {
            jsonResponse(['ok' => false, 'error' => 'Old path and new name required'], 400);
        }
        
        if (!preg_match('/^[a-zA-Z0-9._\-äöüß ]{1,255}$/u', $newName)) {
            jsonResponse(['ok' => false, 'error' => 'Invalid name'], 400);
        }
        
        $oldPath = $normalizedInputPath($oldPath);
        if ($oldPath === '') {
            jsonResponse(['ok' => false, 'error' => 'Invalid path'], 400);
        }

        $fullOldPath = $folderPath . '/' . $oldPath;
        
        if (!file_exists($fullOldPath)) {
            jsonResponse(['ok' => false, 'error' => 'File or folder not found at path: ' . $fullOldPath], 404);
        }
        
        $policies = $loadPolicies($folderPath);
        if (is_file($fullOldPath) && $resolveReadOnly($oldPath, $policies)) {
            jsonResponse(['ok' => false, 'error' => 'Datei ist schreibgeschützt'], 403);
        }

        $pathInfo = pathinfo($fullOldPath);
        $newPath = $pathInfo['dirname'] . '/' . $newName;
        
        if (file_exists($newPath)) {
            jsonResponse(['ok' => false, 'error' => 'Target already exists'], 409);
        }
        
        if (!rename($fullOldPath, $newPath)) {
            jsonResponse(['ok' => false, 'error' => 'Failed to rename'], 500);
        }
        
        jsonResponse([
            'ok' => true,
            'new_path' => str_replace($folderPath . '/', '', $newPath)
        ]);
    }
    
    // ============================================
    // DELETE
    // ============================================
    elseif ($action === 'delete') {
        $input = json_decode(file_get_contents('php://input'), true);
        $path = trim($input['path'] ?? '');
        
        if (empty($path)) {
            jsonResponse(['ok' => false, 'error' => 'Path required'], 400);
        }
        
        $path = $normalizedInputPath($path);
        if ($path === '') {
            jsonResponse(['ok' => false, 'error' => 'Invalid path'], 400);
        }

        $fullPath = $folderPath . '/' . $path;
        $fullPath = str_replace('//', '/', $fullPath);
        
        if (!file_exists($fullPath)) {
            jsonResponse(['ok' => false, 'error' => 'File or folder not found'], 404);
        }
        
        $policies = $loadPolicies($folderPath);
        if (is_file($fullPath) && $resolveReadOnly($path, $policies)) {
            jsonResponse(['ok' => false, 'error' => 'Datei ist schreibgeschützt'], 403);
        }

        // Recursive delete for folders
        function deleteRecursive($dir) {
            if (!is_dir($dir)) {
                return unlink($dir);
            }
            
            $files = array_diff(scandir($dir), ['.', '..']);
            foreach ($files as $file) {
                $path = $dir . '/' . $file;
                if (is_dir($path)) {
                    deleteRecursive($path);
                } else {
                    unlink($path);
                }
            }
            return rmdir($dir);
        }
        
        if (!deleteRecursive($fullPath)) {
            jsonResponse(['ok' => false, 'error' => 'Failed to delete'], 500);
        }
        
        jsonResponse(['ok' => true]);
    }
    
    // ============================================
    // READ FILE
    // ============================================
    elseif ($action === 'read') {
        $path = trim($_GET['path'] ?? '');
        
        if (!$path) {
            jsonResponse(['ok' => false, 'error' => 'Path required'], 400);
        }
        
        $fullPath = $folderPath . '/' . ltrim($path, '/');
        
        // Security check: prevent directory traversal
        $real = realpath($fullPath);
        if (!$real || strpos($real, realpath($folderPath)) !== 0) {
            jsonResponse(['ok' => false, 'error' => 'Invalid path'], 400);
        }
        
        // Only files
        if (!is_file($fullPath)) {
            jsonResponse(['ok' => false, 'error' => 'Not a file'], 400);
        }
        
        $content = file_get_contents($fullPath);
        if ($content === false) {
            jsonResponse(['ok' => false, 'error' => 'Failed to read file'], 500);
        }
        
        jsonResponse(['ok' => true, 'content' => $content]);
    }

    // READ FILE (get content for export)
    // ============================================
    elseif ($action === 'read') {
        $input = json_decode(file_get_contents('php://input'), true);
        $path = '';
        if (isset($input['path'])) {
            $path = trim((string)$input['path']);
        } elseif (isset($_POST['path'])) {
            $path = trim((string)$_POST['path']);
        } elseif (isset($_GET['path'])) {
            $path = trim((string)$_GET['path']);
        }

        if (!$path) {
            jsonResponse(['ok' => false, 'error' => 'Path required'], 400);
        }

        $path = $normalizedInputPath($path);
        if ($path === '') {
            jsonResponse(['ok' => false, 'error' => 'Invalid path'], 400);
        }

        $fullPath = $folderPath . '/' . $path;

        // Security check: prevent directory traversal
        $realFull = realpath($fullPath);
        $realFolder = realpath($folderPath);
        if (!$realFull || !$realFolder || strpos($realFull, $realFolder) !== 0) {
            jsonResponse(['ok' => false, 'error' => 'Invalid path'], 400);
        }

        if (!is_file($fullPath)) {
            jsonResponse(['ok' => false, 'error' => 'File not found'], 404);
        }

        $content = file_get_contents($fullPath);
        if ($content === false) {
            jsonResponse(['ok' => false, 'error' => 'Failed to read file'], 500);
        }

        jsonResponse([
            'ok' => true,
            'path' => $path,
            'content' => $content
        ]);
    }

    // ============================================
    // SAVE FILE (update content)
    // ============================================
    elseif ($action === 'save') {
        $input = json_decode(file_get_contents('php://input'), true);
        $path = isset($input['path']) ? trim($input['path']) : '';
        $content = isset($input['content']) ? $input['content'] : '';

        if (!$path) {
            jsonResponse(['ok' => false, 'error' => 'Path required'], 400);
        }

        $path = $normalizedInputPath($path);
        if ($path === '') {
            jsonResponse(['ok' => false, 'error' => 'Invalid path'], 400);
        }

        $policies = $loadPolicies($folderPath);
        if ($resolveReadOnly($path, $policies)) {
            jsonResponse(['ok' => false, 'error' => 'Datei ist schreibgeschützt'], 403);
        }

        $fullPath = $folderPath . '/' . $path;

        // Security check: prevent directory traversal
        $real = realpath(dirname($fullPath));
        if (!$real || strpos($real, realpath($folderPath)) !== 0) {
            jsonResponse(['ok' => false, 'error' => 'Invalid path'], 400);
        }

        // Only allow saving files, not folders
        if (is_dir($fullPath)) {
            jsonResponse(['ok' => false, 'error' => 'Cannot save folder'], 400);
        }

        // Ensure parent directory exists
        $parentDir = dirname($fullPath);
        if (!file_exists($parentDir)) {
            if (!mkdir($parentDir, 0755, true)) {
                jsonResponse(['ok' => false, 'error' => 'Failed to create parent directory'], 500);
            }
        }

        // Write content to file
        if (file_put_contents($fullPath, $content) === false) {
            jsonResponse(['ok' => false, 'error' => 'Failed to save file'], 500);
        }

        jsonResponse(['ok' => true, 'message' => 'File saved successfully']);
    }
    
    else {
        jsonResponse(['ok' => false, 'error' => 'Invalid action'], 400);
    }
    
} catch (Exception $e) {
    error_log('Task folder files error: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'Server error'], 500);
}

?>
