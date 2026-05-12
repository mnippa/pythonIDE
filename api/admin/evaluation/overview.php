<?php
/**
 * Admin: Assignment evaluation overview
 * GET ?assignment_id=X
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../auth/middleware.php';

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
    $hasHintsRevealed = $hasUserTasks && $columnExists($conn, 'user_tasks', 'hints_revealed');
    $hasActiveSeconds = $hasUserTasks && $columnExists($conn, 'user_tasks', 'active_seconds');

    $assignedUsers = [];

    $directStmt = $conn->prepare('
        SELECT
            u.id,
            ua.status AS raw_status,
            ua.submitted_at,
            ua.is_late,
            ua.is_rework,
            ua.due_date AS user_due_date,
            a.due_date AS assignment_due_date,
            COALESCE(ua.due_date, a.due_date) AS effective_due_date,
            a.is_active AS assignment_active,
            a.available_from,
            a.hard_deadline
        FROM user_assignments ua
        INNER JOIN users u ON u.id = ua.user_id
        INNER JOIN assignments a ON a.id = ua.assignment_id
        WHERE ua.assignment_id = ? AND ua.user_id IS NOT NULL
    ');
    $directStmt->bind_param('i', $assignmentId);
    $directStmt->execute();
    $directResult = $directStmt->get_result();
    while ($row = $directResult->fetch_assoc()) {
        $assignedUsers[(int)$row['id']] = $row;
    }

    if ($hasUserTeamId && $hasAssignmentTeamId) {
        $teamStmt = $conn->prepare('
            SELECT
                u.id,
                ua.status AS raw_status,
                ua.submitted_at,
                ua.is_late,
                ua.is_rework,
                ua.due_date AS user_due_date,
                a.due_date AS assignment_due_date,
                COALESCE(ua.due_date, a.due_date) AS effective_due_date,
                a.is_active AS assignment_active,
                a.available_from,
                a.hard_deadline
            FROM user_assignments ua
            INNER JOIN users u ON u.team_id = ua.team_id
            INNER JOIN assignments a ON a.id = ua.assignment_id
            WHERE ua.assignment_id = ? AND ua.team_id IS NOT NULL
        ');
        $teamStmt->bind_param('i', $assignmentId);
        $teamStmt->execute();
        $teamResult = $teamStmt->get_result();
        while ($row = $teamResult->fetch_assoc()) {
            $userId = (int)$row['id'];
            if (!isset($assignedUsers[$userId])) {
                $assignedUsers[$userId] = $row;
            }
        }
    }

    $stats = [
        'total' => count($assignedUsers),
        'assigned' => 0,
        'in_progress' => 0,
        'rework' => 0,
        'completed' => 0,
        'late_completed' => 0,
        'passed' => 0,
        'passed_delayed' => 0,
        'missed' => 0,
        // Legacy aliases for old UI consumers
        'unstarted' => 0,
        'failed' => 0,
        'avg_runs' => 0
    ];

    $taskStatsByUser = [];
    if (count($assignedUsers) > 0) {
        $userIds = array_keys($assignedUsers);
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
            $taskStatsByUser[(int)$statsRow['user_id']] = [
                'total_tasks' => (int)($statsRow['total_tasks'] ?? 0),
                'worked_tasks' => (int)($statsRow['worked_tasks'] ?? 0),
                'passed_tasks' => (int)($statsRow['passed_tasks'] ?? 0),
                'finalized_tasks' => (int)($statsRow['finalized_tasks'] ?? 0),
            ];
        }
    }

    foreach ($assignedUsers as $userId => $row) {
        $timing = calcAssignmentTiming($row);
        $taskStats = $taskStatsByUser[(int)$userId] ?? [
            'total_tasks' => 0,
            'worked_tasks' => 0,
            'passed_tasks' => 0,
            'finalized_tasks' => 0,
        ];
        $displayStatus = deriveAssignmentDisplayStatus($row, $timing, $taskStats);
        $statusKey = $displayStatus['status'];
        if (!isset($stats[$statusKey])) {
            $statusKey = 'assigned';
        }
        $stats[$statusKey]++;
    }

    $stats['unstarted'] = $stats['assigned'];
    $stats['failed'] = $stats['missed'];

    $tasks = [];
    $taskStmt = $conn->prepare('SELECT id, title, position FROM tasks WHERE assignment_id = ? ORDER BY position ASC');
    $taskStmt->bind_param('i', $assignmentId);
    $taskStmt->execute();
    $taskResult = $taskStmt->get_result();

    while ($row = $taskResult->fetch_assoc()) {
        $tasks[] = [
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'position' => (int)$row['position'],
            'stats' => [
                'unstarted' => 0,
                'in_progress' => 0,
                'passed' => 0,
                'failed' => 0
            ],
            'sum_checks' => 0,
            'avg_checks' => 0,
            'sum_runs' => 0,
            'avg_runs' => 0,
            'sum_hints' => 0,
            'avg_hints' => 0,
            'sum_active_seconds' => 0,
            'avg_active_seconds' => 0
        ];
    }

    if ($hasUserTasks && $stats['total'] > 0 && count($tasks) > 0) {
        $taskIds = array_map(function ($t) { return $t['id']; }, $tasks);
        $userIds = array_keys($assignedUsers);

        $taskPlaceholders = implode(',', array_fill(0, count($taskIds), '?'));
        $userPlaceholders = implode(',', array_fill(0, count($userIds), '?'));
        $types = str_repeat('i', count($taskIds) + count($userIds));
        $params = array_merge($taskIds, $userIds);

        $runSelect = $hasRunCount ? 'ut.run_count' : '0';
        $hintsSelect = $hasHintsRevealed ? 'IFNULL(JSON_LENGTH(ut.hints_revealed), 0)' : '0';
        $activeSelect = $hasActiveSeconds ? 'ut.active_seconds' : '0';

        $sql = "SELECT ut.task_id, ut.status, ut.attempts,
                       {$runSelect} AS run_count,
                       {$hintsSelect} AS hints_count,
                       {$activeSelect} AS active_seconds
                FROM user_tasks ut
                WHERE ut.task_id IN ({$taskPlaceholders})
                  AND ut.user_id IN ({$userPlaceholders})";

        $statStmt = $conn->prepare($sql);
        if ($statStmt) {
            $statStmt->bind_param($types, ...$params);
            $statStmt->execute();
            $statResult = $statStmt->get_result();

            $statsByTask = [];
            $avgByTask = [];
            while ($row = $statResult->fetch_assoc()) {
                $taskId = (int)$row['task_id'];
                $status = $row['status'] ?? 'unbearbeitet';
                $attempts = (int)$row['attempts'];
                $runs = (int)$row['run_count'];
                $hints = (int)$row['hints_count'];
                $activeSeconds = (int)$row['active_seconds'];

                if (!isset($statsByTask[$taskId])) {
                    $statsByTask[$taskId] = [
                        'in_progress' => 0,
                        'passed' => 0,
                        'failed' => 0
                    ];
                }

                if (!isset($avgByTask[$taskId])) {
                    $avgByTask[$taskId] = [
                        'attempts' => 0,
                        'runs' => 0,
                        'hints' => 0,
                        'active_seconds' => 0,
                        'entries' => 0
                    ];
                }

                if ($status === 'in-progress') {
                    $statsByTask[$taskId]['in_progress'] += 1;
                } elseif ($status === 'passed') {
                    $statsByTask[$taskId]['passed'] += 1;
                } elseif ($status === 'failed') {
                    $statsByTask[$taskId]['failed'] += 1;
                }

                $avgByTask[$taskId]['attempts'] += $attempts;
                $avgByTask[$taskId]['runs'] += $runs;
                $avgByTask[$taskId]['hints'] += $hints;
                $avgByTask[$taskId]['active_seconds'] += $activeSeconds;
                $avgByTask[$taskId]['entries'] += 1;
            }

            $totalRuns = 0;
            $totalEntries = 0;

            foreach ($tasks as &$task) {
                $taskId = $task['id'];
                $taskStats = $statsByTask[$taskId] ?? ['in_progress' => 0, 'passed' => 0, 'failed' => 0];
                $taskAvg = $avgByTask[$taskId] ?? ['attempts' => 0, 'runs' => 0, 'hints' => 0, 'active_seconds' => 0, 'entries' => 0];
                $started = $taskStats['in_progress'] + $taskStats['passed'] + $taskStats['failed'];
                $unstarted = max(0, $stats['total'] - $started);

                $task['stats']['in_progress'] = $taskStats['in_progress'];
                $task['stats']['passed'] = $taskStats['passed'];
                $task['stats']['failed'] = $taskStats['failed'];
                $task['stats']['unstarted'] = $unstarted;

                if ($taskAvg['entries'] > 0) {
                    $task['sum_checks'] = $taskAvg['attempts'];
                    $task['avg_checks'] = $taskAvg['attempts'] / $taskAvg['entries'];
                    $task['sum_runs'] = $taskAvg['runs'];
                    $task['avg_runs'] = $taskAvg['runs'] / $taskAvg['entries'];
                    $task['sum_hints'] = $taskAvg['hints'];
                    $task['avg_hints'] = $taskAvg['hints'] / $taskAvg['entries'];
                    $task['sum_active_seconds'] = $taskAvg['active_seconds'];
                    $task['avg_active_seconds'] = $taskAvg['active_seconds'] / $taskAvg['entries'];
                }

                $totalRuns += $taskAvg['runs'];
                $totalEntries += $taskAvg['entries'];
            }
            unset($task);

            if ($totalEntries > 0) {
                $stats['avg_runs'] = $totalRuns / $totalEntries;
            }
        }
    } else {
        foreach ($tasks as &$task) {
            $task['stats']['unstarted'] = $stats['total'];
        }
        unset($task);
    }

    jsonResponse([
        'ok' => true,
        'assignment_id' => $assignmentId,
        'title' => $assignment['title'],
        'stats' => $stats,
        'tasks' => $tasks
    ]);
} catch (Exception $e) {
    error_log('Evaluation overview error: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'Failed to load evaluation overview'], 500);
}
