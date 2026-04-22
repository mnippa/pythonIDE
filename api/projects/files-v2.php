<?php
/**
 * Project Files API (v2) - Hierarchical File System
 * 
 * Endpoints:
 * GET  /api/projects/files.php?action=tree&project_id=X        - Get file tree structure
 * POST /api/projects/files.php?action=create&project_id=X      - Create new file
 * GET  /api/projects/files.php?action=read&project_id=X&file_id=Y - Read file content
 * PUT  /api/projects/files.php?action=update&project_id=X      - Update file content
 * DELETE /api/projects/files.php?action=delete&project_id=X    - Delete file
 * 
 * Uses: project_files, project_folders tables
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

$user = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? null;

// Read JSON body once (can only be read once!)
$jsonInput = json_decode(file_get_contents('php://input'), true) ?? [];

// Get project_id from GET, POST, or JSON body
$projectId = null;
if (isset($_GET['project_id'])) {
    $projectId = (int)$_GET['project_id'];
} elseif (isset($_POST['project_id'])) {
    $projectId = (int)$_POST['project_id'];
} elseif (isset($jsonInput['project_id'])) {
    $projectId = (int)$jsonInput['project_id'];
}

if (!$projectId) {
    jsonResponse(['ok' => false, 'error' => 'Project ID required'], 400);
}

$conn = getDbConnection();

// Ensure required tables exist
ensureProjectFilesTablesExist($conn);

// Check project ownership
$stmt = $conn->prepare('SELECT user_id FROM projects WHERE id = ?');
$stmt->bind_param('i', $projectId);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();

if (!$project || ($project['user_id'] != $user['id'] && $user['role'] !== 'admin')) {
    jsonResponse(['ok' => false, 'error' => 'Access denied'], 403);
}

try {
    // ============================================
    // GET TREE STRUCTURE
    // ============================================
    if ($action === 'tree') {
        $tree = buildFileTree($conn, $projectId);
        jsonResponse(['ok' => true, 'tree' => $tree]);
    }

    // ============================================
    // IMPORT ZIP ARCHIVE
    // ============================================
    elseif ($action === 'import_zip' && $method === 'POST') {
        if (!class_exists('ZipArchive')) {
            jsonResponse(['ok' => false, 'error' => 'ZipArchive is not available on server'], 500);
        }

        if (!isset($_FILES['zip_file']) || !is_array($_FILES['zip_file'])) {
            jsonResponse(['ok' => false, 'error' => 'ZIP file required'], 400);
        }

        $zipFile = $_FILES['zip_file'];
        if (($zipFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            jsonResponse(['ok' => false, 'error' => 'ZIP upload failed'], 400);
        }

        $uploadName = (string)($zipFile['name'] ?? '');
        if (strtolower(pathinfo($uploadName, PATHINFO_EXTENSION)) !== 'zip') {
            jsonResponse(['ok' => false, 'error' => 'Only .zip files are supported'], 400);
        }

        $baseFolderId = null;
        if (isset($_POST['folder_id']) && $_POST['folder_id'] !== '' && $_POST['folder_id'] !== 'null') {
            $baseFolderId = (int)$_POST['folder_id'];
            if ($baseFolderId > 0) {
                $checkFolderStmt = $conn->prepare('SELECT id FROM project_folders WHERE id = ? AND project_id = ?');
                $checkFolderStmt->bind_param('ii', $baseFolderId, $projectId);
                $checkFolderStmt->execute();
                if ($checkFolderStmt->get_result()->num_rows === 0) {
                    jsonResponse(['ok' => false, 'error' => 'Target folder not found'], 404);
                }
            } else {
                $baseFolderId = null;
            }
        }

        $zip = new ZipArchive();
        $opened = $zip->open($zipFile['tmp_name']);
        if ($opened !== true) {
            jsonResponse(['ok' => false, 'error' => 'Unable to open ZIP archive'], 400);
        }

        $importedCount = 0;
        $renamedCount = 0;
        $skippedCount = 0;
        $failed = [];
        $renamed = [];

        $folderCache = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat || !isset($stat['name'])) {
                continue;
            }

            $entryName = (string)$stat['name'];
            if ($entryName === '' || substr($entryName, -1) === '/') {
                continue;
            }

            $normalizedPath = normalizeZipEntryPath($entryName);
            if ($normalizedPath === '' || strpos($normalizedPath, '__MACOSX/') === 0) {
                continue;
            }

            try {
                $parts = explode('/', $normalizedPath);
                if (count($parts) === 0) {
                    $skippedCount++;
                    continue;
                }

                foreach ($parts as $segment) {
                    if (!isValidNodeName($segment)) {
                        throw new Exception('Invalid path segment');
                    }
                }

                $fileName = array_pop($parts);
                if ($fileName === '' || !isValidNodeName($fileName)) {
                    throw new Exception('Invalid filename');
                }

                $targetFolderId = ensureFolderPathInProject($conn, $projectId, $parts, $baseFolderId, $folderCache);

                $raw = $zip->getFromIndex($i);
                if ($raw === false) {
                    throw new Exception('Could not read ZIP entry');
                }

                $mimeType = getMimeType($fileName);
                $storeContent = $raw;

                if (isBinaryByFileName($fileName)) {
                    if (isImageByFileName($fileName)) {
                        $storeContent = 'data:' . $mimeType . ';base64,' . base64_encode($raw);
                    } else {
                        $storeContent = 'data:application/octet-stream;base64,' . base64_encode($raw);
                    }
                }

                $uniqueFileName = resolveUniqueFileName($conn, $projectId, $targetFolderId, $fileName);
                $fileSize = strlen($storeContent);
                $insertStmt = $conn->prepare('INSERT INTO project_files (project_id, folder_id, name, content, mime_type, file_size) VALUES (?, ?, ?, ?, ?, ?)');
                $insertStmt->bind_param('iisssi', $projectId, $targetFolderId, $uniqueFileName, $storeContent, $mimeType, $fileSize);

                if (!$insertStmt->execute()) {
                    throw new Exception('DB insert failed');
                }

                $importedCount++;
                if ($uniqueFileName !== $fileName) {
                    $renamedCount++;
                    $renamed[] = $normalizedPath . ' -> ' . $uniqueFileName;
                }
            } catch (Exception $entryErr) {
                $failed[] = $normalizedPath . ': ' . $entryErr->getMessage();
            }
        }

        $zip->close();

        jsonResponse([
            'ok' => true,
            'imported' => $importedCount,
            'renamed' => $renamedCount,
            'skipped' => $skippedCount,
            'failed' => $failed,
            'renamed_files' => $renamed
        ]);
    }
    
    // ============================================
    // CREATE FILE
    // ============================================
    elseif ($action === 'create' && $method === 'POST') {
        $parentFolderId = $jsonInput['folder_id'] ?? null;
        $fileName = trim($jsonInput['name'] ?? '');
        $content = $jsonInput['content'] ?? '';
        
        if (empty($fileName)) {
            jsonResponse(['ok' => false, 'error' => 'File name required'], 400);
        }
        
        // Validate filename
        if (!preg_match('/^[\w\-. ]+$/', $fileName)) {
            jsonResponse(['ok' => false, 'error' => 'Invalid filename'], 400);
        }
        
        if ($parentFolderId) {
            $checkFolderStmt = $conn->prepare('SELECT id FROM project_folders WHERE id = ? AND project_id = ?');
            $checkFolderStmt->bind_param('ii', $parentFolderId, $projectId);
            $checkFolderStmt->execute();
            if ($checkFolderStmt->get_result()->num_rows === 0) {
                jsonResponse(['ok' => false, 'error' => 'Folder not found'], 404);
            }
        }

        // Prevent duplicate names in same folder (also works for root folder_id IS NULL)
        if ($parentFolderId === null || $parentFolderId === '' || (int)$parentFolderId === 0) {
            $dupStmt = $conn->prepare('SELECT id FROM project_files WHERE project_id = ? AND folder_id IS NULL AND name = ? LIMIT 1');
            $dupStmt->bind_param('is', $projectId, $fileName);
        } else {
            $parentFolderId = (int)$parentFolderId;
            $dupStmt = $conn->prepare('SELECT id FROM project_files WHERE project_id = ? AND folder_id = ? AND name = ? LIMIT 1');
            $dupStmt->bind_param('iis', $projectId, $parentFolderId, $fileName);
        }
        $dupStmt->execute();
        if ($dupStmt->get_result()->num_rows > 0) {
            jsonResponse(['ok' => false, 'error' => 'Dateiname bereits vorhanden'], 409);
        }
        
        $fileSize = strlen($content);
        $mimeType = getMimeType($fileName);
        
        $stmt = $conn->prepare('INSERT INTO project_files (project_id, folder_id, name, content, mime_type, file_size) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('iisssi', $projectId, $parentFolderId, $fileName, $content, $mimeType, $fileSize);
        
        if ($stmt->execute()) {
            $fileId = $conn->insert_id;
            jsonResponse(['ok' => true, 'file_id' => $fileId, 'name' => $fileName], 201);
        } else {
            jsonResponse(['ok' => false, 'error' => $conn->error], 500);
        }
    }
    
    // ============================================
    // READ FILE
    // ============================================
    elseif ($action === 'read' && $method === 'GET') {
        $fileId = isset($_GET['file_id']) ? (int)$_GET['file_id'] : null;
        
        if (!$fileId) {
            jsonResponse(['ok' => false, 'error' => 'File ID required'], 400);
        }
        
        $stmt = $conn->prepare('SELECT id, name, content, mime_type, file_size FROM project_files WHERE id = ? AND project_id = ?');
        $stmt->bind_param('ii', $fileId, $projectId);
        $stmt->execute();
        $file = $stmt->get_result()->fetch_assoc();
        
        if (!$file) {
            jsonResponse(['ok' => false, 'error' => 'File not found'], 404);
        }
        
        jsonResponse([
            'ok' => true,
            'id' => $file['id'],
            'name' => $file['name'],
            'content' => $file['content'],
            'mime_type' => $file['mime_type'],
            'size' => $file['file_size']
        ]);
    }
    
    // ============================================
    // RENAME FILE
    // ============================================
    elseif ($action === 'rename' && $method === 'PUT') {
        $fileId = isset($jsonInput['file_id']) ? (int)$jsonInput['file_id'] : null;
        $newName = trim($jsonInput['name'] ?? '');

        if (!$fileId || $newName === '') {
            jsonResponse(['ok' => false, 'error' => 'File ID and name required'], 400);
        }

        if (!preg_match('/^[\w\-. ]+$/', $newName)) {
            jsonResponse(['ok' => false, 'error' => 'Invalid filename'], 400);
        }

        $fileStmt = $conn->prepare('SELECT id, folder_id FROM project_files WHERE id = ? AND project_id = ?');
        $fileStmt->bind_param('ii', $fileId, $projectId);
        $fileStmt->execute();
        $fileRow = $fileStmt->get_result()->fetch_assoc();
        if (!$fileRow) {
            jsonResponse(['ok' => false, 'error' => 'File not found'], 404);
        }

        $folderId = $fileRow['folder_id'];
        if ($folderId === null) {
            $dupStmt = $conn->prepare('SELECT id FROM project_files WHERE project_id = ? AND folder_id IS NULL AND name = ? AND id != ? LIMIT 1');
            $dupStmt->bind_param('isi', $projectId, $newName, $fileId);
        } else {
            $folderId = (int)$folderId;
            $dupStmt = $conn->prepare('SELECT id FROM project_files WHERE project_id = ? AND folder_id = ? AND name = ? AND id != ? LIMIT 1');
            $dupStmt->bind_param('iisi', $projectId, $folderId, $newName, $fileId);
        }
        $dupStmt->execute();
        if ($dupStmt->get_result()->num_rows > 0) {
            jsonResponse(['ok' => false, 'error' => 'Dateiname bereits vorhanden'], 409);
        }

        $renameStmt = $conn->prepare('UPDATE project_files SET name = ?, updated_at = NOW() WHERE id = ? AND project_id = ?');
        $renameStmt->bind_param('sii', $newName, $fileId, $projectId);

        if ($renameStmt->execute()) {
            jsonResponse(['ok' => true, 'message' => 'File renamed']);
        } else {
            jsonResponse(['ok' => false, 'error' => $conn->error], 500);
        }
    }

    // ============================================
    // UPDATE FILE (SAVE)
    // ============================================
    elseif ($action === 'update' && $method === 'PUT') {
        $fileId = isset($jsonInput['file_id']) ? (int)$jsonInput['file_id'] : null;
        $content = $jsonInput['content'] ?? null;
        
        if (!$fileId || $content === null) {
            jsonResponse(['ok' => false, 'error' => 'File ID and content required'], 400);
        }
        
        // Check file exists and belongs to project
        $stmt = $conn->prepare('SELECT id FROM project_files WHERE id = ? AND project_id = ?');
        $stmt->bind_param('ii', $fileId, $projectId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            jsonResponse(['ok' => false, 'error' => 'File not found'], 404);
        }
        
        $fileSize = strlen($content);
        $stmt = $conn->prepare('UPDATE project_files SET content = ?, file_size = ?, updated_at = NOW() WHERE id = ? AND project_id = ?');
        $stmt->bind_param('siii', $content, $fileSize, $fileId, $projectId);
        
        if ($stmt->execute()) {
            jsonResponse(['ok' => true, 'message' => 'File saved']);
        } else {
            jsonResponse(['ok' => false, 'error' => $conn->error], 500);
        }
    }
    
    // ============================================
    // DELETE FILE
    // ============================================
    elseif ($action === 'delete' && $method === 'DELETE') {

        $fileId = isset($jsonInput['file_id']) ? (int)$jsonInput['file_id'] : null;
        
        if (!$fileId) {
            jsonResponse(['ok' => false, 'error' => 'File ID required'], 400);
        }
        
        $stmt = $conn->prepare('DELETE FROM project_files WHERE id = ? AND project_id = ?');
        $stmt->bind_param('ii', $fileId, $projectId);
        
        if ($stmt->execute() && $conn->affected_rows > 0) {
            jsonResponse(['ok' => true, 'message' => 'File deleted']);
        } else {
            jsonResponse(['ok' => false, 'error' => 'File not found'], 404);
        }
    }
    
    else {
        jsonResponse(['ok' => false, 'error' => 'Unknown action'], 400);
    }
    
} catch (Exception $e) {
    error_log('[ProjectFilesAPI] Error: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => $e->getMessage()], 500);
}

// ============================================
// Helper Functions
// ============================================

/**
 * Build hierarchical file tree structure
 */
