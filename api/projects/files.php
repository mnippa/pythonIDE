<?php
/**
 * Project Files Management API
 * POST /api/projects/files/create - Create/upload file
 * GET /api/projects/files/list - List files
 * GET /api/projects/files/read - Read file content
 * PUT /api/projects/files/update - Update file content
 * DELETE /api/projects/files/delete - Delete file
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
    // CREATE FILE
    // ============================================
    if ($action === 'create') {
        if ($method !== 'POST') {
            jsonResponse(['ok' => false, 'error' => 'POST required'], 405);
        }
        
        $folderId = isset($_POST['folder_id']) ? (int)$_POST['folder_id'] : null;
        $fileName = trim($_POST['name'] ?? '');
        $fileType = $_POST['file_type'] ?? 'text'; // python, json, image, text, other
        $content = $_POST['content'] ?? '';
        
        if (empty($fileName)) {
            jsonResponse(['ok' => false, 'error' => 'File name required'], 400);
        }
        
        // Handle file upload vs raw content
        if (isset($_FILES['file'])) {
            $file = $_FILES['file'];
            $fileName = basename($file['name']);
            $content = file_get_contents($file['tmp_name']);
        }
        
        // Validate file name
        if (!preg_match('/^[a-zA-Z0-9._\-äöüß ]{1,255}$/', $fileName)) {
            jsonResponse(['ok' => false, 'error' => 'Invalid file name'], 400);
        }
        
        // Get extension
        $pathInfo = pathinfo($fileName);
        $extension = $pathInfo['extension'] ?? '';
        
        // Validate extension based on file type
        $validExtensions = [
            'python' => ['py'],
            'json' => ['json'],
            'image' => ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'],
            'text' => ['txt', 'md', 'csv', 'log'],
            'other' => ['*']
        ];
        
        if ($fileType !== 'other' && !in_array(strtolower($extension), $validExtensions[$fileType] ?? [])) {
            jsonResponse(['ok' => false, 'error' => 'Invalid file extension for type: ' . $fileType], 400);
        }
        
        // Get folder path
        $folderPath = '';
        if ($folderId) {
            $stmt = $conn->prepare('SELECT path FROM folders WHERE id = ? AND project_id = ?');
            $stmt->bind_param('ii', $folderId, $projectId);
            $stmt->execute();
            $folder = $stmt->get_result()->fetch_assoc();
            
            if (!$folder) {
                jsonResponse(['ok' => false, 'error' => 'Folder not found'], 404);
            }
            
            $folderPath = $folder['path'] . '/';
        }
        
        $filePath = $folderPath . $fileName;
        
        // Check if file already exists
        $stmt = $conn->prepare('SELECT id FROM files WHERE project_id = ? AND file_path = ?');
        $stmt->bind_param('is', $projectId, $filePath);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            jsonResponse(['ok' => false, 'error' => 'File already exists at this path'], 409);
        }
        
        // Get MIME type
        $mimeType = mime_content_type_custom($fileName, $extension);
        
        // Determine if binary
        $isBinary = isBinaryFile($fileType);
        
        // Create file
        $fileSize = strlen($content);
        $stmt = $conn->prepare('INSERT INTO files (folder_id, project_id, name, file_type, extension, mime_type, content, file_path, file_size, is_binary) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('iisssssii', $folderId, $projectId, $fileName, $fileType, $extension, $mimeType, $content, $filePath, $fileSize, $isBinary);
        
        if ($stmt->execute()) {
            $fileId = $conn->insert_id;
            
            jsonResponse([
                'ok' => true,
                'file' => [
                    'id' => $fileId,
                    'project_id' => $projectId,
                    'folder_id' => $folderId,
                    'name' => $fileName,
                    'file_type' => $fileType,
                    'extension' => $extension,
                    'mime_type' => $mimeType,
                    'file_path' => $filePath,
                    'file_size' => $fileSize,
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ], 201);
        } else {
            jsonResponse(['ok' => false, 'error' => 'Failed to create file: ' . $conn->error], 500);
        }
    }
    
    // ============================================
    // LIST FILES
    // ============================================
    elseif ($action === 'list') {
        if ($method !== 'GET') {
            jsonResponse(['ok' => false, 'error' => 'GET required'], 405);
        }
        
        $folderId = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : null;
        
        $sql = 'SELECT id, name, file_type, extension, mime_type, file_path, file_size, folder_id, created_at, updated_at FROM files WHERE project_id = ?';
        $params = [$projectId];
        $types = 'i';
        
        if ($folderId !== null) {
            $sql .= ' AND folder_id = ?';
            $params[] = $folderId;
            $types .= 'i';
        } else {
            $sql .= ' AND folder_id IS NULL';
        }
        
        $sql .= ' ORDER BY name ASC';
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $files = [];
        while ($row = $result->fetch_assoc()) {
            // Don't return file content in list view
            unset($row['content']);
            $files[] = $row;
        }
        
        jsonResponse([
            'ok' => true,
            'project_id' => $projectId,
            'folder_id' => $folderId,
            'files' => $files,
            'count' => count($files)
        ]);
    }
    
    // ============================================
    // READ FILE
    // ============================================
    elseif ($action === 'read') {
        if ($method !== 'GET') {
            jsonResponse(['ok' => false, 'error' => 'GET required'], 405);
        }
        
        $fileId = isset($_GET['file_id']) ? (int)$_GET['file_id'] : null;
        
        if (!$fileId) {
            jsonResponse(['ok' => false, 'error' => 'File ID required'], 400);
        }
        
        $stmt = $conn->prepare('SELECT id, name, file_type, extension, mime_type, file_path, file_size, content, created_at FROM files WHERE id = ? AND project_id = ?');
        $stmt->bind_param('ii', $fileId, $projectId);
        $stmt->execute();
        $file = $stmt->get_result()->fetch_assoc();
        
        if (!$file) {
            jsonResponse(['ok' => false, 'error' => 'File not found'], 404);
        }
        
        // For binary files, encode as base64
        if ($file['file_type'] === 'image') {
            $file['content'] = base64_encode($file['content']);
            $file['is_base64'] = true;
        }
        
        jsonResponse([
            'ok' => true,
            'file' => $file
        ]);
    }
    
    // ============================================
    // UPDATE FILE
    // ============================================
    elseif ($action === 'update') {
        if ($method !== 'PUT') {
            jsonResponse(['ok' => false, 'error' => 'PUT required'], 405);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $fileId = isset($input['file_id']) ? (int)$input['file_id'] : null;
        $content = $input['content'] ?? null;
        
        if (!$fileId || $content === null) {
            jsonResponse(['ok' => false, 'error' => 'File ID and content required'], 400);
        }
        
        // Check file exists
        $stmt = $conn->prepare('SELECT id, file_type FROM files WHERE id = ? AND project_id = ?');
        $stmt->bind_param('ii', $fileId, $projectId);
        $stmt->execute();
        $file = $stmt->get_result()->fetch_assoc();
        
        if (!$file) {
            jsonResponse(['ok' => false, 'error' => 'File not found'], 404);
        }
        
        // Update only text files (not images)
        if ($file['file_type'] === 'image') {
            jsonResponse(['ok' => false, 'error' => 'Cannot update binary files'], 400);
        }
        
        $fileSize = strlen($content);
        $now = date('Y-m-d H:i:s');
        
        $stmt = $conn->prepare('UPDATE files SET content = ?, file_size = ?, updated_at = ? WHERE id = ?');
        $stmt->bind_param('sisi', $content, $fileSize, $now, $fileId);
        
        if ($stmt->execute()) {
            jsonResponse(['ok' => true, 'message' => 'File updated successfully']);
        } else {
            jsonResponse(['ok' => false, 'error' => 'Failed to update file'], 500);
        }
    }
    
    // ============================================
    // DELETE FILE
    // ============================================
    elseif ($action === 'delete') {
        if ($method !== 'DELETE') {
            jsonResponse(['ok' => false, 'error' => 'DELETE required'], 405);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $fileId = isset($input['file_id']) ? (int)$input['file_id'] : null;
        
        if (!$fileId) {
            jsonResponse(['ok' => false, 'error' => 'File ID required'], 400);
        }
        
        // Check file exists
        $stmt = $conn->prepare('SELECT id FROM files WHERE id = ? AND project_id = ?');
        $stmt->bind_param('ii', $fileId, $projectId);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows === 0) {
            jsonResponse(['ok' => false, 'error' => 'File not found'], 404);
        }
        
        // Delete file
        $stmt = $conn->prepare('DELETE FROM files WHERE id = ? AND project_id = ?');
        $stmt->bind_param('ii', $fileId, $projectId);
        
        if ($stmt->execute()) {
            jsonResponse(['ok' => true, 'message' => 'File deleted successfully']);
        } else {
            jsonResponse(['ok' => false, 'error' => 'Failed to delete file'], 500);
        }
    }
    
    else {
        jsonResponse(['ok' => false, 'error' => 'Unknown action: ' . $action], 400);
    }
    
} catch (Exception $e) {
    jsonResponse(['ok' => false, 'error' => $e->getMessage()], 500);
}

// ============================================
// Helper Functions
// ============================================

function mime_content_type_custom($fileName, $extension) {
    $mimeTypes = [
        'pdf' => 'application/pdf',
        'txt' => 'text/plain',
        'csv' => 'text/csv',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'py' => 'text/x-python',
        'js' => 'text/javascript',
        'css' => 'text/css',
        'html' => 'text/html',
        'md' => 'text/markdown',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
    ];
    
    $ext = strtolower($extension);
    return $mimeTypes[$ext] ?? 'application/octet-stream';
}

function isBinaryFile($fileType) {
    return $fileType === 'image' || $fileType === 'other';
}
