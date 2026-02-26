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

        $fileId = isset($input['file_id']) ? (int)$input['file_id'] : null;
        
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
