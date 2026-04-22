<?php
/**
 * Import project from a single JSON file payload.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

$user = requireAuth();
$conn = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid JSON'], 400);
}

$archive = $input['archive'] ?? $input['project_export'] ?? $input;
if (!is_array($archive)) {
    jsonResponse(['ok' => false, 'error' => 'Missing archive payload'], 400);
}

$archiveProject = is_array($archive['project'] ?? null) ? $archive['project'] : [];
$files = $archive['files'] ?? null;
if (!is_array($files)) {
    jsonResponse(['ok' => false, 'error' => 'Archive files are missing'], 400);
}

$sourceType = strtolower(trim((string)($input['source_type'] ?? 'json')));

$name = trim((string)($input['name'] ?? $archiveProject['name'] ?? ''));
if ($name === '') {
    $name = 'Import ' . date('Y-m-d H:i');
}
if (mb_strlen($name) > 255) {
    $name = mb_substr($name, 0, 255);
}

$name = resolveUniqueProjectNameForUser($conn, (int)$user['id'], $name);

$description = trim((string)($archiveProject['description'] ?? ''));
if (mb_strlen($description) > 2000) {
    $description = mb_substr($description, 0, 2000);
}

$projectType = normalizeProjectType((string)($archiveProject['project_type'] ?? ''));
if ($projectType === null) {
    $projectType = inferProjectTypeFromFiles($files);
}

$visibility = 'private';

// Enforce project limit similar to create endpoint.
$maxProjects = 50;
$settingsStmt = $conn->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
$key = 'project_limit_per_user';
$settingsStmt->bind_param('s', $key);
$settingsStmt->execute();
$settingsResult = $settingsStmt->get_result()->fetch_assoc();
if ($settingsResult && isset($settingsResult['setting_value'])) {
    $maxProjects = (int)$settingsResult['setting_value'];
}

$countStmt = $conn->prepare('SELECT COUNT(*) AS count FROM projects WHERE user_id = ?');
$countStmt->bind_param('i', $user['id']);
$countStmt->execute();
$currentCount = (int)($countStmt->get_result()->fetch_assoc()['count'] ?? 0);
if ($currentCount >= $maxProjects) {
    jsonResponse(['ok' => false, 'error' => "Project limit reached ($maxProjects projects)"], 403);
}

ensureProjectFilesTablesExist($conn);

$conn->begin_transaction();

try {
    $insertProject = $conn->prepare('INSERT INTO projects (user_id, name, description, code, project_type, visibility, share_token) VALUES (?, ?, ?, ?, ?, ?, NULL)');
    $emptyCode = '';
    $insertProject->bind_param('isssss', $user['id'], $name, $description, $emptyCode, $projectType, $visibility);

    if (!$insertProject->execute()) {
        throw new Exception('Failed to create imported project');
    }

    $projectId = (int)$conn->insert_id;

    $folderPathToId = [];

    $folderPathsFromArchive = [];
    if (is_array($archive['folders'] ?? null)) {
        foreach ($archive['folders'] as $folderPathRaw) {
            if (!is_string($folderPathRaw)) {
                continue;
            }
            $folderPath = normalizePath($folderPathRaw);
            if ($folderPath !== '') {
                if ($sourceType === 'zip' && substr_count($folderPath, '/') > 0) {
                    throw new Exception('ZIP-Import unterstützt maximal eine Ordnerebene.');
                }
                $folderPathsFromArchive[] = $folderPath;
            }
        }
    }

    // Also derive folder paths from file paths.
    foreach ($files as $fileItem) {
        if (!is_array($fileItem)) {
            continue;
        }
        $rawPath = (string)($fileItem['path'] ?? '');
        $normalized = normalizePath($rawPath);
        if ($normalized === '') {
            continue;
        }
        if ($sourceType === 'zip' && substr_count($normalized, '/') > 1) {
            throw new Exception('ZIP-Import unterstützt maximal eine Ordnerebene: ' . $normalized);
        }
        $folderPath = dirname($normalized);
        if ($folderPath !== '.' && $folderPath !== DIRECTORY_SEPARATOR) {
            $folderPathsFromArchive[] = normalizePath($folderPath);
        }
    }

    $folderPathsFromArchive = array_values(array_unique(array_filter($folderPathsFromArchive, function ($p) {
        return $p !== '';
    })));

    usort($folderPathsFromArchive, function ($a, $b) {
        return substr_count($a, '/') <=> substr_count($b, '/');
    });

    foreach ($folderPathsFromArchive as $folderPath) {
        ensureFolderPath($conn, $projectId, $folderPath, $folderPathToId);
    }

    $insertFile = $conn->prepare('INSERT INTO project_files (project_id, folder_id, name, content, mime_type, file_size) VALUES (?, ?, ?, ?, ?, ?)');

    $insertedAnyFile = false;
    $initPyContent = '';

    foreach ($files as $fileItem) {
        if (!is_array($fileItem)) {
            continue;
        }

        $rawPath = (string)($fileItem['path'] ?? '');
        $normalizedPath = normalizePath($rawPath);
        if ($normalizedPath === '') {
            continue;
        }
        if ($sourceType === 'zip' && substr_count($normalizedPath, '/') > 1) {
            throw new Exception('ZIP-Import unterstützt maximal eine Ordnerebene: ' . $normalizedPath);
        }

        $namePart = basename($normalizedPath);
        if (!isValidNodeName($namePart)) {
            throw new Exception('Invalid file name in archive: ' . $namePart);
        }

        $folderPath = dirname($normalizedPath);
        $folderId = null;
        if ($folderPath !== '.' && $folderPath !== DIRECTORY_SEPARATOR) {
            $folderPath = normalizePath($folderPath);
            if ($folderPath !== '') {
                ensureFolderPath($conn, $projectId, $folderPath, $folderPathToId);
                $folderId = (int)$folderPathToId[$folderPath];
            }
        }

        $content = (string)($fileItem['content'] ?? '');
        $mimeType = trim((string)($fileItem['mime_type'] ?? ''));
        if ($mimeType === '') {
            $mimeType = getMimeTypeFromFilename($namePart);
        }
        $fileSize = strlen($content);

        $checkDup = null;
        if ($folderId === null) {
            $checkDup = $conn->prepare('SELECT id FROM project_files WHERE project_id = ? AND folder_id IS NULL AND name = ? LIMIT 1');
            $checkDup->bind_param('is', $projectId, $namePart);
        } else {
            $checkDup = $conn->prepare('SELECT id FROM project_files WHERE project_id = ? AND folder_id = ? AND name = ? LIMIT 1');
            $checkDup->bind_param('iis', $projectId, $folderId, $namePart);
        }
        $checkDup->execute();
        if ($checkDup->get_result()->num_rows > 0) {
            throw new Exception('Duplicate file in archive: ' . $normalizedPath);
        }

        $insertFile->bind_param('iisssi', $projectId, $folderId, $namePart, $content, $mimeType, $fileSize);
        if (!$insertFile->execute()) {
            throw new Exception('Failed to import file: ' . $normalizedPath);
        }

        $insertedAnyFile = true;
        if (strcasecmp($namePart, 'init.py') === 0 && $folderId === null && $initPyContent === '') {
            $initPyContent = $content;
        }
    }

    if (!$insertedAnyFile) {
        $defaultInit = "# Imported project\n\n# Start coding here\n";
        $defaultMime = 'text/x-python';
        $defaultSize = strlen($defaultInit);
        $nullFolder = null;
        $initName = 'init.py';
        $insertFile->bind_param('iisssi', $projectId, $nullFolder, $initName, $defaultInit, $defaultMime, $defaultSize);
        if (!$insertFile->execute()) {
            throw new Exception('Failed to create default init.py');
        }
        $initPyContent = $defaultInit;
    }

    // Keep compatibility with code fallback in parts of UI.
    $updateCode = $conn->prepare('UPDATE projects SET code = ?, updated_at = NOW() WHERE id = ?');
    $updateCode->bind_param('si', $initPyContent, $projectId);
    $updateCode->execute();

    $conn->commit();

    jsonResponse([
        'ok' => true,
        'project' => [
            'id' => $projectId,
            'name' => $name,
            'description' => $description,
            'project_type' => $projectType,
            'visibility' => $visibility
        ]
    ], 201);
} catch (Exception $e) {
    $conn->rollback();
    jsonResponse(['ok' => false, 'error' => $e->getMessage()], 400);
}

function normalizeProjectType($type)
{
    $type = strtolower(trim($type));
    if (in_array($type, ['python', 'html', 'mixed'], true)) {
        return $type;
    }
    return null;
}

function inferProjectTypeFromFiles($files)
{
    $hasPy = false;
    $hasHtml = false;

    foreach ($files as $f) {
        if (!is_array($f)) {
            continue;
        }
        $path = strtolower((string)($f['path'] ?? ''));
        if ($path === '') {
            continue;
        }
        if (substr($path, -3) === '.py') {
            $hasPy = true;
        }
        if (substr($path, -5) === '.html' || substr($path, -4) === '.htm') {
            $hasHtml = true;
        }
    }

    if ($hasHtml && $hasPy) {
        return 'mixed';
    }
    if ($hasHtml) {
        return 'html';
    }
    return 'python';
}

function normalizePath($path)
{
    $path = str_replace('\\', '/', trim((string)$path));
    $path = preg_replace('#/+#', '/', $path);
    $path = trim($path, '/');
    if ($path === '' || $path === '.') {
        return '';
    }

    $parts = explode('/', $path);
    $safe = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..') {
            throw new Exception('Invalid path segment in import');
        }
        if (!isValidNodeName($part)) {
            throw new Exception('Invalid path segment in import: ' . $part);
        }
        $safe[] = $part;
    }

    return implode('/', $safe);
}

function isValidNodeName($name)
{
    return (bool)preg_match('/^[\w\-. ]+$/', (string)$name);
}

function ensureFolderPath($conn, $projectId, $folderPath, &$folderPathToId)
{
    $folderPath = normalizePath($folderPath);
    if ($folderPath === '') {
        return null;
    }

    if (isset($folderPathToId[$folderPath])) {
        return $folderPathToId[$folderPath];
    }

    $parts = explode('/', $folderPath);
    $currentPath = '';
    $parentId = null;

    foreach ($parts as $part) {
        $currentPath = $currentPath === '' ? $part : ($currentPath . '/' . $part);

        if (isset($folderPathToId[$currentPath])) {
            $parentId = (int)$folderPathToId[$currentPath];
            continue;
        }

        $select = null;
        if ($parentId === null) {
            $select = $conn->prepare('SELECT id FROM project_folders WHERE project_id = ? AND parent_folder_id IS NULL AND name = ? LIMIT 1');
            $select->bind_param('is', $projectId, $part);
        } else {
            $select = $conn->prepare('SELECT id FROM project_folders WHERE project_id = ? AND parent_folder_id = ? AND name = ? LIMIT 1');
            $select->bind_param('iis', $projectId, $parentId, $part);
        }

        $select->execute();
        $row = $select->get_result()->fetch_assoc();

        if ($row) {
            $folderId = (int)$row['id'];
        } else {
            $insert = $conn->prepare('INSERT INTO project_folders (project_id, parent_folder_id, name) VALUES (?, ?, ?)');
            $insert->bind_param('iis', $projectId, $parentId, $part);
            if (!$insert->execute()) {
                throw new Exception('Failed to create folder: ' . $currentPath);
            }
            $folderId = (int)$conn->insert_id;
        }

        $folderPathToId[$currentPath] = $folderId;
        $parentId = $folderId;
    }

    return $folderPathToId[$folderPath];
}

function getMimeTypeFromFilename($filename)
{
    $ext = strtolower(pathinfo((string)$filename, PATHINFO_EXTENSION));
    $map = [
        'py' => 'text/x-python',
        'txt' => 'text/plain',
        'md' => 'text/markdown',
        'json' => 'application/json',
        'html' => 'text/html',
        'htm' => 'text/html',
        'css' => 'text/css',
        'js' => 'text/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp'
    ];

    return $map[$ext] ?? 'text/plain';
}

function ensureProjectFilesTablesExist($conn)
{
    try {
        $result = $conn->query("SHOW TABLES LIKE 'project_folders'");
        if ($result && $result->num_rows == 0) {
            $conn->query("CREATE TABLE IF NOT EXISTS project_folders (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                project_id INT UNSIGNED NOT NULL,
                parent_folder_id INT UNSIGNED,
                name VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                FOREIGN KEY (parent_folder_id) REFERENCES project_folders(id) ON DELETE CASCADE,
                INDEX (project_id),
                INDEX (parent_folder_id),
                UNIQUE KEY unique_folder_name (project_id, parent_folder_id, name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        $result = $conn->query("SHOW TABLES LIKE 'project_files'");
        if ($result && $result->num_rows == 0) {
            $conn->query("CREATE TABLE IF NOT EXISTS project_files (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                project_id INT UNSIGNED NOT NULL,
                folder_id INT UNSIGNED,
                name VARCHAR(255) NOT NULL,
                content MEDIUMTEXT,
                mime_type VARCHAR(100) DEFAULT 'text/plain',
                file_size INT UNSIGNED DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                FOREIGN KEY (folder_id) REFERENCES project_folders(id) ON DELETE CASCADE,
                INDEX (project_id),
                INDEX (folder_id),
                UNIQUE KEY unique_file_name (project_id, folder_id, name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
    } catch (Exception $e) {
        error_log('Failed to ensure project files tables: ' . $e->getMessage());
    }
}

function resolveUniqueProjectNameForUser($conn, $userId, $baseName)
{
    $name = preg_replace('/\s+/', ' ', trim((string)$baseName));
    if ($name === '') {
        $name = 'Importiertes Projekt';
    }

    $name = mb_substr($name, 0, 255);

    if (!projectNameExistsForUser($conn, $userId, $name)) {
        return $name;
    }

    $candidate = mb_substr($name, 0, max(1, 255 - strlen('=1'))) . '=1';
    if (!projectNameExistsForUser($conn, $userId, $candidate)) {
        return $candidate;
    }

    for ($i = 2; $i < 1000; $i++) {
        $suffix = $i < 100 ? str_pad((string)$i, 2, '0', STR_PAD_LEFT) : (string)$i;
        $postfix = '=' . $suffix;
        $prefix = mb_substr($name, 0, max(1, 255 - strlen($postfix)));
        $candidate = $prefix . $postfix;

        if (!projectNameExistsForUser($conn, $userId, $candidate)) {
            return $candidate;
        }
    }

    $fallbackPostfix = '=' . date('His');
    $prefix = mb_substr($name, 0, max(1, 255 - strlen($fallbackPostfix)));
    return $prefix . $fallbackPostfix;
}

function projectNameExistsForUser($conn, $userId, $name)
{
    $stmt = $conn->prepare('SELECT id FROM projects WHERE user_id = ? AND LOWER(name) = LOWER(?) LIMIT 1');
    $stmt->bind_param('is', $userId, $name);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $exists;
}
