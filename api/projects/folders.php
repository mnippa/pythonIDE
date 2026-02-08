<?php
/**
 * Project Folders Management API
 * POST /api/projects/folders/create - Create folder
 * GET /api/projects/folders/list - List folders
 * PUT /api/projects/folders/rename - Rename folder
 * DELETE /api/projects/folders/delete - Delete folder
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

// Require authentication
$user = requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? null;
$projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : (isset($_POST['project_id']) ? (int)$_POST['project_id'] : null);

if (!$projectId) {
    jsonResponse(['ok' => false, 'error' => 'Project ID required'], 400);
}

$conn = getDbConnection();

try {
    // Check project ownership
    $stmt = $conn->prepare('SELECT user_id FROM projects WHERE id = ?');
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $project = $stmt->get_result()->fetch_assoc();
    
    if (!$project || ($project['user_id'] != $user['id'] && $user['role'] !== 'admin')) {
        jsonResponse(['ok' => false, 'error' => 'Access denied'], 403);
    }
    
    // ============================================
    // CREATE FOLDER
    // ============================================
    if ($action === 'create') {
        if ($method !== 'POST') {
            jsonResponse(['ok' => false, 'error' => 'POST required'], 405);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $name = trim($input['name'] ?? '');
        $parentFolderId = isset($input['parent_folder_id']) ? (int)$input['parent_folder_id'] : null;
        $description = trim($input['description'] ?? '');
        
        if (empty($name)) {
            jsonResponse(['ok' => false, 'error' => 'Folder name required'], 400);
        }
        
        // Validate folder name
        if (!preg_match('/^[a-zA-Z0-9._\-äöüß ]{1,255}$/u', $name)) {
            jsonResponse(['ok' => false, 'error' => 'Invalid folder name'], 400);
        }
        
        // Build path
        $path = '';
        if ($parentFolderId) {
            // Check parent folder exists and belongs to this project
            $stmt = $conn->prepare('SELECT path FROM folders WHERE id = ? AND project_id = ?');
            $stmt->bind_param('ii', $parentFolderId, $projectId);
            $stmt->execute();
            $parentFolder = $stmt->get_result()->fetch_assoc();
            
            if (!$parentFolder) {
                jsonResponse(['ok' => false, 'error' => 'Parent folder not found'], 404);
            }
            
            $path = $parentFolder['path'] . '/' . $name;
        } else {
            $path = $name;
        }
        
        // Check if folder already exists at this path
        $stmt = $conn->prepare('SELECT id FROM folders WHERE project_id = ? AND path = ?');
        $stmt->bind_param('is', $projectId, $path);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            jsonResponse(['ok' => false, 'error' => 'Folder already exists at this path'], 409);
        }
        
        // Create folder
        $stmt = $conn->prepare('INSERT INTO folders (project_id, name, path, parent_folder_id, description) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('issis', $projectId, $name, $path, $parentFolderId, $description);
        
        if ($stmt->execute()) {
            $folderId = $conn->insert_id;
            
            jsonResponse([
                'ok' => true,
                'folder' => [
                    'id' => $folderId,
                    'project_id' => $projectId,
                    'name' => $name,
                    'path' => $path,
                    'parent_folder_id' => $parentFolderId,
                    'description' => $description,
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ], 201);
        } else {
            jsonResponse(['ok' => false, 'error' => 'Failed to create folder'], 500);
        }
    }
    
    // ============================================
    // LIST FOLDERS
    // ============================================
    elseif ($action === 'list') {
        if ($method !== 'GET') {
            jsonResponse(['ok' => false, 'error' => 'GET required'], 405);
        }
        
        $parentFolderId = isset($_GET['parent_id']) ? (int)$_GET['parent_id'] : null;
        
        $sql = 'SELECT id, name, path, parent_folder_id, description, created_at, updated_at FROM folders WHERE project_id = ?';
        $params = [$projectId];
        $types = 'i';
        
        if ($parentFolderId !== null) {
            $sql .= ' AND parent_folder_id = ?';
            $params[] = $parentFolderId;
            $types .= 'i';
        } else {
            $sql .= ' AND parent_folder_id IS NULL';
        }
        
        $sql .= ' ORDER BY name ASC';
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $folders = [];
        while ($row = $result->fetch_assoc()) {
            // Count subfolders and files
            $subStmt = $conn->prepare('SELECT COUNT(*) as count FROM folders WHERE parent_folder_id = ?');
            $subStmt->bind_param('i', $row['id']);
            $subStmt->execute();
            $row['subfolder_count'] = $subStmt->get_result()->fetch_assoc()['count'];
            
            $fileStmt = $conn->prepare('SELECT COUNT(*) as count FROM files WHERE folder_id = ?');
            $fileStmt->bind_param('i', $row['id']);
            $fileStmt->execute();
            $row['file_count'] = $fileStmt->get_result()->fetch_assoc()['count'];
            
            $folders[] = $row;
        }
        
        jsonResponse([
            'ok' => true,
            'project_id' => $projectId,
            'parent_folder_id' => $parentFolderId,
            'folders' => $folders,
            'count' => count($folders)
        ]);
    }
    
    // ============================================
    // RENAME FOLDER
    // ============================================
    elseif ($action === 'rename') {
        if ($method !== 'PUT') {
            jsonResponse(['ok' => false, 'error' => 'PUT required'], 405);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $folderId = isset($input['folder_id']) ? (int)$input['folder_id'] : null;
        $newName = trim($input['name'] ?? '');
        
        if (!$folderId || empty($newName)) {
            jsonResponse(['ok' => false, 'error' => 'Folder ID and name required'], 400);
        }
        
        // Check folder exists
        $stmt = $conn->prepare('SELECT path, parent_folder_id FROM folders WHERE id = ? AND project_id = ?');
        $stmt->bind_param('ii', $folderId, $projectId);
        $stmt->execute();
        $folder = $stmt->get_result()->fetch_assoc();
        
        if (!$folder) {
            jsonResponse(['ok' => false, 'error' => 'Folder not found'], 404);
        }
        
        // Build new path
        if ($folder['parent_folder_id']) {
            $stmt = $conn->prepare('SELECT path FROM folders WHERE id = ?');
            $stmt->bind_param('i', $folder['parent_folder_id']);
            $stmt->execute();
            $parentPath = $stmt->get_result()->fetch_assoc()['path'];
            $newPath = $parentPath . '/' . $newName;
        } else {
            $newPath = $newName;
        }
        
        // Update folder
        $stmt = $conn->prepare('UPDATE folders SET name = ?, path = ? WHERE id = ?');
        $stmt->bind_param('ssi', $newName, $newPath, $folderId);
        
        if ($stmt->execute()) {
            // Update all subfolders' paths
            $stmt = $conn->prepare('SELECT id, path FROM folders WHERE project_id = ? AND path LIKE ?');
            $oldPathPattern = str_replace('/', '\/', $folder['path']) . '/%';
            $stmt->bind_param('is', $projectId, $oldPathPattern);
            $stmt->execute();
            $subfolders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            foreach ($subfolders as $subfolder) {
                $updatedPath = str_replace($folder['path'], $newPath, $subfolder['path']);
                $updateStmt = $conn->prepare('UPDATE folders SET path = ? WHERE id = ?');
                $updateStmt->bind_param('si', $updatedPath, $subfolder['id']);
                $updateStmt->execute();
            }
            
            jsonResponse(['ok' => true, 'message' => 'Folder renamed successfully']);
        } else {
            jsonResponse(['ok' => false, 'error' => 'Failed to rename folder'], 500);
        }
    }
    
    // ============================================
    // DELETE FOLDER
    // ============================================
    elseif ($action === 'delete') {
        if ($method !== 'DELETE') {
            jsonResponse(['ok' => false, 'error' => 'DELETE required'], 405);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $folderId = isset($input['folder_id']) ? (int)$input['folder_id'] : null;
        
        if (!$folderId) {
            jsonResponse(['ok' => false, 'error' => 'Folder ID required'], 400);
        }
        
        // Check folder exists
        $stmt = $conn->prepare('SELECT id FROM folders WHERE id = ? AND project_id = ?');
        $stmt->bind_param('ii', $folderId, $projectId);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows === 0) {
            jsonResponse(['ok' => false, 'error' => 'Folder not found'], 404);
        }
        
        // Delete folder (cascade deletes files via foreign key)
        $stmt = $conn->prepare('DELETE FROM folders WHERE id = ? AND project_id = ?');
        $stmt->bind_param('ii', $folderId, $projectId);
        
        if ($stmt->execute()) {
            jsonResponse(['ok' => true, 'message' => 'Folder deleted successfully']);
        } else {
            jsonResponse(['ok' => false, 'error' => 'Failed to delete folder'], 500);
        }
    }
    
    else {
        jsonResponse(['ok' => false, 'error' => 'Unknown action: ' . $action], 400);
    }
    
} catch (Exception $e) {
    jsonResponse(['ok' => false, 'error' => $e->getMessage()], 500);
}
