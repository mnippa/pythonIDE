<?php
/**
 * Admin: Update assignment status for a user
 * POST { assignment_id, user_id, status }
 */

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../auth/middleware.php';

header('Content-Type: application/json');

function buildReworkDueDate(int $daysFromNow = 10): string {
    $date = new DateTimeImmutable('now');
    return $date->modify('+' . $daysFromNow . ' days')->format('Y-m-d H:i:s');
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

    $assignmentId = isset($input['assignment_id']) ? (int)$input['assignment_id'] : null;
    $userId = isset($input['user_id']) ? (int)$input['user_id'] : null;
    $status = $input['status'] ?? null;

    if (!$assignmentId || !$userId) {
        jsonResponse(['ok' => false, 'error' => 'assignment_id and user_id required'], 400);
    }

    requireAdminOwnedAssignment($conn, $assignmentId, $admin);

    if ($status === 'rework') {
        $conn->begin_transaction();

        $stmt = $conn->prepare('SELECT id FROM user_assignments WHERE user_id = ? AND assignment_id = ?');
        $stmt->bind_param('ii', $userId, $assignmentId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        $reworkDueDate = buildReworkDueDate(10);

        if ($existing) {
            $update = $conn->prepare(
                'UPDATE user_assignments
                 SET status = ?, due_date = ?, submitted_at = NULL, is_late = 0
                 WHERE id = ?'
            );
            $mappedStatus = 'rework';
            $uaId = (int)$existing['id'];
            $update->bind_param('ssi', $mappedStatus, $reworkDueDate, $uaId);
            if (!$update->execute()) {
                throw new RuntimeException('Failed to update rework assignment status');
            }
        } else {
            $insert = $conn->prepare(
                'INSERT INTO user_assignments (user_id, assignment_id, status, assigned_by, due_date, submitted_at, is_late)
                 VALUES (?, ?, ?, ?, ?, NULL, 0)'
            );
            $mappedStatus = 'rework';
            $adminId = (int)$admin['id'];
            $insert->bind_param('iisis', $userId, $assignmentId, $mappedStatus, $adminId, $reworkDueDate);
            if (!$insert->execute()) {
                throw new RuntimeException('Failed to create rework assignment status');
            }
        }

        $resetFailedTasks = $conn->prepare(
            'UPDATE user_tasks ut
             INNER JOIN tasks t ON t.id = ut.task_id
             SET ut.status = "unbearbeitet",
                 ut.attempts = 0,
                 ut.current_iteration = 1,
                 ut.selected_options = NULL,
                 ut.text_answer = NULL,
                 ut.variable_values = NULL,
                 ut.completed_at = NULL
             WHERE ut.user_id = ?
               AND t.assignment_id = ?
               AND ut.status = "failed"'
        );
        $resetFailedTasks->bind_param('ii', $userId, $assignmentId);
        if (!$resetFailedTasks->execute()) {
            throw new RuntimeException('Failed to reset failed tasks for rework');
        }

        $conn->commit();
        jsonResponse([
            'ok' => true,
            'updated' => true,
            'status' => 'rework',
            'due_date' => $reworkDueDate,
            'reset_failed_tasks' => $resetFailedTasks->affected_rows,
        ]);
    }

    // passed_delayed is not a DB value; it maps to passed + is_late=1
    $isLate = null; // null = do not touch is_late
    if ($status === 'passed_delayed') {
        $status = 'passed';
        $isLate = 1;
    } elseif ($status === 'passed') {
        $isLate = 0; // explicitly clear is_late when setting plain passed
    }

    // General alias map for other lifecycle labels that may arrive
    $statusAliasMap = [
        'completed'    => 'submitted',
        'late_completed' => 'submitted',
        'missed'       => 'failed',
    ];
    if (isset($statusAliasMap[$status])) {
        $status = $statusAliasMap[$status];
    }

    $allowedStatus = ['assigned', 'in_progress', 'rework', 'submitted', 'passed', 'failed'];

    if (!in_array($status, $allowedStatus, true)) {
        jsonResponse(['ok' => false, 'error' => 'Invalid status'], 400);
    }

    $stmt = $conn->prepare('SELECT id FROM user_assignments WHERE user_id = ? AND assignment_id = ?');
    $stmt->bind_param('ii', $userId, $assignmentId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();

    if ($existing) {
        $now = date('Y-m-d H:i:s');
        $uaId = (int)$existing['id'];

        // Read current submitted_at to preserve it if already set
        $cur = $conn->prepare('SELECT submitted_at FROM user_assignments WHERE id = ?');
        $cur->bind_param('i', $uaId);
        $cur->execute();
        $curRow = $cur->get_result()->fetch_assoc();
        $existingSubmittedAt = $curRow['submitted_at'] ?? null;

        $setParts = ['status = ?'];
        $bindTypes = 's';
        $bindValues = [$status];

        if ($isLate !== null) {
            $setParts[] = 'is_late = ?';
            $bindTypes .= 'i';
            $bindValues[] = $isLate;
        }

        // Set submitted_at only if not already set and status implies submission
        if (empty($existingSubmittedAt) && in_array($status, ['submitted', 'passed', 'failed'], true)) {
            $setParts[] = 'submitted_at = ?';
            $bindTypes .= 's';
            $bindValues[] = $now;
        }

        // Always set graded_at + graded_by when admin marks passed or failed
        if (in_array($status, ['passed', 'failed'], true)) {
            $setParts[] = 'graded_at = ?';
            $bindTypes .= 's';
            $bindValues[] = $now;
            $setParts[] = 'graded_by = ?';
            $bindTypes .= 'i';
            $bindValues[] = (int)$admin['id'];
        }

        $setParts[] = 'id = ?';  // append WHERE value last
        $bindTypes .= 'i';
        $bindValues[] = $uaId;

        $sql = 'UPDATE user_assignments SET ' . implode(', ', array_slice($setParts, 0, -1)) . ' WHERE id = ?';
        $update = $conn->prepare($sql);
        $update->bind_param($bindTypes, ...$bindValues);
        if ($update->execute()) {
            jsonResponse(['ok' => true, 'updated' => true]);
        }
        jsonResponse(['ok' => false, 'error' => 'Failed to update status'], 500);
    }

    $adminId = (int)$admin['id'];
    $isGradedInsert = in_array($status, ['passed', 'failed'], true);
    $nowInsert = date('Y-m-d H:i:s');
    if ($isGradedInsert) {
        $insert = $conn->prepare('INSERT INTO user_assignments (user_id, assignment_id, status, assigned_by, submitted_at, graded_at, graded_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $insert->bind_param('iisissi', $userId, $assignmentId, $status, $adminId, $nowInsert, $nowInsert, $adminId);
    } else {
        $insert = $conn->prepare('INSERT INTO user_assignments (user_id, assignment_id, status, assigned_by) VALUES (?, ?, ?, ?)');
        $insert->bind_param('iisi', $userId, $assignmentId, $status, $adminId);
    }

    if ($insert->execute()) {
        jsonResponse(['ok' => true, 'created' => true]);
    }

    jsonResponse(['ok' => false, 'error' => 'Failed to create assignment status'], 500);
} catch (Exception $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            // Ignore rollback follow-up errors and return the original failure.
        }
    }
    error_log('Assignment status update error: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'Failed to update assignment status'], 500);
}
