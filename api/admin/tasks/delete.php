<?php
/**
 * Delete Task API (Admin only)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/auth/middleware.php';

header('Content-Type: application/json');

$user = requireAdmin();
$pdo = getPdoConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$taskId = isset($input['id']) ? (int)$input['id'] : null;

if (!$taskId) {
    jsonResponse(['ok' => false, 'error' => 'Task ID required'], 400);
}

try {
    // Get task info first
    $stmt = $pdo->prepare('SELECT id, title, assignment_id FROM tasks WHERE id = ?');
    $stmt->execute([$taskId]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$task) {
        jsonResponse(['ok' => false, 'error' => 'Task not found'], 404);
    }
    
    // Delete task
    $deleteStmt = $pdo->prepare('DELETE FROM tasks WHERE id = ?');
    $deleteStmt->execute([$taskId]);
    
    // Delete task options if any
    $deleteOptionsStmt = $pdo->prepare('DELETE FROM task_options WHERE task_id = ?');
    $deleteOptionsStmt->execute([$taskId]);
    
    // Delete user attempts
    $deleteAttemptsStmt = $pdo->prepare('DELETE FROM user_tasks WHERE task_id = ?');
    $deleteAttemptsStmt->execute([$taskId]);
    
    jsonResponse([
        'ok' => true,
        'message' => "Task #{$taskId} ({$task['title']}) deleted successfully",
        'task_id' => $taskId,
        'assignment_id' => $task['assignment_id']
    ]);
    
} catch (Exception $e) {
    jsonResponse(['ok' => false, 'error' => $e->getMessage()], 500);
}