function buildFileTree($conn, $projectId) {
    // Get project info
    $stmt = $conn->prepare('SELECT name FROM projects WHERE id = ?');
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $project = $stmt->get_result()->fetch_assoc();
    
    $tree = [
        'name' => $project['name'],
        'type' => 'root',
        'id' => $projectId,
        'expanded' => true,
        'children' => []
    ];
    
    // Get root folders and files
    $tree['children'] = getTreeChildren($conn, $projectId, null);
    
    return $tree;
}

/**
 * Recursively get children for a folder
 */
function getTreeChildren($conn, $projectId, $parentFolderId) {
    $children = [];
    
    // Get folders
    $sql = 'SELECT id, name FROM project_folders WHERE project_id = ? AND parent_folder_id ' . 
           ($parentFolderId === null ? 'IS NULL' : '= ?') . ' ORDER BY name ASC';
    $stmt = $conn->prepare($sql);
    
    if ($parentFolderId === null) {
        $stmt->bind_param('i', $projectId);
    } else {
        $stmt->bind_param('ii', $projectId, $parentFolderId);
    }
    
    $stmt->execute();
    $folders = $stmt->get_result();
    
    while ($folder = $folders->fetch_assoc()) {
        $children[] = [
            'id' => $folder['id'],
            'name' => $folder['name'],
            'type' => 'folder',
            'expanded' => false,
            'children' => getTreeChildren($conn, $projectId, $folder['id'])
        ];
    }
    
    // Get files
    $sql = 'SELECT id, name, file_size FROM project_files WHERE project_id = ? AND folder_id ' . 
           ($parentFolderId === null ? 'IS NULL' : '= ?') . ' ORDER BY name ASC';
    $stmt = $conn->prepare($sql);
    
    if ($parentFolderId === null) {
        $stmt->bind_param('i', $projectId);
    } else {
        $stmt->bind_param('ii', $projectId, $parentFolderId);
    }
    
    $stmt->execute();
    $files = $stmt->get_result();
    
    while ($file = $files->fetch_assoc()) {
        $children[] = [
            'id' => $file['id'],
            'name' => $file['name'],
            'type' => 'file',
            'size' => $file['file_size'] ?? 0
        ];
    }
    
    return $children;
}

