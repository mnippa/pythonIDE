<?php
/**
 * Admin: List users assigned to a specific assignment
 * GET ?assignment_id=X
 */

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../auth/middleware.php';

header('Content-Type: application/json');

function formatRemainingTime(?DateTimeImmutable $deadline): ?string {
    if ($deadline === null) {
        return null;
    }

    $now = new DateTimeImmutable('now');
    if ($now >= $deadline) {
        return null;
    }

    $diff = $now->diff($deadline);
    $days = (int)$diff->format('%a');
    $hours = (int)$diff->format('%h');
    $minutes = (int)$diff->format('%i');

    if ($days >= 1) {
        $dayLabel = $days === 1 ? 'Tag' : 'Tage';
        $hourLabel = $hours === 1 ? 'Stunde' : 'Stunden';
        return "{$days} {$dayLabel}, {$hours} {$hourLabel}";
    }

    $hoursFormatted = str_pad((string)$hours, 2, '0', STR_PAD_LEFT);
    $minutesFormatted = str_pad((string)$minutes, 2, '0', STR_PAD_LEFT);
    return "{$hoursFormatted}:{$minutesFormatted}";
}

function parseApiDateTime(?string $value, bool $dateOnlyAsEndOfDay = false): ?DateTimeImmutable {
    if ($value === null || trim($value) === '') {
        return null;
    }

    $raw = trim($value);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        $raw .= $dateOnlyAsEndOfDay ? ' 23:59:59' : ' 00:00:00';
    }

    try {
        return new DateTimeImmutable($raw);
    } catch (Exception $e) {
        return null;
    }
}

function getEffectiveHardDeadline(?DateTimeImmutable $hardDeadline, ?DateTimeImmutable $dueDate): ?DateTimeImmutable {
    if ($dueDate !== null && ($hardDeadline === null || $hardDeadline < $dueDate)) {
        return $dueDate;
    }

    return $hardDeadline;
}

function isReworkState(array $row, string $rawStatus, bool $allPassed, bool $allWorked): bool {
    return $rawStatus === 'rework';
}

function calcAssignmentTiming(array $row): array {
    $now = new DateTimeImmutable('now');
    $availableFrom = parseApiDateTime($row['available_from'] ?? null, false);
    $dueDate = parseApiDateTime($row['effective_due_date'] ?? null, true);
    $hardDeadline = getEffectiveHardDeadline(
        parseApiDateTime($row['hard_deadline'] ?? null, true),
        $dueDate
    );

    $phase = 'open';
    if (!empty($row['assignment_active']) && (int)$row['assignment_active'] === 0) {
        $phase = 'hidden';
    } elseif ($availableFrom !== null && $now < $availableFrom) {
        $phase = 'upcoming';
    } elseif ($hardDeadline !== null && $now > $hardDeadline) {
        $phase = 'closed';
    } elseif ($dueDate !== null && $now > $dueDate) {
        $phase = 'late';
    }

    $daysRemaining = null;
    if ($hardDeadline !== null && $phase !== 'closed') {
        $daysRemaining = (int)$now->diff($hardDeadline)->format('%r%a');
    } elseif ($dueDate !== null && !in_array($phase, ['late', 'closed'], true)) {
        $daysRemaining = (int)$now->diff($dueDate)->format('%r%a');
    }

    return [
        'phase' => $phase,
        'days_remaining' => $daysRemaining,
        'formatted_time_remaining' => formatRemainingTime($dueDate),
    ];
}

