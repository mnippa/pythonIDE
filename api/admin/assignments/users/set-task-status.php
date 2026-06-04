<?php
/**
 * Admin: Set single user task status and keep assignment status in sync.
 * POST { assignment_id, user_id, task_id, status, attempts?, reset_checks?, admin_feedback_comment? }
 */

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../auth/middleware.php';

header('Content-Type: application/json');

function boolInput($value): bool {
    if (is_bool($value)) return $value;
    if (is_int($value)) return $value === 1;
    if (is_string($value)) {
        $v = strtolower(trim($value));
        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }
    return false;
}

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
    $statusRequested = isset($input['status']) ? (string)$input['status'] : '';

    if ($assignmentId <= 0 || $userId <= 0 || $taskId <= 0 || $statusRequested === '') {
        jsonResponse(['ok' => false, 'error' => 'assignment_id, user_id, task_id and status required'], 400);
    }

    requireAdminOwnedAssignment($conn, $assignmentId, $admin);

    $allowed = ['unbearbeitet', 'in-progress', 'submitted', 'passed', 'failed', 'missed'];
    if (!in_array($statusRequested, $allowed, true)) {
        jsonResponse(['ok' => false, 'error' => 'Invalid status'], 400);
    }

    $statusEffective = $statusRequested === 'missed' ? 'failed' : $statusRequested;

    $taskCheck = $conn->prepare('SELECT id FROM tasks WHERE id = ? AND assignment_id = ? LIMIT 1');
    $taskCheck->bind_param('ii', $taskId, $assignmentId);
    $taskCheck->execute();
    if (!$taskCheck->get_result()->fetch_assoc()) {
        jsonResponse(['ok' => false, 'error' => 'Task not found in assignment'], 404);
    }

    $setAttempts = null;
    if (array_key_exists('attempts', $input)) {
        $setAttempts = max(0, (int)$input['attempts']);
    }
    if (boolInput($input['reset_checks'] ?? false)) {
        $setAttempts = 0;
    }
    $fullReset = boolInput($input['full_reset'] ?? false);
    if ($fullReset && $statusEffective === 'unbearbeitet') {
        $setAttempts = 0;
    }

    $utSelect = $conn->prepare('SELECT id, attempts, status FROM user_tasks WHERE user_id = ? AND task_id = ? LIMIT 1');
    $utSelect->bind_param('ii', $userId, $taskId);
    $utSelect->execute();
    $utRow = $utSelect->get_result()->fetch_assoc();

    $hasSubmissionComment = false;
    $commentColumnCheck = $conn->query("SHOW COLUMNS FROM user_tasks LIKE 'submission_comment'");
    if ($commentColumnCheck && $commentColumnCheck->num_rows > 0) {
        $hasSubmissionComment = true;
    }

    $hasAdminFeedbackComment = false;
    $adminFeedbackColumnCheck = $conn->query("SHOW COLUMNS FROM user_tasks LIKE 'admin_feedback_comment'");
    if ($adminFeedbackColumnCheck && $adminFeedbackColumnCheck->num_rows > 0) {
        $hasAdminFeedbackComment = true;
    }

    $hasAdminFeedbackInput = array_key_exists('admin_feedback_comment', $input);
    $adminFeedbackComment = null;
    if ($hasAdminFeedbackInput) {
        $rawFeedback = trim((string)$input['admin_feedback_comment']);
        $adminFeedbackComment = $rawFeedback !== '' ? $rawFeedback : null;
    }

    $attemptsAfter = $setAttempts !== null ? $setAttempts : (int)($utRow['attempts'] ?? 0);
    $isFinal = in_array($statusEffective, ['submitted', 'passed', 'failed'], true);
    $now = date('Y-m-d H:i:s');

    if ($fullReset && $statusEffective === 'unbearbeitet') {
        // Full content reset: determine task type for targeted field clearing
        $taskTypeStmt = $conn->prepare('SELECT task_type FROM tasks WHERE id = ? LIMIT 1');
        $taskTypeStmt->bind_param('i', $taskId);
        $taskTypeStmt->execute();
        $taskTypeRow = $taskTypeStmt->get_result()->fetch_assoc();
        $taskType = $taskTypeRow['task_type'] ?? 'code';

        $isIterative = in_array($taskType, ['code_reading', 'code_random_complex'], true);
        $isCode     = in_array($taskType, ['code', 'code_ui'], true);

        if ($utRow) {
            $utId = (int)$utRow['id'];
            if ($isIterative) {
                $upd = $conn->prepare(
                    'UPDATE user_tasks SET status = "unbearbeitet", attempts = 0, current_iteration = 1,
                     variable_values = NULL, hints_revealed = "[]", completed_at = NULL, started_at = NULL' . ($hasSubmissionComment ? ', submission_comment = NULL' : '') . '
                     WHERE id = ?'
                );
                $upd->bind_param('i', $utId);
            } elseif ($isCode) {
                $upd = $conn->prepare(
                    'UPDATE user_tasks SET status = "unbearbeitet", attempts = 0, current_code = NULL,
                     hints_revealed = "[]", completed_at = NULL, started_at = NULL' . ($hasSubmissionComment ? ', submission_comment = NULL' : '') . '
                     WHERE id = ?'
                );
                $upd->bind_param('i', $utId);
            } else {
                // MC / text / quiz tasks
                $upd = $conn->prepare(
                    'UPDATE user_tasks SET status = "unbearbeitet", attempts = 0,
                     selected_options = NULL, text_answer = NULL,
                     hints_revealed = "[]", completed_at = NULL, started_at = NULL' . ($hasSubmissionComment ? ', submission_comment = NULL' : '') . '
                     WHERE id = ?'
                );
                $upd->bind_param('i', $utId);
            }
            if (!$upd->execute()) {
                jsonResponse(['ok' => false, 'error' => 'Failed to reset user task'], 500);
            }
        }
        // If no row exists yet the task is already in default unbearbeitet state – nothing to do
    } elseif ($utRow) {
        $utId = (int)$utRow['id'];
        if ($setAttempts !== null) {
            $commentSuffix = ($hasSubmissionComment && $statusEffective === 'unbearbeitet') ? ', submission_comment = NULL' : '';
            $upd = $conn->prepare('UPDATE user_tasks SET status = ?, attempts = ?, completed_at = ?' . $commentSuffix . ' WHERE id = ?');
            $completedAt = $isFinal ? $now : null;
            $upd->bind_param('sisi', $statusEffective, $attemptsAfter, $completedAt, $utId);
            if (!$upd->execute()) {
                jsonResponse(['ok' => false, 'error' => 'Failed to update user task'], 500);
            }
        } else {
            $commentSuffix = ($hasSubmissionComment && $statusEffective === 'unbearbeitet') ? ', submission_comment = NULL' : '';
            $upd = $conn->prepare('UPDATE user_tasks SET status = ?, completed_at = ?' . $commentSuffix . ' WHERE id = ?');
            $completedAt = $isFinal ? $now : null;
            $upd->bind_param('ssi', $statusEffective, $completedAt, $utId);
            if (!$upd->execute()) {
                jsonResponse(['ok' => false, 'error' => 'Failed to update user task'], 500);
            }
        }
    } else {
        $startedAt = $now;
        $hints = '[]';
        $insert = $conn->prepare(
            'INSERT INTO user_tasks (user_id, task_id, status, attempts, current_iteration, run_count, current_code, hints_revealed, variable_values, started_at, completed_at)
             VALUES (?, ?, ?, ?, 1, 0, NULL, ?, NULL, ?, ?)'
        );
        $completedAt = $isFinal ? $now : null;
        $insert->bind_param('isissss', $userId, $taskId, $statusEffective, $attemptsAfter, $hints, $startedAt, $completedAt);
        if (!$insert->execute()) {
            jsonResponse(['ok' => false, 'error' => 'Failed to create user task'], 500);
        }
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
        elseif ($st === 'submitted') $cntPass++;
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
        // At least one task open (unbearbeitet or in-progress) alongside done tasks
        $assignmentStatus = 'in_progress';
    }

    $uaSelect = $conn->prepare('SELECT id, status FROM user_assignments WHERE user_id = ? AND assignment_id = ? LIMIT 1');
    $uaSelect->bind_param('ii', $userId, $assignmentId);
    $uaSelect->execute();
    $uaRow = $uaSelect->get_result()->fetch_assoc();

    // Detect rework: admin is reopening a previously closed assignment
    $prevAssignmentStatus = $uaRow['status'] ?? 'assigned';
    $setRework = ($assignmentStatus === 'in_progress')
        && in_array($prevAssignmentStatus, ['submitted', 'passed', 'failed'], true);

    if ($uaRow) {
        $uaId = (int)$uaRow['id'];
        if ($assignmentStatus === 'submitted') {
            // Stamp submitted_at when closing
            $uaUpd = $conn->prepare('UPDATE user_assignments SET status = ?, submitted_at = ? WHERE id = ?');
            $uaUpd->bind_param('ssi', $assignmentStatus, $now, $uaId);
        } elseif ($setRework) {
            // Reopening closed assignment: mark as rework
            $uaUpd = $conn->prepare('UPDATE user_assignments SET status = ?, is_rework = 1 WHERE id = ?');
            $uaUpd->bind_param('si', $assignmentStatus, $uaId);
        } else {
            // assigned / in_progress without rework: preserve existing submitted_at
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

    if ($hasAdminFeedbackComment && $hasAdminFeedbackInput) {
        $feedbackUpd = $conn->prepare('UPDATE user_tasks SET admin_feedback_comment = ? WHERE user_id = ? AND task_id = ?');
        $feedbackUpd->bind_param('sii', $adminFeedbackComment, $userId, $taskId);
        if (!$feedbackUpd->execute()) {
            jsonResponse(['ok' => false, 'error' => 'Failed to update admin feedback comment'], 500);
        }
    }

    $adminFeedbackCommentCurrent = null;
    if ($hasAdminFeedbackComment) {
        $feedbackRead = $conn->prepare('SELECT admin_feedback_comment FROM user_tasks WHERE user_id = ? AND task_id = ? LIMIT 1');
        $feedbackRead->bind_param('ii', $userId, $taskId);
        $feedbackRead->execute();
        $feedbackRow = $feedbackRead->get_result()->fetch_assoc();
        $adminFeedbackCommentCurrent = $feedbackRow['admin_feedback_comment'] ?? null;
    }

    jsonResponse([
        'ok' => true,
        'assignment_id' => $assignmentId,
        'user_id' => $userId,
        'task_id' => $taskId,
        'status_requested' => $statusRequested,
        'status_effective' => $statusEffective,
        'admin_feedback_comment' => $adminFeedbackCommentCurrent,
        'attempts_after' => $attemptsAfter,
        'completed_at' => $isFinal ? $now : null,
        'assignment_status' => $assignmentStatus,
        'summary' => [
            'total' => $total,
            'unbearbeitet' => $cntUn,
            'in_progress' => $cntIn,
            'passed' => $cntPass,
            'failed' => $cntFail,
        ]
    ]);
} catch (Exception $e) {
    error_log('Admin set-task-status error: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'Failed to update task status'], 500);
}