/**
 * Get MIME type from filename
 */
function getMimeType($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $mimeTypes = [
        'py' => 'text/x-python',
        'txt' => 'text/plain',
        'md' => 'text/markdown',
        'json' => 'application/json',
        'html' => 'text/html',
        'css' => 'text/css',
        'js' => 'text/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
    ];
    
    return $mimeTypes[$ext] ?? 'text/plain';
}

function normalizeZipEntryPath($path) {
    $path = str_replace('\\', '/', trim((string)$path));
    $path = preg_replace('#/+#', '/', $path);
    $path = trim($path, '/');
    return $path;
}

function isValidNodeName($name) {
    $name = (string)$name;
    if ($name === '' || $name === '.' || $name === '..') {
        return false;
    }
    return preg_match('/^[\w\-. ]+$/', $name) === 1;
}

function isImageByFileName($filename) {
    $ext = strtolower(pathinfo((string)$filename, PATHINFO_EXTENSION));
    return in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'], true);
}

function isBinaryByFileName($filename) {
    $ext = strtolower(pathinfo((string)$filename, PATHINFO_EXTENSION));
    $textExt = ['py', 'txt', 'md', 'json', 'html', 'htm', 'css', 'js', 'csv', 'xml', 'yml', 'yaml', 'ini', 'cfg', 'sql', 'php'];
    return !in_array($ext, $textExt, true);
}

