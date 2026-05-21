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

// Support admin simulation via test_user_id (read-only context for test view)
if (isset($_GET['test_user_id'])) {
    $testUserId = (int)$_GET['test_user_id'];

    if (($user['role'] ?? '') !== 'admin') {
        jsonResponse(['ok' => false, 'error' => 'Unauthorized: Admin access required for test_user_id'], 403);
    }

    if ($testUserId <= 0) {
        jsonResponse(['ok' => false, 'error' => 'Invalid test_user_id'], 400);
    }

    $userCheckStmt = $conn->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
    $userCheckStmt->bind_param('i', $testUserId);
    $userCheckStmt->execute();
    $exists = $userCheckStmt->get_result()->fetch_assoc();

    if (!$exists) {
        jsonResponse(['ok' => false, 'error' => 'Test user not found'], 404);
    }

    $userId = $testUserId;
}

$columnExists = function (mysqli $conn, string $table, string $column): bool {
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $check = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $check && $check->num_rows > 0;
};

$hasRunCount = $columnExists($conn, 'user_tasks', 'run_count');
$runSelect = $hasRunCount ? ', run_count' : '';
$hasCurrentIteration = $columnExists($conn, 'user_tasks', 'current_iteration');
$iterationSelect = $hasCurrentIteration ? ', current_iteration' : '';
$hasIterationValues = $columnExists($conn, 'user_tasks', 'iteration_values');
$iterationValuesSelect = $hasIterationValues ? ', iteration_values' : '';
$hasSubmissionComment = $columnExists($conn, 'user_tasks', 'submission_comment');
$submissionCommentSelect = $hasSubmissionComment ? ', submission_comment' : '';

if ($taskId) {
    // Get single task progress
    $stmt = $conn->prepare(
        'SELECT id, user_id, task_id, status, attempts' . $runSelect . $iterationSelect . $iterationValuesSelect . $submissionCommentSelect . ', current_code, selected_options, text_answer, variable_values, hints_revealed, started_at, completed_at, updated_at
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
        if ($hasRunCount) {
            $task['run_count'] = (int)$task['run_count'];
        }
        if ($hasCurrentIteration) {
            $task['current_iteration'] = (int)$task['current_iteration'];
        }
        if ($hasIterationValues && isset($task['iteration_values'])) {
            $task['iteration_values'] = $task['iteration_values'] ? json_decode($task['iteration_values'], true) : null;
        }
        if ($hasSubmissionComment) {
            $task['submission_comment'] = $task['submission_comment'] ?? null;
        }
        $task['hints_revealed'] = $task['hints_revealed'] ? json_decode($task['hints_revealed'], true) : [];
        jsonResponse(['ok' => true, 'task' => $task]);
    } else {
        jsonResponse(['ok' => true, 'task' => null]);
    }
} elseif ($assignmentId) {
    // Get all tasks progress for assignment
    $stmt = $conn->prepare(
        'SELECT ut.id, ut.user_id, ut.task_id, ut.status, ut.attempts' . $runSelect . $iterationSelect . $iterationValuesSelect . ', ut.current_code, ut.selected_options, ut.text_answer, ut.variable_values, ut.hints_revealed, ut.started_at, ut.completed_at, ut.updated_at
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
        if ($hasRunCount) {
            $row['run_count'] = (int)$row['run_count'];
        }
        if ($hasCurrentIteration) {
            $row['current_iteration'] = (int)$row['current_iteration'];
        }
        if ($hasIterationValues && isset($row['iteration_values'])) {
            $row['iteration_values'] = $row['iteration_values'] ? json_decode($row['iteration_values'], true) : null;
        }
        if ($hasSubmissionComment) {
            $row['submission_comment'] = $row['submission_comment'] ?? null;
        }
        $row['hints_revealed'] = $row['hints_revealed'] ? json_decode($row['hints_revealed'], true) : [];
        $tasks[] = $row;
    }
    
    jsonResponse(['ok' => true, 'tasks' => $tasks]);
} else {
    jsonResponse(['ok' => false, 'error' => 'assignment_id or task_id required'], 400);
}
