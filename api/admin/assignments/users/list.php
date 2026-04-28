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

function calcAssignmentTiming(array $row): array {
    $now = new DateTimeImmutable('now');
    $availableFrom = parseApiDateTime($row['available_from'] ?? null, false);
    $dueDate = parseApiDateTime($row['effective_due_date'] ?? null, true);
    $hardDeadline = parseApiDateTime($row['hard_deadline'] ?? null, true);

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
    $totalTasks = (int)($taskStats['total_tasks'] ?? 0);
    $workedTasks = (int)($taskStats['worked_tasks'] ?? 0);
    $passedTasks = (int)($taskStats['passed_tasks'] ?? 0);
    $finalizedTasks = (int)($taskStats['finalized_tasks'] ?? 0);

    $isLate = !empty($row['is_late']);
    if (!$isLate && !empty($row['submitted_at']) && !empty($row['effective_due_date'])) {
        $submittedAt = parseApiDateTime((string)$row['submitted_at'], false);
        $dueDate = parseApiDateTime((string)$row['effective_due_date'], true);
        $isLate = $submittedAt !== null && $dueDate !== null && $submittedAt > $dueDate;
    }

    $allPassed = $totalTasks > 0 && $passedTasks >= $totalTasks;
    $allWorked = $totalTasks > 0 && $finalizedTasks >= $totalTasks;

    if ($rawStatus === 'passed' || $allPassed) {
        $status = $isLate ? 'passed_delayed' : 'passed';
        return [
            'status' => $status,
            'label' => $isLate ? 'Passed delayed' : 'Passed',
            'is_late_completion' => $isLate,
        ];
    }

    if (($rawStatus === 'submitted' && $workedTasks > 0) || $allWorked) {
        return [
            'status' => $isLate ? 'late_completed' : 'completed',
            'label' => $isLate ? 'Verspaetet abgeschlossen' : 'Abgeschlossen',
            'is_late_completion' => $isLate,
        ];
    }

    if ($timing['phase'] === 'closed') {
        return [
            'status' => 'missed',
            'label' => 'Verpasst',
            'is_late_completion' => false,
        ];
    }

    if ($workedTasks > 0 || in_array($rawStatus, ['in_progress', 'submitted', 'failed'], true)) {
        return [
            'status' => 'in_progress',
            'label' => 'In Bearbeitung',
            'is_late_completion' => false,
        ];
    }

    return [
        'status' => 'assigned',
        'label' => 'Zugewiesen',
        'is_late_completion' => false,
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
            COALESCE(ua_user.status, ua_team.status, "assigned") AS raw_status,
            COALESCE(ua_user.submitted_at, ua_team.submitted_at) AS submitted_at,
            COALESCE(ua_user.is_late, ua_team.is_late, 0) AS is_late,
            COALESCE(ua_user.due_date, ua_team.due_date, a.due_date) AS effective_due_date,
            a.is_active AS assignment_active,
            a.available_from,
            a.hard_deadline,
            CASE WHEN ua_user.id IS NOT NULL THEN 1 ELSE 0 END AS is_direct,
            ' . $runSelect . ',
            ' . $activeSelect . '
        FROM users u
        INNER JOIN assignments a ON a.id = ?
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
        // 5 params: assignment join + 2 JOINs + runSelect subquery + activeSelect subquery
        $stmt->bind_param('iiiii', $assignmentId, $assignmentId, $assignmentId, $assignmentId, $assignmentId);
    } elseif ($hasRunCount || $hasActiveSeconds) {
        // 4 params: assignment join + 2 JOINs + one subquery
        $stmt->bind_param('iiii', $assignmentId, $assignmentId, $assignmentId, $assignmentId);
    } else {
        // 3 params: assignment join + the 2 JOINs
        $stmt->bind_param('iii', $assignmentId, $assignmentId, $assignmentId);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $users = [];
    $userRows = [];
    while ($row = $result->fetch_assoc()) {
        $userRows[] = $row;
    }

    $taskStatsByUser = [];
    if (!empty($userRows)) {
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
        $taskStatsTypes = 'i' . str_repeat('i', count($userIds));
        $taskStatsParams = array_merge([$assignmentId], $userIds);
        $taskStatsStmt->bind_param($taskStatsTypes, ...$taskStatsParams);
        $taskStatsStmt->execute();

        $taskStatsResult = $taskStatsStmt->get_result();
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
            'raw_status' => (($row['raw_status'] ?? 'assigned') === 'passed' && !empty($row['is_late']))
                ? 'passed_delayed'
                : ($row['raw_status'] ?? 'assigned'),
            'is_direct' => (bool)$row['is_direct'],
            'run_count' => (int)$row['run_count'],
            'active_seconds' => (int)$row['active_seconds'],
            'is_late_completion' => $displayStatus['is_late_completion'],
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
} catch (Exception $e) {
    error_log('Assignment users list error: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'Failed to load assignment users'], 500);
}
