<?php
/**
 * Student folder files API
 * - list: return virtual init.py + real folder structure from filesystem
 * - read: return user override (if any) else filesystem default
 * - save: persist text file changes per user in DB
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

$user = requireAuth();
$conn = getDbConnection();

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

// Support admin viewing/editing student files via test_user_id
$userId = (int)$user['id'];
$isAdminSimulation = false;

if (isset($_GET['test_user_id']) || isset($_POST['test_user_id'])) {
    $testUserId = isset($_GET['test_user_id']) ? (int)$_GET['test_user_id'] : (int)$_POST['test_user_id'];
    
    // Only allow admins to simulate other users
    if (($user['role'] ?? '') === 'admin' && $testUserId > 0) {
        // Verify test user exists
        $stmt = $conn->prepare('SELECT id FROM users WHERE id = ?');
        $stmt->bind_param('i', $testUserId);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            $userId = $testUserId;
            $isAdminSimulation = true;
        } else {
            jsonResponse(['ok' => false, 'error' => 'Test user not found'], 404);
        }
    } else {
        jsonResponse(['ok' => false, 'error' => 'Unauthorized: Admin access required for test_user_id'], 403);
    }
}

$binaryExtensions = [
    'png','jpg','jpeg','gif','bmp','webp','ico','svgz',
    'pdf','zip','rar','7z','tar','gz','exe','dll','so','bin',
    'woff','woff2','ttf','otf','eot','mp3','mp4','avi','mov','wav'
];

$isTextPath = function (string $path) use ($binaryExtensions): bool {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === '') {
        return true;
    }
    return !in_array($ext, $binaryExtensions, true);
};

$sanitizeRelativePath = function (string $path): string {
    $path = ltrim(trim($path), '/');
    if ($path === '' || strpos($path, '..') !== false || strpos($path, '\\') !== false) {
        return '';
    }
    return str_replace('//', '/', $path);
};

$scanDirectory = function ($dir, $basePath = '') use (&$scanDirectory, $isTextPath) {
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
                'children' => $scanDirectory($filePath, $relativePath)
            ];
        } else {
            $items[] = [
                'name' => $file,
                'type' => 'file',
                'virtual' => false,
                'path' => $relativePath,
                'size' => filesize($filePath),
                'is_text' => $isTextPath($relativePath)
            ];
        }
    }

    return $items;
};

try {
    // Ensure per-user text file table exists
    $conn->query(
        'CREATE TABLE IF NOT EXISTS user_task_files (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            task_id INT UNSIGNED NOT NULL,
            file_path VARCHAR(1024) NOT NULL,
            content MEDIUMTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_user_task_file (user_id, task_id, file_path),
            INDEX idx_user_task (user_id, task_id),
            CONSTRAINT fk_user_task_files_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_user_task_files_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    // Load task metadata
    $taskStmt = $conn->prepare('SELECT id, assignment_id, folderstructure, code_template FROM tasks WHERE id = ?');
    $taskStmt->bind_param('i', $taskId);
    $taskStmt->execute();
    $task = $taskStmt->get_result()->fetch_assoc();

    if (!$task) {
        jsonResponse(['ok' => false, 'error' => 'Task not found'], 404);
    }

    if (!(int)$task['folderstructure']) {
        jsonResponse(['ok' => false, 'error' => 'Task has no folder structure'], 400);
    }

    $folderPath = __DIR__ . '/../../storage/tasks/folders/task_' . $taskId;

    // Resolve student's init.py content from user_tasks.current_code fallback to tasks.code_template
    $initCode = null;
    $userTaskStmt = $conn->prepare('SELECT current_code FROM user_tasks WHERE user_id = ? AND task_id = ? LIMIT 1');
    $userTaskStmt->bind_param('ii', $userId, $taskId);
    $userTaskStmt->execute();
    $userTaskRow = $userTaskStmt->get_result()->fetch_assoc();
    if ($userTaskRow && array_key_exists('current_code', $userTaskRow) && $userTaskRow['current_code'] !== null) {
        $initCode = $userTaskRow['current_code'];
    } else {
        $initCode = $task['code_template'] ?? '';
    }

    // LIST
    if ($action === 'list') {
        $files = [[
            'name' => 'init.py',
            'type' => 'file',
            'virtual' => true,
            'path' => 'init.py',
            'is_text' => true,
            'content' => $initCode
        ]];

        if (is_dir($folderPath)) {
            $realItems = $scanDirectory($folderPath);
            $files = array_merge($files, $realItems);
        }

        jsonResponse(['ok' => true, 'files' => $files]);
    }

    // READ
    if ($action === 'read') {
        $path = $sanitizeRelativePath((string)($_GET['path'] ?? ''));
        if ($path === '') {
            jsonResponse(['ok' => false, 'error' => 'Path required'], 400);
        }

        if ($path === 'init.py') {
            jsonResponse(['ok' => true, 'content' => $initCode]);
        }

        $fullPath = $folderPath . '/' . $path;
        $real = realpath($fullPath);
        if (!$real || strpos($real, realpath($folderPath)) !== 0 || !is_file($real)) {
            jsonResponse(['ok' => false, 'error' => 'File not found'], 404);
        }

        if (!$isTextPath($path)) {
            jsonResponse(['ok' => false, 'error' => 'Nur Textdateien können geöffnet werden'], 400);
        }

        $overrideStmt = $conn->prepare('SELECT content FROM user_task_files WHERE user_id = ? AND task_id = ? AND file_path = ? LIMIT 1');
        $overrideStmt->bind_param('iis', $userId, $taskId, $path);
        $overrideStmt->execute();
        $overrideRow = $overrideStmt->get_result()->fetch_assoc();

        if ($overrideRow) {
            jsonResponse(['ok' => true, 'content' => $overrideRow['content'] ?? '']);
        }

        $content = file_get_contents($real);
        if ($content === false) {
            jsonResponse(['ok' => false, 'error' => 'Failed to read file'], 500);
        }

        jsonResponse(['ok' => true, 'content' => $content]);
    }

    // SAVE
    if ($action === 'save') {
        if ($method !== 'POST') {
            jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $path = $sanitizeRelativePath((string)($input['path'] ?? ''));
        $content = (string)($input['content'] ?? '');

        if ($path === '') {
            jsonResponse(['ok' => false, 'error' => 'Path required'], 400);
        }

        if ($path === 'init.py') {
            $existingStmt = $conn->prepare('SELECT id FROM user_tasks WHERE user_id = ? AND task_id = ? LIMIT 1');
            $existingStmt->bind_param('ii', $userId, $taskId);
            $existingStmt->execute();
            $existing = $existingStmt->get_result()->fetch_assoc();

            if ($existing) {
                $updateStmt = $conn->prepare('UPDATE user_tasks SET current_code = ? WHERE id = ?');
                $updateStmt->bind_param('si', $content, $existing['id']);
                if (!$updateStmt->execute()) {
                    jsonResponse(['ok' => false, 'error' => 'Failed to save init.py'], 500);
                }
            } else {
                $status = 'in-progress';
                $attempts = 0;
                $runCount = 0;
                $hintsRevealed = '[]';
                $startedAt = date('Y-m-d H:i:s');
                $currentIteration = 1;
                $variableValues = null;

                $insertStmt = $conn->prepare(
                    'INSERT INTO user_tasks (user_id, task_id, status, attempts, current_iteration, run_count, current_code, hints_revealed, variable_values, started_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $insertStmt->bind_param('iisiiissss', $userId, $taskId, $status, $attempts, $currentIteration, $runCount, $content, $hintsRevealed, $variableValues, $startedAt);
                if (!$insertStmt->execute()) {
                    jsonResponse(['ok' => false, 'error' => 'Failed to create user task for init.py'], 500);
                }
            }

            jsonResponse(['ok' => true, 'message' => 'init.py saved']);
        }

        if (!$isTextPath($path)) {
            jsonResponse(['ok' => false, 'error' => 'Nur Textdateien können gespeichert werden'], 400);
        }

        $fullPath = $folderPath . '/' . $path;
        $real = realpath($fullPath);
        if (!$real || strpos($real, realpath($folderPath)) !== 0 || !is_file($real)) {
            jsonResponse(['ok' => false, 'error' => 'File not found'], 404);
        }

        $upsert = $conn->prepare(
            'INSERT INTO user_task_files (user_id, task_id, file_path, content)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE content = VALUES(content), updated_at = CURRENT_TIMESTAMP'
        );
        $upsert->bind_param('iiss', $userId, $taskId, $path, $content);

        if (!$upsert->execute()) {
            jsonResponse(['ok' => false, 'error' => 'Failed to save file content'], 500);
        }

        jsonResponse(['ok' => true, 'message' => 'File saved']);
    }

    jsonResponse(['ok' => false, 'error' => 'Invalid action'], 400);
} catch (Exception $e) {
    error_log('Student folder files error: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'Server error'], 500);
}
