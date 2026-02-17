<?php
/**
 * Reset all user task attempts for an assignment
 * POST /api/assignments/reset_attempts.php
 * Admin only
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

$user = requireAuth();
$conn = getDbConnection();

// Admin only
if ($user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Admin access required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$assignmentId = isset($input['assignment_id']) ? (int)$input['assignment_id'] : null;

if (!$assignmentId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'assignment_id required']);
    exit;
}

// Check if assignment exists
$stmt = $conn->prepare('SELECT id, title FROM assignments WHERE id = ?');
$stmt->bind_param('i', $assignmentId);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();

if (!$assignment) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Assignment not found']);
    exit;
}

// Reset all user_tasks for this assignment
$stmt = $conn->prepare(
    'UPDATE user_tasks ut
     INNER JOIN tasks t ON t.id = ut.task_id
     SET ut.status = \'unbearbeitet\',
         ut.attempts = 0,
         ut.current_iteration = 1,
         ut.selected_options = NULL,
         ut.text_answer = NULL,
         ut.variable_values = NULL,
         ut.current_code = NULL,
         ut.hints_revealed = NULL,
         ut.completed_at = NULL
     WHERE t.assignment_id = ?'
);

$stmt->bind_param('i', $assignmentId);

if ($stmt->execute()) {
    $affectedRows = $stmt->affected_rows;
    echo json_encode([
        'ok' => true,
        'message' => "Versuche zurückgesetzt für Assignment: {$assignment['title']}",
        'affected_rows' => $affectedRows
    ]);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database error: ' . $stmt->error]);
}