function deriveAssignmentDisplayStatus(array $row, array $timing, array $taskStats): array {
    $rawStatus = (string)($row['raw_status'] ?? 'assigned');
    $statusMap = [
        'assigned' => 'Zugewiesen',
        'in_progress' => 'In Bearbeitung',
        'submitted' => 'Eingereicht',
        'passed' => 'Bestanden',
        'failed' => 'Nicht bestanden',
        'rework' => 'Nacharbeit',
    ];

    $isLate = !empty($row['is_late']);
    if (!$isLate && !empty($row['submitted_at']) && !empty($row['effective_due_date'])) {
        $submittedAt = parseApiDateTime((string)$row['submitted_at'], false);
        $dueDate = parseApiDateTime((string)$row['effective_due_date'], true);
        $isLate = $submittedAt !== null && $dueDate !== null && $submittedAt > $dueDate;
    }

    return [
        'status' => in_array($rawStatus, array_keys($statusMap), true) ? $rawStatus : 'assigned',
        'label' => $statusMap[$rawStatus] ?? 'Zugewiesen',
        'is_late_completion' => false,
        'is_rework' => $rawStatus === 'rework',
    ];
}

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
    $hasUserTaskStatus = $hasUserTasks && $columnExists($conn, 'user_tasks', 'status');
    $hasRunCount = $hasUserTasks && $columnExists($conn, 'user_tasks', 'run_count');
    $hasActiveSeconds = $hasUserTasks && $columnExists($conn, 'user_tasks', 'active_seconds');

    $hasUaTeamId = $columnExists($conn, 'user_assignments', 'team_id');
    $hasUaStatus = $columnExists($conn, 'user_assignments', 'status');
    $hasUaSubmittedAt = $columnExists($conn, 'user_assignments', 'submitted_at');
    $hasUaGradedAt = $columnExists($conn, 'user_assignments', 'graded_at');
    $hasUaGradedBy = $columnExists($conn, 'user_assignments', 'graded_by');
    $hasUaIsLate = $columnExists($conn, 'user_assignments', 'is_late');
    $hasUaDueDate = $columnExists($conn, 'user_assignments', 'due_date');

    $hasAssignmentDueDate = $columnExists($conn, 'assignments', 'due_date');
    $hasAssignmentIsActive = $columnExists($conn, 'assignments', 'is_active');
    $hasAssignmentAvailableFrom = $columnExists($conn, 'assignments', 'available_from');
    $hasAssignmentHardDeadline = $columnExists($conn, 'assignments', 'hard_deadline');

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

    // ua_team join is only possible when user_assignments has a team_id column
    $uaTeamJoin = $hasUaTeamId
        ? 'LEFT JOIN user_assignments ua_team ON ua_team.assignment_id = ? AND ua_team.team_id = u.team_id'
        : '';
    $assignmentDueDateExpr = $hasAssignmentDueDate ? 'a.due_date' : 'NULL';
    $assignmentIsActiveExpr = $hasAssignmentIsActive ? 'a.is_active' : '1';
    $assignmentAvailableFromExpr = $hasAssignmentAvailableFrom ? 'a.available_from' : 'NULL';
    $assignmentHardDeadlineExpr = $hasAssignmentHardDeadline ? 'a.hard_deadline' : 'NULL';

    $uaTeamStatus = $hasUaStatus
        ? ($hasUaTeamId
            ? 'COALESCE(ua_user.status, ua_team.status, "assigned")'
            : 'COALESCE(ua_user.status, "assigned")')
        : '"assigned"';

    $uaTeamSubmittedAt = $hasUaSubmittedAt
        ? ($hasUaTeamId ? 'COALESCE(ua_user.submitted_at, ua_team.submitted_at)' : 'ua_user.submitted_at')
        : 'NULL';

    $uaTeamGradedAt = $hasUaGradedAt
        ? ($hasUaTeamId ? 'COALESCE(ua_user.graded_at, ua_team.graded_at)' : 'ua_user.graded_at')
        : 'NULL';

    $uaTeamGradedBy = $hasUaGradedBy
        ? ($hasUaTeamId ? 'COALESCE(ua_user.graded_by, ua_team.graded_by)' : 'ua_user.graded_by')
        : 'NULL';

    $uaTeamIsLate = $hasUaIsLate
        ? ($hasUaTeamId ? 'COALESCE(ua_user.is_late, ua_team.is_late, 0)' : 'COALESCE(ua_user.is_late, 0)')
        : '0';

    $uaTeamDueDate = $hasUaDueDate
        ? ($hasUaTeamId ? 'COALESCE(ua_user.due_date, ua_team.due_date)' : 'ua_user.due_date')
        : 'NULL';

    if ($hasUaDueDate) {
        $uaTeamEffDueDate = $hasUaTeamId
            ? 'COALESCE(ua_user.due_date, ua_team.due_date, ' . $assignmentDueDateExpr . ')'
            : 'COALESCE(ua_user.due_date, ' . $assignmentDueDateExpr . ')';
    } else {
        $uaTeamEffDueDate = $assignmentDueDateExpr;
    }
    $uaTeamWhere = $hasUaTeamId
        ? 'ua_user.id IS NOT NULL OR ua_team.id IS NOT NULL'
        : 'ua_user.id IS NOT NULL';
    if ($hasUaGradedBy) {
        $uaTeamGraderJoin = $hasUaTeamId
            ? 'LEFT JOIN users grader ON grader.id = COALESCE(ua_user.graded_by, ua_team.graded_by)'
            : 'LEFT JOIN users grader ON grader.id = ua_user.graded_by';
    } else {
        $uaTeamGraderJoin = 'LEFT JOIN users grader ON 1 = 0';
    }

    $sql = '
        SELECT
            u.id,
            u.email,
            u.first_name,
            u.last_name,
            u.team_id,
            t.name AS team_name,
            ' . $uaTeamStatus . ' AS raw_status,
            ' . $uaTeamSubmittedAt . ' AS submitted_at,
            ' . $uaTeamGradedAt . ' AS graded_at,
            ' . $uaTeamGradedBy . ' AS graded_by_id,
            ' . $uaTeamIsLate . ' AS is_late,
            ' . $uaTeamDueDate . ' AS user_due_date,
            ' . $assignmentDueDateExpr . ' AS assignment_due_date,
            ' . $uaTeamEffDueDate . ' AS effective_due_date,
            ' . $assignmentIsActiveExpr . ' AS assignment_active,
            ' . $assignmentAvailableFromExpr . ' AS available_from,
            ' . $assignmentHardDeadlineExpr . ' AS hard_deadline,
            CASE WHEN ua_user.id IS NOT NULL THEN 1 ELSE 0 END AS is_direct,
            ' . $runSelect . ',
            ' . $activeSelect . ',
            grader.last_name AS graded_by_last_name
        FROM users u
        INNER JOIN assignments a ON a.id = ?
        LEFT JOIN teams t ON t.id = u.team_id
        LEFT JOIN user_assignments ua_user
            ON ua_user.assignment_id = ? AND ua_user.user_id = u.id
        ' . $uaTeamJoin . '
        ' . $uaTeamGraderJoin . '
        WHERE ' . $uaTeamWhere . '
        ORDER BY u.last_name, u.first_name, u.email
    ';

    // Count bind params: base=2 (assignment JOIN + ua_user JOIN) + optional ua_team JOIN + optional subqueries
    $baseParams = 2;
    $joinParams  = $hasUaTeamId ? 1 : 0;
    $subParams   = ($hasRunCount ? 1 : 0) + ($hasActiveSeconds ? 1 : 0);
    $totalParams = $baseParams + $joinParams + $subParams;
    $paramTypes  = str_repeat('i', $totalParams);
    $paramValues = array_fill(0, $totalParams, $assignmentId);

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Failed to prepare assignment users query: ' . $conn->error);
    }
    $stmt->bind_param($paramTypes, ...$paramValues);
    if (!$stmt->execute()) {
        throw new RuntimeException('Failed to execute assignment users query: ' . $stmt->error);
    }
    $result = $stmt->get_result();
    if ($result === false) {
        throw new RuntimeException('Failed to fetch assignment users result: ' . $stmt->error);
    }

    $users = [];
    $userRows = [];
    while ($row = $result->fetch_assoc()) {
        $userRows[] = $row;
    }

    $taskStatsByUser = [];
    if (!empty($userRows) && $hasUserTaskStatus) {
        $userIds = array_values(array_map(static function (array $row): int {
            return (int)$row['id'];
        }, $userRows));
        $userPlaceholders = implode(',', array_fill(0, count($userIds), '?'));
        $taskStatsSql = "
            SELECT
                ux.id AS user_id,
                COUNT(t.id) AS total_tasks,
                SUM(CASE WHEN ut.status IN ('in-progress', 'passed', 'failed') THEN 1 ELSE 0 END) AS worked_tasks,
                SUM(CASE WHEN ut.status = 'passed' THEN 1 ELSE 0 END) AS passed_tasks,
                SUM(CASE WHEN ut.status IN ('passed', 'failed') THEN 1 ELSE 0 END) AS finalized_tasks
            FROM users ux
            INNER JOIN tasks t ON t.assignment_id = ?
            LEFT JOIN user_tasks ut ON ut.task_id = t.id AND ut.user_id = ux.id
            WHERE ux.id IN ({$userPlaceholders})
            GROUP BY ux.id
        ";

        $taskStatsStmt = $conn->prepare($taskStatsSql);
        if (!$taskStatsStmt) {
            throw new RuntimeException('Failed to prepare task stats query: ' . $conn->error);
        }
        $taskStatsTypes = 'i' . str_repeat('i', count($userIds));
        $taskStatsParams = array_merge([$assignmentId], $userIds);
        $taskStatsStmt->bind_param($taskStatsTypes, ...$taskStatsParams);
        if (!$taskStatsStmt->execute()) {
            throw new RuntimeException('Failed to execute task stats query: ' . $taskStatsStmt->error);
        }

        $taskStatsResult = $taskStatsStmt->get_result();
        if ($taskStatsResult === false) {
            throw new RuntimeException('Failed to fetch task stats result: ' . $taskStatsStmt->error);
        }
        while ($statsRow = $taskStatsResult->fetch_assoc()) {
            $statsUserId = (int)$statsRow['user_id'];
            if ($statsUserId <= 0) {
                continue;
            }
            $taskStatsByUser[$statsUserId] = [
                'total_tasks' => (int)($statsRow['total_tasks'] ?? 0),
                'worked_tasks' => (int)($statsRow['worked_tasks'] ?? 0),
                'passed_tasks' => (int)($statsRow['passed_tasks'] ?? 0),
                'finalized_tasks' => (int)($statsRow['finalized_tasks'] ?? 0),
            ];
        }
    }

    foreach ($userRows as $row) {
        $userId = (int)$row['id'];
        $taskStats = $taskStatsByUser[$userId] ?? [
            'total_tasks' => 0,
            'worked_tasks' => 0,
            'passed_tasks' => 0,
            'finalized_tasks' => 0,
        ];
        $timing = calcAssignmentTiming($row);
        $displayStatus = deriveAssignmentDisplayStatus($row, $timing, $taskStats);

        $users[] = [
            'id' => $userId,
            'email' => $row['email'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'team_id' => $row['team_id'] !== null ? (int)$row['team_id'] : null,
            'team_name' => $row['team_name'],
            'status' => $displayStatus['status'],
            'status_label' => $displayStatus['label'],
            // Expose passed_delayed as selectable raw_status so the admin dropdown matches
            'raw_status' => ((($row['raw_status'] ?? 'assigned') === 'passed' && !empty($row['is_late']))
                ? 'passed_delayed'
                : ($row['raw_status'] ?? 'assigned')),
            'is_direct' => (bool)$row['is_direct'],
            'run_count' => (int)$row['run_count'],
            'active_seconds' => (int)$row['active_seconds'],
            'submitted_at' => $row['submitted_at'] ?? null,
            'graded_at' => $row['graded_at'] ?? null,
            'graded_by_last_name' => $row['graded_by_last_name'] ?? null,
            'effective_due_date' => $row['effective_due_date'] ?? null,
            'is_late' => $displayStatus['is_late'] ?? !empty($row['is_late']),
            'is_late_completion' => $displayStatus['is_late_completion'],
            'is_rework' => $displayStatus['is_rework'],
            'timing_phase' => $timing['phase'],
            'task_progress' => $taskStats,
        ];
    }

    jsonResponse([
        'ok' => true,
        'assignment_id' => $assignmentId,
        'users' => $users,
        'count' => count($users)
    ]);
} catch (Throwable $e) {
    $assignmentIdLog = isset($assignmentId) ? (string)$assignmentId : 'n/a';
    $flags = [
        'ua_team_id' => isset($hasUaTeamId) ? (int)$hasUaTeamId : -1,
        'ua_status' => isset($hasUaStatus) ? (int)$hasUaStatus : -1,
        'ua_submitted_at' => isset($hasUaSubmittedAt) ? (int)$hasUaSubmittedAt : -1,
        'ua_graded_at' => isset($hasUaGradedAt) ? (int)$hasUaGradedAt : -1,
        'ua_graded_by' => isset($hasUaGradedBy) ? (int)$hasUaGradedBy : -1,
        'ua_is_late' => isset($hasUaIsLate) ? (int)$hasUaIsLate : -1,
        'ua_due_date' => isset($hasUaDueDate) ? (int)$hasUaDueDate : -1,
        'a_due_date' => isset($hasAssignmentDueDate) ? (int)$hasAssignmentDueDate : -1,
        'a_is_active' => isset($hasAssignmentIsActive) ? (int)$hasAssignmentIsActive : -1,
        'a_available_from' => isset($hasAssignmentAvailableFrom) ? (int)$hasAssignmentAvailableFrom : -1,
        'a_hard_deadline' => isset($hasAssignmentHardDeadline) ? (int)$hasAssignmentHardDeadline : -1,
        'user_tasks' => isset($hasUserTasks) ? (int)$hasUserTasks : -1,
        'user_tasks_status' => isset($hasUserTaskStatus) ? (int)$hasUserTaskStatus : -1,
        'run_count' => isset($hasRunCount) ? (int)$hasRunCount : -1,
        'active_seconds' => isset($hasActiveSeconds) ? (int)$hasActiveSeconds : -1,
    ];
    error_log('Assignment users list error: ' . $e->getMessage() . ' | assignment_id=' . $assignmentIdLog . ' | flags=' . json_encode($flags));
    jsonResponse(['ok' => false, 'error' => 'Failed to load assignment users'], 500);
}
