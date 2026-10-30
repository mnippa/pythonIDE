<?php
/**
 * Delete Task API (Admin only)
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../auth/middleware.php';
require_once __DIR__ . '/../../../components/task_order.php';

header('Content-Type: application/json');

$user = requireAdmin();
$conn = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$taskId = isset($input['id']) ? (int)$input['id'] : null;

if (!$taskId) {
    jsonResponse(['ok' => false, 'error' => 'Task ID required'], 400);
}

$task = requireAdminOwnedTask($conn, $taskId, $user);
requireTaskAssignmentUnlocked($conn, $taskId);

try {
    $conn->begin_transaction();
    lockAssignmentTaskOrder($conn, (int)$task['assignment_id']);
    requireTaskAssignmentUnlocked($conn, $taskId);
    
    // Delete task
    $deleteStmt = $conn->prepare('DELETE FROM tasks WHERE id = ?');
    $deleteStmt->bind_param('i', $taskId);
    if (!$deleteStmt->execute()) {
        throw new RuntimeException('Could not delete task');
    }
    
    // Delete task options if any
    $deleteOptionsStmt = $conn->prepare('DELETE FROM task_options WHERE task_id = ?');
    $deleteOptionsStmt->bind_param('i', $taskId);
    if (!$deleteOptionsStmt->execute()) {
        throw new RuntimeException('Could not delete task options');
    }
    
    // Delete user attempts
    $deleteAttemptsStmt = $conn->prepare('DELETE FROM user_tasks WHERE task_id = ?');
    $deleteAttemptsStmt->bind_param('i', $taskId);
    if (!$deleteAttemptsStmt->execute()) {
        throw new RuntimeException('Could not delete user attempts');
    }
    normalizeTaskPositions($conn, (int)$task['assignment_id']);
    $conn->commit();
    
    jsonResponse([
        'ok' => true,
        'message' => "Task #{$taskId} ({$task['title']}) deleted successfully",
        'task_id' => $taskId,
        'assignment_id' => $task['assignment_id']
    ]);
    
} catch (Throwable $e) {
    $conn->rollback();
    error_log('Admin task deletion failed: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => $e->getMessage()], 500);
}
