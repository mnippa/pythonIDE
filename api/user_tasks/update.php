<?php
/**
 * Update or create user task progress
 * Users can update their own task progress
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

$user = requireAuth();
$conn = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
    jsonResponse(['ok' => false, 'error' => 'Invalid JSON'], 400);
}

$taskId = isset($input['task_id']) ? (int)$input['task_id'] : null;
if (!$taskId) {
    jsonResponse(['ok' => false, 'error' => 'task_id required'], 400);
}

$userId = (int)$user['id'];

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

// Resolve assignment_id for this task so we can update assignment status
$assignmentId = null;
$assignmentStmt = $conn->prepare('SELECT assignment_id FROM tasks WHERE id = ?');
if ($assignmentStmt) {
    $assignmentStmt->bind_param('i', $taskId);
    $assignmentStmt->execute();
    $assignmentRow = $assignmentStmt->get_result()->fetch_assoc();
    if ($assignmentRow) {
        $assignmentId = (int)$assignmentRow['assignment_id'];
    }
}

$columnExists = function (mysqli $conn, string $table, string $column): bool {
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $check = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $check && $check->num_rows > 0;
};

$hasSubmissionComment = $columnExists($conn, 'user_tasks', 'submission_comment');

$syncAssignmentStatus = function () use ($conn, $userId, $assignmentId) {
    if (!$assignmentId) {
        return;
    }

    $statusStmt = $conn->prepare(
        'SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN COALESCE(ut.status, "unbearbeitet") = "unbearbeitet" THEN 1 ELSE 0 END) AS unstarted_cnt,
            SUM(CASE WHEN COALESCE(ut.status, "unbearbeitet") = "in-progress" THEN 1 ELSE 0 END) AS in_progress_cnt,
            SUM(CASE WHEN COALESCE(ut.status, "unbearbeitet") IN ("submitted", "passed", "failed") THEN 1 ELSE 0 END) AS done_cnt
         FROM tasks t
         LEFT JOIN user_tasks ut ON ut.task_id = t.id AND ut.user_id = ?
         WHERE t.assignment_id = ?'
    );
    if (!$statusStmt) {
        return;
    }

    $statusStmt->bind_param('ii', $userId, $assignmentId);
    $statusStmt->execute();
    $row = $statusStmt->get_result()->fetch_assoc();

    $total = (int)($row['total'] ?? 0);
    $unstartedCount = (int)($row['unstarted_cnt'] ?? 0);
    $inProgressCount = (int)($row['in_progress_cnt'] ?? 0);
    $doneCount = (int)($row['done_cnt'] ?? 0);

    if ($total === 0 || $unstartedCount === $total) {
        $assignmentStatus = 'assigned';
    } elseif ($doneCount === $total) {
        $assignmentStatus = 'submitted';
    } else {
        $assignmentStatus = 'in_progress';
    }

    $uaStmt = $conn->prepare('SELECT id, submitted_at FROM user_assignments WHERE user_id = ? AND assignment_id = ? LIMIT 1');
    if (!$uaStmt) {
        return;
    }

    $uaStmt->bind_param('ii', $userId, $assignmentId);
    $uaStmt->execute();
    $uaRow = $uaStmt->get_result()->fetch_assoc();

    if ($uaRow) {
        $uaId = (int)$uaRow['id'];
        if ($assignmentStatus === 'submitted') {
            $submittedAt = $uaRow['submitted_at'] ?: date('Y-m-d H:i:s');
            $uaUpd = $conn->prepare('UPDATE user_assignments SET status = ?, submitted_at = ? WHERE id = ?');
            if ($uaUpd) {
                $uaUpd->bind_param('ssi', $assignmentStatus, $submittedAt, $uaId);
                $uaUpd->execute();
            }
        } else {
            $uaUpd = $conn->prepare('UPDATE user_assignments SET status = ? WHERE id = ?');
            if ($uaUpd) {
                $uaUpd->bind_param('si', $assignmentStatus, $uaId);
                $uaUpd->execute();
            }
        }
        return;
    }

    $submittedAt = $assignmentStatus === 'submitted' ? date('Y-m-d H:i:s') : null;
    $assignedBy = $userId;
    $uaIns = $conn->prepare('INSERT INTO user_assignments (user_id, assignment_id, assigned_by, status, submitted_at) VALUES (?, ?, ?, ?, ?)');
    if ($uaIns) {
        $uaIns->bind_param('iiiss', $userId, $assignmentId, $assignedBy, $assignmentStatus, $submittedAt);
        $uaIns->execute();
    }
};

// Mark assignment as in_progress when the first task is edited
$markAssignmentInProgress = function () use ($conn, $userId, $assignmentId) {
    if (!$assignmentId) {
        return;
    }

    $uaStmt = $conn->prepare('SELECT id, status FROM user_assignments WHERE user_id = ? AND assignment_id = ?');
    if (!$uaStmt) {
        return;
    }

    $uaStmt->bind_param('ii', $userId, $assignmentId);
    $uaStmt->execute();
    $uaRow = $uaStmt->get_result()->fetch_assoc();

    if ($uaRow) {
        $currentStatus = $uaRow['status'] ?? 'assigned';
        if ($currentStatus === 'assigned') {
            $updateStmt = $conn->prepare('UPDATE user_assignments SET status = ? WHERE id = ?');
            if ($updateStmt) {
                $newStatus = 'in_progress';
                $uaId = (int)$uaRow['id'];
                $updateStmt->bind_param('si', $newStatus, $uaId);
                $updateStmt->execute();
            }
        }
        return;
    }

    $insertStmt = $conn->prepare('INSERT INTO user_assignments (user_id, assignment_id, status) VALUES (?, ?, ?)');
    if ($insertStmt) {
        $newStatus = 'in_progress';
        $insertStmt->bind_param('iis', $userId, $assignmentId, $newStatus);
        $insertStmt->execute();
    }
};

// Check if user_task entry exists
$stmt = $conn->prepare('SELECT id FROM user_tasks WHERE user_id = ? AND task_id = ?');
$stmt->bind_param('ii', $userId, $taskId);
$stmt->execute();
$result = $stmt->get_result();
$existing = $result->fetch_assoc();

$updates = [];
$params = [];
$types = '';

// Status
if (isset($input['status'])) {
    $allowedStatus = ['unbearbeitet', 'in-progress', 'submitted', 'passed', 'failed'];
    if (!in_array($input['status'], $allowedStatus, true)) {
        jsonResponse(['ok' => false, 'error' => 'Invalid status'], 400);
    }
    $updates[] = 'status = ?';
    $params[] = $input['status'];
    $types .= 's';
    
    // Set completed_at if status is passed or failed
    if (in_array($input['status'], ['submitted', 'passed', 'failed'])) {
        $updates[] = 'completed_at = ?';
        // Use client-provided completed_at if available, otherwise use server time
        $completedAt = isset($input['completed_at']) ? $input['completed_at'] : date('Y-m-d H:i:s');
        $params[] = $completedAt;
        $types .= 's';
    }
}

// Attempts
if (isset($input['attempts'])) {
    $attempts = (int)$input['attempts'];
    if ($attempts < 0) {
        jsonResponse(['ok' => false, 'error' => 'Invalid attempts'], 400);
    }
    $updates[] = 'attempts = ?';
    $params[] = $attempts;
    $types .= 'i';
}

// Current iteration (for iterative quiz tasks)
if (isset($input['current_iteration'])) {
    $currentIteration = (int)$input['current_iteration'];
    if ($currentIteration < 1) {
        jsonResponse(['ok' => false, 'error' => 'Invalid current_iteration'], 400);
    }
    $updates[] = 'current_iteration = ?';
    $params[] = $currentIteration;
    $types .= 'i';
}

// Runs
if (isset($input['run_count'])) {
    $runCount = (int)$input['run_count'];
    if ($runCount < 0) {
        jsonResponse(['ok' => false, 'error' => 'Invalid run_count'], 400);
    }
    $updates[] = 'run_count = ?';
    $params[] = $runCount;
    $types .= 'i';
}

// Current code
if (array_key_exists('current_code', $input)) {
    $updates[] = 'current_code = ?';
    $params[] = $input['current_code'];
    $types .= 's';
}

// Optional submission comment
if ($hasSubmissionComment && array_key_exists('submission_comment', $input)) {
    $updates[] = 'submission_comment = ?';
    $comment = trim((string)$input['submission_comment']);
    $params[] = $comment !== '' ? $comment : null;
    $types .= 's';
}

// Hints revealed
if (isset($input['hints_revealed']) && is_array($input['hints_revealed'])) {
    $updates[] = 'hints_revealed = ?';
    $params[] = json_encode($input['hints_revealed']);
    $types .= 's';
}

// Variable values (for generated tasks)
if (array_key_exists('variable_values', $input)) {
    $updates[] = 'variable_values = ?';
    $variableValuesJson = $input['variable_values'] ? json_encode($input['variable_values']) : null;
    $params[] = $variableValuesJson;
    $types .= 's';
}

// Start time - only set if not already set
if (!$existing && isset($input['started_at'])) {
    $updates[] = 'started_at = ?';
    $params[] = $input['started_at'];
    $types .= 's';
}

if ($existing) {
    // Update existing record
    if (empty($updates)) {
        jsonResponse(['ok' => true, 'message' => 'No changes'], 200);
    }
    
    $params[] = $existing['id'];
    $types .= 'i';
    
    $sql = 'UPDATE user_tasks SET ' . implode(', ', $updates) . ' WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        $markAssignmentInProgress();
        $syncAssignmentStatus();
        jsonResponse(['ok' => true, 'message' => 'User task updated', 'id' => $existing['id']]);
    } else {
        jsonResponse(['ok' => false, 'error' => 'Failed to update user task'], 500);
    }
} else {
    // Create new record
    $status = isset($input['status'])
        ? $input['status']
        : (array_key_exists('variable_values', $input) ? 'unbearbeitet' : 'in-progress');
    $attempts = isset($input['attempts']) ? (int)$input['attempts'] : 0;
    $runCount = isset($input['run_count']) ? (int)$input['run_count'] : 0;
    $currentCode = isset($input['current_code']) ? $input['current_code'] : null;
    $submissionComment = $hasSubmissionComment && array_key_exists('submission_comment', $input)
        ? (trim((string)$input['submission_comment']) !== '' ? trim((string)$input['submission_comment']) : null)
        : null;
    $hintsRevealed = isset($input['hints_revealed']) ? json_encode($input['hints_revealed']) : '[]';
    $variableValues = array_key_exists('variable_values', $input) ? (json_encode($input['variable_values']) ?: null) : null;
    $startedAt = isset($input['started_at']) ? $input['started_at'] : date('Y-m-d H:i:s');
    $currentIteration = isset($input['current_iteration']) ? (int)$input['current_iteration'] : 1;

    $insertColumns = 'user_id, task_id, status, attempts, current_iteration, run_count, current_code, hints_revealed, variable_values, started_at';
    $insertPlaceholders = '?, ?, ?, ?, ?, ?, ?, ?, ?, ?';
    $bindValues = [$userId, $taskId, $status, $attempts, $currentIteration, $runCount, $currentCode, $hintsRevealed, $variableValues, $startedAt];
    $bindTypes = 'iisiiissss';
    if ($hasSubmissionComment) {
        $insertColumns .= ', submission_comment';
        $insertPlaceholders .= ', ?';
        $bindValues[] = $submissionComment;
        $bindTypes .= 's';
    }

    $stmt = $conn->prepare(
        "INSERT INTO user_tasks ({$insertColumns}) VALUES ({$insertPlaceholders})"
    );
    $stmt->bind_param($bindTypes, ...$bindValues);
    
    if ($stmt->execute()) {
        $markAssignmentInProgress();
        $syncAssignmentStatus();
        jsonResponse(['ok' => true, 'message' => 'User task created', 'id' => $conn->insert_id]);
    } else {
        jsonResponse(['ok' => false, 'error' => 'Failed to create user task'], 500);
    }
}
