<?php
/**
 * Admin: List users assigned to a specific assignment
 * GET ?assignment_id=X
 */

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../auth/middleware.php';

header('Content-Type: application/json');

try {
    $admin = requireAdmin();
    $conn = getDbConnection();

    $assignmentId = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : null;
    if (!$assignmentId) {
        jsonResponse(['ok' => false, 'error' => 'assignment_id required'], 400);
    }

    requireAdminOwnedAssignment($conn, $assignmentId, $admin);

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

    $hasUserTasks = $tableExists($conn, 'user_tasks');
    $hasRunCount = $hasUserTasks && $columnExists($conn, 'user_tasks', 'run_count');
    $hasActiveSeconds = $hasUserTasks && $columnExists($conn, 'user_tasks', 'active_seconds');

    $runSelect = '0 AS run_count';
    if ($hasRunCount) {
        $runSelect = '(
            SELECT COALESCE(SUM(ut.run_count), 0)
            FROM user_tasks ut
            INNER JOIN tasks t ON t.id = ut.task_id
            WHERE ut.user_id = u.id AND t.assignment_id = ?
        ) AS run_count';
    }

    $activeSelect = '0 AS active_seconds';
    if ($hasActiveSeconds) {
        $activeSelect = '(
            SELECT COALESCE(SUM(ut.active_seconds), 0)
            FROM user_tasks ut
            INNER JOIN tasks t ON t.id = ut.task_id
            WHERE ut.user_id = u.id AND t.assignment_id = ?
        ) AS active_seconds';
    }

    $sql = '
        SELECT
            u.id,
            u.email,
            u.first_name,
            u.last_name,
            u.team_id,
            t.name AS team_name,
            COALESCE(ua_user.status, ua_team.status, "assigned") AS status,
            CASE WHEN ua_user.id IS NOT NULL THEN 1 ELSE 0 END AS is_direct,
            ' . $runSelect . ',
            ' . $activeSelect . '
        FROM users u
        LEFT JOIN teams t ON t.id = u.team_id
        LEFT JOIN user_assignments ua_user
            ON ua_user.assignment_id = ? AND ua_user.user_id = u.id
        LEFT JOIN user_assignments ua_team
            ON ua_team.assignment_id = ? AND ua_team.team_id = u.team_id
        WHERE ua_user.id IS NOT NULL OR ua_team.id IS NOT NULL
        ORDER BY u.last_name, u.first_name, u.email
    ';

    $stmt = $conn->prepare($sql);
    if ($hasRunCount && $hasActiveSeconds) {
        // 4 params: 2 JOINs + runSelect subquery + activeSelect subquery
        $stmt->bind_param('iiii', $assignmentId, $assignmentId, $assignmentId, $assignmentId);
    } elseif ($hasRunCount || $hasActiveSeconds) {
        // 3 params: 2 JOINs + one subquery
        $stmt->bind_param('iii', $assignmentId, $assignmentId, $assignmentId);
    } else {
        // 2 params: just the 2 JOINs
        $stmt->bind_param('ii', $assignmentId, $assignmentId);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => (int)$row['id'],
            'email' => $row['email'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'team_id' => $row['team_id'] !== null ? (int)$row['team_id'] : null,
            'team_name' => $row['team_name'],
            'status' => $row['status'],
            'is_direct' => (bool)$row['is_direct'],
            'run_count' => (int)$row['run_count'],
            'active_seconds' => (int)$row['active_seconds']
        ];
    }

    jsonResponse([
        'ok' => true,
        'assignment_id' => $assignmentId,
        'users' => $users,
        'count' => count($users)
    ]);
} catch (Exception $e) {
    error_log('Assignment users list error: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'Failed to load assignment users'], 500);
}
