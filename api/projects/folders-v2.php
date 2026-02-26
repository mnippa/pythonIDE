<?php
/**
 * Project Folders API (v2) - Hierarchical Folder Management
 * 
 * Endpoints:
 * POST   /api/projects/folders.php?action=create      - Create folder
 * PUT    /api/projects/folders.php?action=rename      - Rename folder
 * DELETE /api/projects/folders.php?action=delete      - Delete folder
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
    // CREATE FOLDER
    // ============================================
    if ($action === 'create' && $method === 'POST') {
        $parentFolderId = $jsonInput['parent_folder_id'] ?? null;
        $folderName = trim($jsonInput['name'] ?? '');
        
        if (empty($folderName)) {
            jsonResponse(['ok' => false, 'error' => 'Folder name required'], 400);
        }
        
        // Validate folder name
        if (!preg_match('/^[\w\-. ]+$/', $folderName)) {
            jsonResponse(['ok' => false, 'error' => 'Invalid folder name'], 400);
        }
        
        // Check parent folder exists if specified
        if ($parentFolderId) {
            $checkStmt = $conn->prepare('SELECT id FROM project_folders WHERE id = ? AND project_id = ?');
            $checkStmt->bind_param('ii', $parentFolderId, $projectId);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows === 0) {
                jsonResponse(['ok' => false, 'error' => 'Parent folder not found'], 404);
            }
        }
        
        // Check if folder name already exists at this location
        $checkStmt = $conn->prepare('SELECT id FROM project_folders WHERE project_id = ? AND name = ? AND parent_folder_id ' . 
                                   ($parentFolderId === null ? 'IS NULL' : '= ?'));
        if ($parentFolderId === null) {
            $checkStmt->bind_param('is', $projectId, $folderName);
        } else {
            $checkStmt->bind_param('isi', $projectId, $folderName, $parentFolderId);
        }
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            jsonResponse(['ok' => false, 'error' => 'Folder already exists at this location'], 409);
        }
        
        // Create folder
        $stmt = $conn->prepare('INSERT INTO project_folders (project_id, parent_folder_id, name) VALUES (?, ?, ?)');
        $stmt->bind_param('iis', $projectId, $parentFolderId, $folderName);
        
        if ($stmt->execute()) {
            $folderId = $conn->insert_id;
            jsonResponse(['ok' => true, 'folder_id' => $folderId, 'name' => $folderName], 201);
        } else {
            jsonResponse(['ok' => false, 'error' => $conn->error], 500);
        }
    }
    
    // ============================================
    // RENAME FOLDER
    // ============================================
    elseif ($action === 'rename' && $method === 'PUT') {
        $folderId = isset($jsonInput['folder_id']) ? (int)$jsonInput['folder_id'] : null;
        $newName = trim($jsonInput['name'] ?? '');
        
        if (!$folderId || empty($newName)) {
            jsonResponse(['ok' => false, 'error' => 'Folder ID and name required'], 400);
        }
        
        // Validate folder name
        if (!preg_match('/^[\w\-. ]+$/', $newName)) {
            jsonResponse(['ok' => false, 'error' => 'Invalid folder name'], 400);
        }
        
        // Check folder exists
        $stmt = $conn->prepare('SELECT id, parent_folder_id FROM project_folders WHERE id = ? AND project_id = ?');
        $stmt->bind_param('ii', $folderId, $projectId);
        $stmt->execute();
        $folder = $stmt->get_result()->fetch_assoc();
        
        if (!$folder) {
            jsonResponse(['ok' => false, 'error' => 'Folder not found'], 404);
        }
        
        // Check if name already exists at same level
        $checkStmt = $conn->prepare('SELECT id FROM project_folders WHERE project_id = ? AND name = ? AND parent_folder_id ' . 
                                   ($folder['parent_folder_id'] === null ? 'IS NULL' : '= ?') . ' AND id != ?');
        if ($folder['parent_folder_id'] === null) {
            $checkStmt->bind_param('isii', $projectId, $newName, $folderId);
        } else {
            $checkStmt->bind_param('isii', $projectId, $newName, $folder['parent_folder_id'], $folderId);
        }
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            jsonResponse(['ok' => false, 'error' => 'Folder with this name already exists'], 409);
        }
        
        // Rename folder
        $stmt = $conn->prepare('UPDATE project_folders SET name = ? WHERE id = ? AND project_id = ?');
        $stmt->bind_param('sii', $newName, $folderId, $projectId);
        
        if ($stmt->execute()) {
            jsonResponse(['ok' => true, 'message' => 'Folder renamed']);
        } else {
            jsonResponse(['ok' => false, 'error' => $conn->error], 500);
        }
    }
    
    // ============================================
    // DELETE FOLDER
    // ============================================
    elseif ($action === 'delete' && $method === 'DELETE') {
        $folderId = isset($jsonInput['folder_id']) ? (int)$jsonInput['folder_id'] : null;
        
        if (!$folderId) {
            jsonResponse(['ok' => false, 'error' => 'Folder ID required'], 400);
        }
        
        // Check folder exists
        $stmt = $conn->prepare('SELECT id FROM project_folders WHERE id = ? AND project_id = ?');
        $stmt->bind_param('ii', $folderId, $projectId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            jsonResponse(['ok' => false, 'error' => 'Folder not found'], 404);
        }
        
        // Check if folder has children - if so, prevent deletion
        $checkStmt = $conn->prepare('SELECT id FROM project_folders WHERE parent_folder_id = ? LIMIT 1');
        $checkStmt->bind_param('i', $folderId);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            jsonResponse(['ok' => false, 'error' => 'Cannot delete folder with subfolders'], 400);
        }
        
        // Check if folder has files
        $checkStmt = $conn->prepare('SELECT id FROM project_files WHERE folder_id = ? LIMIT 1');
        $checkStmt->bind_param('i', $folderId);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            jsonResponse(['ok' => false, 'error' => 'Cannot delete folder with files'], 400);
        }
        
        // Delete folder
        $stmt = $conn->prepare('DELETE FROM project_folders WHERE id = ? AND project_id = ?');
        $stmt->bind_param('ii', $folderId, $projectId);
        
        if ($stmt->execute()) {
            jsonResponse(['ok' => true, 'message' => 'Folder deleted']);
        } else {
            jsonResponse(['ok' => false, 'error' => $conn->error], 500);
        }
    }
    
    else {
        jsonResponse(['ok' => false, 'error' => 'Unknown action'], 400);
    }
    
} catch (Exception $e) {
    error_log('[ProjectFoldersAPI] Error: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => $e->getMessage()], 500);
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
