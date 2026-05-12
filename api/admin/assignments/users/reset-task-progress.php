<?php
/**
 * Admin: Reset one task progress for one user within one assignment
 * POST { assignment_id, user_id, task_id }
 *
 * Workflow:
 * - Keep task status unchanged
 * - Set attempts/checks to 0 only
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
    $taskId = isset($input['task_id']) ? (int)$input['task_id'] : 0;

    if ($assignmentId <= 0 || $userId <= 0 || $taskId <= 0) {
        jsonResponse(['ok' => false, 'error' => 'assignment_id, user_id and task_id required'], 400);
    }

    requireAdminOwnedAssignment($conn, $assignmentId, $admin);

    $selectStmt = $conn->prepare(
        'SELECT ut.id, ut.status, ut.attempts
         FROM user_tasks ut
         INNER JOIN tasks t ON t.id = ut.task_id
         WHERE t.assignment_id = ?
           AND ut.user_id = ?
           AND ut.task_id = ?
         LIMIT 1'
    );
    $selectStmt->bind_param('iii', $assignmentId, $userId, $taskId);
    $selectStmt->execute();
    $row = $selectStmt->get_result()->fetch_assoc();

    if (!$row) {
        jsonResponse(['ok' => false, 'error' => 'user_task not found for assignment/user/task'], 404);
    }

    $userTaskId = (int)$row['id'];
    $statusBefore = (string)($row['status'] ?? 'unbearbeitet');
    $attemptsBefore = (int)($row['attempts'] ?? 0);

    $statusAfter = $statusBefore;
    $updateStmt = $conn->prepare(
        'UPDATE user_tasks
         SET attempts = 0
         WHERE id = ?'
    );
    $updateStmt->bind_param('i', $userTaskId);

    if (!$updateStmt->execute()) {
        jsonResponse(['ok' => false, 'error' => 'Failed to reset task progress'], 500);
    }

    $allTasksStmt = $conn->prepare(
        'SELECT t.id, COALESCE(ut.status, "unbearbeitet") AS status
         FROM tasks t
         LEFT JOIN user_tasks ut ON ut.task_id = t.id AND ut.user_id = ?
         WHERE t.assignment_id = ?'
    );
    $allTasksStmt->bind_param('ii', $userId, $assignmentId);
    $allTasksStmt->execute();
    $allTasks = $allTasksStmt->get_result();

    $total = 0;
    $cntUn = 0;
    $cntIn = 0;
    $cntPass = 0;
    $cntFail = 0;
    while ($r = $allTasks->fetch_assoc()) {
        $total++;
        $st = (string)($r['status'] ?? 'unbearbeitet');
        if ($st === 'unbearbeitet') $cntUn++;
        elseif ($st === 'in-progress') $cntIn++;
        elseif ($st === 'passed') $cntPass++;
        elseif ($st === 'failed') $cntFail++;
        else $cntUn++;
    }

    $allUnbearbeitet = $total > 0 && $cntUn === $total;
    $allDone         = $total > 0 && ($cntPass + $cntFail) === $total;
    if ($total === 0 || $allUnbearbeitet) {
        $assignmentStatus = 'assigned';
    } elseif ($allDone) {
        $assignmentStatus = 'submitted';
    } else {
        $assignmentStatus = 'in_progress';
    }

    $uaSelect = $conn->prepare('SELECT id, status FROM user_assignments WHERE user_id = ? AND assignment_id = ? LIMIT 1');
    $uaSelect->bind_param('ii', $userId, $assignmentId);
    $uaSelect->execute();
    $uaRow = $uaSelect->get_result()->fetch_assoc();
    $now = date('Y-m-d H:i:s');

    // Detect rework: admin is reopening a previously closed assignment
    $prevAssignmentStatus = $uaRow['status'] ?? 'assigned';
    $setRework = ($assignmentStatus === 'in_progress')
        && in_array($prevAssignmentStatus, ['submitted', 'passed', 'failed'], true);

    if ($uaRow) {
        $uaId = (int)$uaRow['id'];
        if ($assignmentStatus === 'submitted') {
            $uaUpd = $conn->prepare('UPDATE user_assignments SET status = ?, submitted_at = ? WHERE id = ?');
            $uaUpd->bind_param('ssi', $assignmentStatus, $now, $uaId);
        } elseif ($setRework) {
            $uaUpd = $conn->prepare('UPDATE user_assignments SET status = ?, is_rework = 1 WHERE id = ?');
            $uaUpd->bind_param('si', $assignmentStatus, $uaId);
        } else {
            $uaUpd = $conn->prepare('UPDATE user_assignments SET status = ? WHERE id = ?');
            $uaUpd->bind_param('si', $assignmentStatus, $uaId);
        }
        $uaUpd->execute();
    } else {
        $submittedAt = $assignmentStatus === 'submitted' ? $now : null;
        $adminId = (int)$admin['id'];
        $uaIns = $conn->prepare('INSERT INTO user_assignments (user_id, assignment_id, assigned_by, status, submitted_at) VALUES (?, ?, ?, ?, ?)');
        $uaIns->bind_param('iiiss', $userId, $assignmentId, $adminId, $assignmentStatus, $submittedAt);
        $uaIns->execute();
    }

    jsonResponse([
        'ok' => true,
        'assignment_id' => $assignmentId,
        'user_id' => $userId,
        'task_id' => $taskId,
        'status_before' => $statusBefore,
        'status_after' => $statusAfter,
        'attempts_before' => $attemptsBefore,
        'attempts_after' => 0,
        'updated_rows' => $updateStmt->affected_rows,
        'assignment_status' => $assignmentStatus,
    ]);
} catch (Exception $e) {
    error_log('Reset single user task progress error: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'Failed to reset task progress'], 500);
}
