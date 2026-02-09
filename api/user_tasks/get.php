<?php
/**
 * Get user task progress for specific tasks
 * Returns task progress data for user's tasks
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

$user = requireAuth();
$conn = getDbConnection();

$assignmentId = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : null;
$taskId = isset($_GET['task_id']) ? (int)$_GET['task_id'] : null;

$userId = (int)$user['id'];

if ($taskId) {
    // Get single task progress
    $stmt = $conn->prepare(
        'SELECT id, user_id, task_id, status, attempts, current_code, hints_revealed, started_at, completed_at, updated_at
         FROM user_tasks 
         WHERE user_id = ? AND task_id = ?'
    );
    $stmt->bind_param('ii', $userId, $taskId);
    $stmt->execute();
    $result = $stmt->get_result();
    $task = $result->fetch_assoc();
    
    if ($task) {
        $task['id'] = (int)$task['id'];
        $task['user_id'] = (int)$task['user_id'];
        $task['task_id'] = (int)$task['task_id'];
        $task['attempts'] = (int)$task['attempts'];
        $task['hints_revealed'] = $task['hints_revealed'] ? json_decode($task['hints_revealed'], true) : [];
        jsonResponse(['ok' => true, 'task' => $task]);
    } else {
        jsonResponse(['ok' => true, 'task' => null]);
    }
} elseif ($assignmentId) {
    // Get all tasks progress for assignment
    $stmt = $conn->prepare(
        'SELECT ut.id, ut.user_id, ut.task_id, ut.status, ut.attempts, ut.current_code, ut.hints_revealed, ut.started_at, ut.completed_at, ut.updated_at
         FROM user_tasks ut
         INNER JOIN tasks t ON t.id = ut.task_id
         WHERE ut.user_id = ? AND t.assignment_id = ?
         ORDER BY t.position ASC'
    );
    $stmt->bind_param('ii', $userId, $assignmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $tasks = [];
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['user_id'] = (int)$row['user_id'];
        $row['task_id'] = (int)$row['task_id'];
        $row['attempts'] = (int)$row['attempts'];
        $row['hints_revealed'] = $row['hints_revealed'] ? json_decode($row['hints_revealed'], true) : [];
        $tasks[] = $row;
    }
    
    jsonResponse(['ok' => true, 'tasks' => $tasks]);
} else {
    jsonResponse(['ok' => false, 'error' => 'assignment_id or task_id required'], 400);
}
