<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../auth/middleware.php';
header('Content-Type: application/json; charset=utf-8');

$admin = requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid JSON'], 400);
}
$assignmentId = (int)($input['assignment_id'] ?? 0);
$userId = (int)($input['user_id'] ?? 0);
$taskId = (int)($input['task_id'] ?? 0);
$usage = $input['usage'] ?? '';
if ($assignmentId <= 0 || $userId <= 0 || $taskId <= 0 || !in_array($usage, ['checks', 'detective'], true)) {
    jsonResponse(['ok' => false, 'error' => 'Assignment, Teilnehmer, Task und Reset-Art sind erforderlich.'], 400);
}
$conn = getDbConnection();
requireAdminOwnedAssignment($conn, $assignmentId, $admin);
$transaction = false;
try {
    if (!$conn->begin_transaction()) throw new RuntimeException('Could not start reset transaction');
    $transaction = true;
    // Use the same user lock as the detective quota endpoint.
    $lock = $conn->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
    $lock->bind_param('i', $userId);
    if (!$lock->execute()) throw new RuntimeException('Could not lock participant');
    if (!$lock->get_result()->fetch_assoc()) {
        $conn->rollback();
        $transaction = false;
        jsonResponse(['ok' => false, 'error' => 'Teilnehmer nicht gefunden.'], 404);
    }
    $task = $conn->prepare(
        'SELECT t.id FROM tasks t INNER JOIN user_assignments ua ON ua.assignment_id = t.assignment_id
         WHERE t.id = ? AND t.assignment_id = ? AND ua.user_id = ?'
    );
    $task->bind_param('iii', $taskId, $assignmentId, $userId);
    if (!$task->execute()) throw new RuntimeException('Could not validate task assignment');
    if (!$task->get_result()->fetch_assoc()) {
        $conn->rollback();
        $transaction = false;
        jsonResponse(['ok' => false, 'error' => 'Task ist diesem Teilnehmer in diesem Assignment nicht zugeordnet.'], 404);
    }
    $stmt = $conn->prepare($usage === 'checks'
        ? 'UPDATE user_tasks SET attempts = 0 WHERE user_id = ? AND task_id = ?'
        : 'DELETE FROM solution_detective_reports WHERE user_id = ? AND task_id = ?');
    $stmt->bind_param('ii', $userId, $taskId);
    if (!$stmt->execute()) throw new RuntimeException('Could not reset task usage');
    if (!$conn->commit()) throw new RuntimeException('Could not commit reset');
    $transaction = false;
    jsonResponse(['ok' => true, 'usage' => $usage, 'task_id' => $taskId, 'user_id' => $userId]);
} catch (Throwable $e) {
    if ($transaction) $conn->rollback();
    error_log('Reset task usage: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'Die Nutzungen konnten nicht zurückgesetzt werden.'], 500);
}