function ensureFolderPathInProject($conn, $projectId, $segments, $baseFolderId, &$folderCache) {
    $parentId = $baseFolderId === null ? null : (int)$baseFolderId;

    foreach ($segments as $segment) {
        $segment = trim((string)$segment);
        if ($segment === '') {
            continue;
        }

        $cacheKey = ($parentId === null ? 'root' : (string)$parentId) . '|' . $segment;
        if (isset($folderCache[$cacheKey])) {
            $parentId = $folderCache[$cacheKey];
            continue;
        }

        if ($parentId === null) {
            $findStmt = $conn->prepare('SELECT id FROM project_folders WHERE project_id = ? AND parent_folder_id IS NULL AND name = ? LIMIT 1');
            $findStmt->bind_param('is', $projectId, $segment);
        } else {
            $findStmt = $conn->prepare('SELECT id FROM project_folders WHERE project_id = ? AND parent_folder_id = ? AND name = ? LIMIT 1');
            $findStmt->bind_param('iis', $projectId, $parentId, $segment);
        }

        $findStmt->execute();
        $row = $findStmt->get_result()->fetch_assoc();

        if ($row && isset($row['id'])) {
            $parentId = (int)$row['id'];
            $folderCache[$cacheKey] = $parentId;
            continue;
        }

        $insertStmt = $conn->prepare('INSERT INTO project_folders (project_id, parent_folder_id, name) VALUES (?, ?, ?)');
        $insertStmt->bind_param('iis', $projectId, $parentId, $segment);
        if (!$insertStmt->execute()) {
            throw new Exception('Could not create folder: ' . $segment);
        }

        $parentId = (int)$conn->insert_id;
        $folderCache[$cacheKey] = $parentId;
    }

    return $parentId;
}

