<?php
/**
 * Admin: Assignment user detail
 * GET ?assignment_id=X&user_id=Y
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../auth/middleware.php';

header('Content-Type: application/json');

try {
    $admin = requireAdmin();
    $conn = getDbConnection();

    $assignmentId = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : null;
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

    if (!$assignmentId || !$userId) {
        jsonResponse(['ok' => false, 'error' => 'assignment_id and user_id required'], 400);
    }

    $assignment = requireAdminOwnedAssignment($conn, $assignmentId, $admin);

    $columnExists = function (mysqli $conn, string $table, string $column): bool {
        $safeTable = $conn->real_escape_string($table);
        $safeColumn = $conn->real_escape_string($column);
        $check = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
        return $check && $check->num_rows > 0;
    };

    $tableExists = function (mysqli $conn, string $table): bool {
        $safeTable = $conn->real_escape_string($table);
        $check = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
        return $check && $check->num_rows > 0;
    };

    $hasUserTeamId = $columnExists($conn, 'users', 'team_id');
    $hasAssignmentTeamId = $columnExists($conn, 'user_assignments', 'team_id');
    $hasUserTasks = $tableExists($conn, 'user_tasks');
    $hasRunCount = $hasUserTasks && $columnExists($conn, 'user_tasks', 'run_count');
    $hasActiveSeconds = $hasUserTasks && $columnExists($conn, 'user_tasks', 'active_seconds');

    $sql = '
        SELECT
            u.id,
            u.email,
            u.first_name,
            u.last_name,
            u.team_id,
            t.name AS team_name,
            ua_user.status AS direct_status,
            ua_team.status AS team_status
        FROM users u
        LEFT JOIN teams t ON t.id = u.team_id
        LEFT JOIN user_assignments ua_user
            ON ua_user.assignment_id = ? AND ua_user.user_id = u.id
    ';

    if ($hasUserTeamId && $hasAssignmentTeamId) {
        $sql .= '
        LEFT JOIN user_assignments ua_team
            ON ua_team.assignment_id = ? AND ua_team.team_id = u.team_id
        ';
    } else {
        $sql .= '
        LEFT JOIN user_assignments ua_team ON 1=0
        ';
    }

    $sql .= ' WHERE u.id = ?';

    $stmt = $conn->prepare($sql);
    if ($hasUserTeamId && $hasAssignmentTeamId) {
        $stmt->bind_param('iii', $assignmentId, $assignmentId, $userId);
    } else {
        $stmt->bind_param('ii', $assignmentId, $userId);
    }
    $stmt->execute();
    $userRow = $stmt->get_result()->fetch_assoc();

    if (!$userRow) {
        jsonResponse(['ok' => false, 'error' => 'User not found'], 404);
    }

    $status = $userRow['direct_status'] ?? $userRow['team_status'] ?? 'assigned';
    $isDirect = $userRow['direct_status'] !== null;

    if ($userRow['direct_status'] === null && $userRow['team_status'] === null) {
        jsonResponse(['ok' => false, 'error' => 'User not assigned to this assignment'], 404);
    }

    $statusLabelMap = [
        'assigned' => 'unbearbeitet',
        'in_progress' => 'in Bearbeitung',
        'submitted' => 'submitted',
        'passed' => 'success',
        'failed' => 'failed'
    ];

    $user = [
        'id' => (int)$userRow['id'],
        'email' => $userRow['email'],
        'first_name' => $userRow['first_name'],
        'last_name' => $userRow['last_name'],
        'full_name' => trim(($userRow['first_name'] ?? '') . ' ' . ($userRow['last_name'] ?? '')),
        'team_id' => $userRow['team_id'] !== null ? (int)$userRow['team_id'] : null,
        'team_name' => $userRow['team_name'],
        'status' => $status,
        'status_label' => $statusLabelMap[$status] ?? $status,
        'source' => $isDirect ? 'direct (User)' : 'team'
    ];

    $taskStatusLabelMap = [
        'unbearbeitet' => 'unbearbeitet',
        'in-progress' => 'in Bearbeitung',
        'passed' => 'success',
        'failed' => 'failed'
    ];

    $tasks = [];
    if ($hasUserTasks) {
        $runSelect = $hasRunCount ? ', ut.run_count' : '';
        $activeSelect = $hasActiveSeconds ? ', ut.active_seconds' : '';
        $taskSql = '
            SELECT t.id, t.title, t.position,
                   ut.status, ut.attempts' . $runSelect . $activeSelect . '
            FROM tasks t
            LEFT JOIN user_tasks ut ON ut.task_id = t.id AND ut.user_id = ?
            WHERE t.assignment_id = ?
            ORDER BY t.position ASC
        ';
        $taskStmt = $conn->prepare($taskSql);
        $taskStmt->bind_param('ii', $userId, $assignmentId);
    } else {
        $taskSql = '
            SELECT t.id, t.title, t.position
            FROM tasks t
            WHERE t.assignment_id = ?
            ORDER BY t.position ASC
        ';
        $taskStmt = $conn->prepare($taskSql);
        $taskStmt->bind_param('i', $assignmentId);
    }
    $taskStmt->execute();
    $taskResult = $taskStmt->get_result();

    while ($row = $taskResult->fetch_assoc()) {
        $taskStatus = $hasUserTasks ? ($row['status'] ?? 'unbearbeitet') : 'unbearbeitet';
        $taskStatusLabel = $taskStatusLabelMap[$taskStatus] ?? $taskStatus;
        $runCount = $hasRunCount && isset($row['run_count']) ? (int)$row['run_count'] : 0;
        $activeSeconds = $hasActiveSeconds && isset($row['active_seconds']) ? (int)$row['active_seconds'] : 0;
        $tasks[] = [
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'position' => (int)$row['position'],
            'status' => $taskStatus,
            'status_label' => $taskStatusLabel,
            'attempts' => $hasUserTasks && $row['attempts'] !== null ? (int)$row['attempts'] : 0,
            'run_count' => $runCount,
            'active_seconds' => $activeSeconds
        ];
    }

    jsonResponse([
        'ok' => true,
        'assignment_id' => $assignmentId,
        'assignment_title' => $assignment['title'],
        'user' => $user,
        'tasks' => $tasks
    ]);
} catch (Exception $e) {
    error_log('Evaluation user detail error: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'Failed to load user detail'], 500);
}
