<?php
/**
 * Admin: Assignment user detail
 * GET ?assignment_id=X&user_id=Y
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
            ua_team.status AS team_status,
            COALESCE(ua_user.submitted_at, ua_team.submitted_at) AS submitted_at,
            COALESCE(ua_user.is_late, ua_team.is_late, 0) AS is_late,
            COALESCE(ua_user.due_date, ua_team.due_date, a.due_date) AS effective_due_date,
            a.is_active AS assignment_active,
            a.available_from,
            a.hard_deadline
        FROM users u
        INNER JOIN assignments a ON a.id = ?
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
        $stmt->bind_param('iiii', $assignmentId, $assignmentId, $assignmentId, $userId);
    } else {
        $stmt->bind_param('iii', $assignmentId, $assignmentId, $userId);
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

    $rawStatus = $status;

    $user = [
        'id' => (int)$userRow['id'],
        'email' => $userRow['email'],
        'first_name' => $userRow['first_name'],
        'last_name' => $userRow['last_name'],
        'full_name' => trim(($userRow['first_name'] ?? '') . ' ' . ($userRow['last_name'] ?? '')),
        'team_id' => $userRow['team_id'] !== null ? (int)$userRow['team_id'] : null,
        'team_name' => $userRow['team_name'],
        'status' => $status,
        'raw_status' => $rawStatus,
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

    $taskStats = [
        'total_tasks' => count($tasks),
        'worked_tasks' => 0,
        'passed_tasks' => 0,
        'finalized_tasks' => 0,
    ];

    foreach ($tasks as $task) {
        $taskStatus = $task['status'] ?? 'unbearbeitet';
        if (in_array($taskStatus, ['in-progress', 'passed', 'failed'], true)) {
            $taskStats['worked_tasks']++;
        }
        if ($taskStatus === 'passed') {
            $taskStats['passed_tasks']++;
            $taskStats['finalized_tasks']++;
        } elseif ($taskStatus === 'failed') {
            $taskStats['finalized_tasks']++;
        }
    }

    $displayRow = [
        'raw_status' => $rawStatus,
        'submitted_at' => $userRow['submitted_at'] ?? null,
        'is_late' => $userRow['is_late'] ?? 0,
        'effective_due_date' => $userRow['effective_due_date'] ?? null,
        'assignment_active' => $userRow['assignment_active'] ?? 1,
        'available_from' => $userRow['available_from'] ?? null,
        'hard_deadline' => $userRow['hard_deadline'] ?? null,
    ];
    $timing = calcAssignmentTiming($displayRow);
    $displayStatus = deriveAssignmentDisplayStatus($displayRow, $timing, $taskStats);
    $user['status'] = $displayStatus['status'];
    $user['status_label'] = $displayStatus['label'];
    $user['is_late_completion'] = $displayStatus['is_late_completion'];

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
