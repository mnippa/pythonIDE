<?php
/**
 * Assignment Files API (Read-Only)
 * GET /api/assignments/files/list - List files for assignment
 * GET /api/assignments/files/read - Read file content (read-only)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

// Require authentication
$user = requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? null;
$assignmentId = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : null;

if (!$assignmentId) {
    jsonResponse(['ok' => false, 'error' => 'Assignment ID required'], 400);
}

$conn = getDbConnection();

try {
    // Check assignment access
    $stmt = $conn->prepare('
        SELECT a.id, a.user_id FROM assignments a
        WHERE a.id = ? AND (a.user_id = ? OR ? = "admin")
    ');
    $isAdmin = $user['role'] === 'admin' ? 'admin' : 'user';
    $stmt->bind_param('iss', $assignmentId, $user['id'], $isAdmin);
    $stmt->execute();
    $assignment = $stmt->get_result()->fetch_assoc();
    
    if (!$assignment) {
        jsonResponse(['ok' => false, 'error' => 'Assignment not found or access denied'], 403);
    }
    
    // ============================================
    // LIST ASSIGNMENT FILES
    // ============================================
    if ($action === 'list') {
        if ($method !== 'GET') {
            jsonResponse(['ok' => false, 'error' => 'GET required'], 405);
        }
        
        $taskId = isset($_GET['task_id']) ? (int)$_GET['task_id'] : null;
        
        $sql = 'SELECT id, name, file_type, content, is_template, is_starter_code, is_solution, is_hidden FROM assignment_files WHERE assignment_id = ?';
        $params = [$assignmentId];
        $types = 'i';
        
        // Only show non-hidden files to students
        // Admins see all files
        if ($user['role'] !== 'admin') {
            $sql .= ' AND is_hidden = 0';
        }
        
        if ($taskId) {
            $sql .= ' AND task_id = ?';
            $params[] = $taskId;
            $types .= 'i';
        }
        
        $sql .= ' ORDER BY name ASC';
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $files = [];
        while ($row = $result->fetch_assoc()) {
            // Don't return raw content in list (too large)
            unset($row['content']);
            $files[] = $row;
        }
        
        jsonResponse([
            'ok' => true,
            'assignment_id' => $assignmentId,
            'task_id' => $taskId,
            'files' => $files,
            'count' => count($files),
            'user_is_admin' => $user['role'] === 'admin'
        ]);
    }
    
    // ============================================
    // READ ASSIGNMENT FILE (Read-Only)
    // ============================================
    elseif ($action === 'read') {
        if ($method !== 'GET') {
            jsonResponse(['ok' => false, 'error' => 'GET required'], 405);
        }
        
        $fileId = isset($_GET['file_id']) ? (int)$_GET['file_id'] : null;
        
        if (!$fileId) {
            jsonResponse(['ok' => false, 'error' => 'File ID required'], 400);
        }
        
        $sql = 'SELECT id, name, file_type, content, is_template, is_starter_code, is_solution, is_hidden FROM assignment_files WHERE id = ? AND assignment_id = ?';
        
        // Only show non-hidden files to students
        if ($user['role'] !== 'admin') {
            $sql .= ' AND is_hidden = 0';
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $fileId, $assignmentId);
        $stmt->execute();
        $file = $stmt->get_result()->fetch_assoc();
        
        if (!$file) {
            jsonResponse(['ok' => false, 'error' => 'File not found'], 404);
        }
        
        // Handle binary files (images)
        $isBase64 = false;
        if ($file['file_type'] === 'image') {
            $file['content'] = base64_encode($file['content']);
            $isBase64 = true;
        }
        
        jsonResponse([
            'ok' => true,
            'file' => $file,
            'is_base64' => $isBase64,
            'read_only' => $user['role'] !== 'admin'
        ]);
    }
    
    // ============================================
    // ATTEMPT TO WRITE ASSIGNMENT FILE (Blocked)
    // ============================================
    elseif ($action === 'update' || $action === 'delete') {
        // Assignment files are read-only for students
        if ($user['role'] !== 'admin') {
            jsonResponse(['ok' => false, 'error' => 'Assignment files are read-only'], 403);
        }
        
        // Admin only: Allow updates
        if ($action === 'update') {
            $input = json_decode(file_get_contents('php://input'), true);
            $fileId = isset($input['file_id']) ? (int)$input['file_id'] : null;
            $content = $input['content'] ?? null;
            
            if (!$fileId || $content === null) {
                jsonResponse(['ok' => false, 'error' => 'File ID and content required'], 400);
            }
            
            $stmt = $conn->prepare('UPDATE assignment_files SET content = ? WHERE id = ? AND assignment_id = ?');
            $stmt->bind_param('sii', $content, $fileId, $assignmentId);
            
            if ($stmt->execute()) {
                jsonResponse(['ok' => true, 'message' => 'File updated']);
            } else {
                jsonResponse(['ok' => false, 'error' => 'Update failed'], 500);
            }
        }
    }
    
    else {
        jsonResponse(['ok' => false, 'error' => 'Unknown action: ' . $action], 400);
    }
    
} catch (Exception $e) {
    jsonResponse(['ok' => false, 'error' => $e->getMessage()], 500);
}
