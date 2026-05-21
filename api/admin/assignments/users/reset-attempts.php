<?php
/**
 * Admin: Reset all task attempts for one user within one assignment
 * POST { assignment_id, user_id }
 */

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../auth/middleware.php';

header('Content-Type: application/json');

try {
    $admin = requireAdmin();
    $conn = getDbConnection();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(['ok' => false, 'error' => 'Invalid JSON'], 400);
    }

    $assignmentId = isset($input['assignment_id']) ? (int)$input['assignment_id'] : 0;
    $userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;

    if ($assignmentId <= 0 || $userId <= 0) {
        jsonResponse(['ok' => false, 'error' => 'assignment_id and user_id required'], 400);
    }

    requireAdminOwnedAssignment($conn, $assignmentId, $admin);

    $hasSubmissionComment = false;
    $commentColumnCheck = $conn->query("SHOW COLUMNS FROM user_tasks LIKE 'submission_comment'");
    if ($commentColumnCheck && $commentColumnCheck->num_rows > 0) {
        $hasSubmissionComment = true;
    }

    $submissionCommentSql = $hasSubmissionComment ? ',
             ut.submission_comment = NULL' : '';
    $stmt = $conn->prepare(
        'UPDATE user_tasks ut
         INNER JOIN tasks t ON t.id = ut.task_id
         SET ut.status = "unbearbeitet",
             ut.attempts = 0,
             ut.run_count = 0,
             ut.current_iteration = 1,
             ut.selected_options = NULL,
             ut.text_answer = NULL,
             ut.variable_values = NULL,
             ut.hints_revealed = NULL,
             ut.completed_at = NULL' . $submissionCommentSql . '
         WHERE t.assignment_id = ?
           AND ut.user_id = ?'
    );
    $stmt->bind_param('ii', $assignmentId, $userId);

    if (!$stmt->execute()) {
        jsonResponse(['ok' => false, 'error' => 'Failed to reset attempts'], 500);
    }

    jsonResponse([
        'ok' => true,
        'assignment_id' => $assignmentId,
        'user_id' => $userId,
        'affected_rows' => $stmt->affected_rows,
    ]);
} catch (Exception $e) {
    error_log('Reset user attempts error: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'Failed to reset attempts'], 500);
}