function resolveUniqueFileName($conn, $projectId, $folderId, $originalName) {
    $name = (string)$originalName;
    if (!fileNameExistsInFolder($conn, $projectId, $folderId, $name)) {
        return $name;
    }

    $dotPos = strrpos($name, '.');
    if ($dotPos === false || $dotPos === 0) {
        $base = $name;
        $ext = '';
    } else {
        $base = substr($name, 0, $dotPos);
        $ext = substr($name, $dotPos);
    }

    for ($i = 1; $i < 1000; $i++) {
        $suffix = $i === 1 ? '=1' : '=' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
        $maxBaseLen = 255 - strlen($ext) - strlen($suffix);
        if ($maxBaseLen < 1) {
            $maxBaseLen = 1;
        }
        $candidate = substr($base, 0, $maxBaseLen) . $suffix . $ext;
        if (!fileNameExistsInFolder($conn, $projectId, $folderId, $candidate)) {
            return $candidate;
        }
    }

    throw new Exception('No free name available for file: ' . $originalName);
}

function fileNameExistsInFolder($conn, $projectId, $folderId, $name) {
    if ($folderId === null) {
        $checkStmt = $conn->prepare('SELECT id FROM project_files WHERE project_id = ? AND folder_id IS NULL AND name = ? LIMIT 1');
        $checkStmt->bind_param('is', $projectId, $name);
    } else {
        $checkStmt = $conn->prepare('SELECT id FROM project_files WHERE project_id = ? AND folder_id = ? AND name = ? LIMIT 1');
        $folderIdInt = (int)$folderId;
        $checkStmt->bind_param('iis', $projectId, $folderIdInt, $name);
    }

    $checkStmt->execute();
    return $checkStmt->get_result()->num_rows > 0;
}

/**
 * Ensure required tables exist - creates them if missing (idempotent)
 */
function ensureProjectFilesTablesExist($conn) {
    try {
        // Check if project_folders table exists
        $result = $conn->query("SHOW TABLES LIKE 'project_folders'");
        if($result->num_rows == 0) {
            // Create project_folders table
            $conn->query("
                CREATE TABLE IF NOT EXISTS project_folders (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
        
        // Check if project_files table exists
        $result = $conn->query("SHOW TABLES LIKE 'project_files'");
        if($result->num_rows == 0) {
            // Create project_files table
            $conn->query("
                CREATE TABLE IF NOT EXISTS project_files (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    } catch (Exception $e) {
        error_log('Failed to ensure project files tables: ' . $e->getMessage());
    }
}
?>
