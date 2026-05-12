<?php
/**
 * List user assignments
 * Admin: list by user_id or assignment_id
 * User: list own assignments
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

$user = requireAuth();
$conn = getDbConnection();

function formatRemainingTime(?DateTimeImmutable $deadline): ?string {
    if ($deadline === null) {
        return null;
    }
    
    $now = new DateTimeImmutable('now');
    if ($now >= $deadline) {
        return null; // Zeit ist vorbei
    }
    
    $diff = $now->diff($deadline);
    
    // Tage extrahieren
    $days = (int)$diff->format('%a');
    $hours = (int)$diff->format('%h');
    $minutes = (int)$diff->format('%i');
    
    if ($days >= 1) {
        // Format: "X Tage, Y Stunden"
        $dayLabel = $days === 1 ? 'Tag' : 'Tage';
        $hourLabel = $hours === 1 ? 'Stunde' : 'Stunden';
        return "{$days} {$dayLabel}, {$hours} {$hourLabel}";
    } else {
        // Format: "HH:MM"
        $hoursFormatted = str_pad($hours, 2, '0', STR_PAD_LEFT);
        $minutesFormatted = str_pad($minutes, 2, '0', STR_PAD_LEFT);
        return "{$hoursFormatted}:{$minutesFormatted}";
    }
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

    // Format remaining time for display
    $formattedTime = null;
    if ($dueDate !== null) {
        $formattedTime = formatRemainingTime($dueDate);
    }

    return [
        'phase' => $phase,
        'days_remaining' => $daysRemaining,
        'formatted_time_remaining' => $formattedTime,
    ];
}

function buildAssignmentTaskStatsMap(mysqli $conn, int $userId, array $assignmentIds): array {
    $assignmentIds = array_values(array_unique(array_filter(array_map('intval', $assignmentIds))));
    if (empty($assignmentIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($assignmentIds), '?'));
    $sql = "
        SELECT
            t.assignment_id,
            COUNT(*) AS total_tasks,
            SUM(CASE WHEN ut.status IN ('in-progress', 'passed', 'failed') THEN 1 ELSE 0 END) AS worked_tasks,
            SUM(CASE WHEN ut.status = 'passed' THEN 1 ELSE 0 END) AS passed_tasks,
            SUM(CASE WHEN ut.status = 'failed' THEN 1 ELSE 0 END) AS failed_tasks,
            SUM(CASE WHEN ut.status IN ('passed', 'failed') THEN 1 ELSE 0 END) AS finalized_tasks
        FROM tasks t
        LEFT JOIN user_tasks ut ON ut.task_id = t.id AND ut.user_id = ?
        WHERE t.assignment_id IN ($placeholders)
        GROUP BY t.assignment_id
    ";

    $stmt = $conn->prepare($sql);
    $types = 'i' . str_repeat('i', count($assignmentIds));
    $params = array_merge([$userId], $assignmentIds);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    $statsMap = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $statsMap[(int)$row['assignment_id']] = [
            'total_tasks' => (int)($row['total_tasks'] ?? 0),
            'worked_tasks' => (int)($row['worked_tasks'] ?? 0),
            'passed_tasks' => (int)($row['passed_tasks'] ?? 0),
            'failed_tasks' => (int)($row['failed_tasks'] ?? 0),
            'finalized_tasks' => (int)($row['finalized_tasks'] ?? 0),
        ];
    }

    return $statsMap;
}

function deriveAssignmentDisplayStatus(array $row, array $timing, array $taskStats): array {
    $rawStatus = (string)($row['status'] ?? 'assigned');
    $totalTasks = (int)($taskStats['total_tasks'] ?? 0);
    $workedTasks = (int)($taskStats['worked_tasks'] ?? 0);
    $passedTasks = (int)($taskStats['passed_tasks'] ?? 0);
    $finalizedTasks = (int)($taskStats['finalized_tasks'] ?? 0);

    // is_late is now correctly set by migration 054 backfill
    $isLate = !empty($row['is_late']);

    $allPassed = $totalTasks > 0 && $passedTasks >= $totalTasks;
    $allWorked = $totalTasks > 0 && $finalizedTasks >= $totalTasks;
    $isRework = isReworkState($row, $rawStatus, $allPassed, $allWorked);

    if ($isRework) {
        return [
            'status' => 'rework',
            'label' => 'Nacharbeit',
            'is_late' => $isLate,
            'is_late_completion' => false,
            'is_rework' => true,
        ];
    }

    if ($rawStatus === 'passed' || $allPassed) {
        $status = $isLate ? 'passed_delayed' : 'passed';
        return [
            'status' => $status,
            'label' => $isLate ? 'Bestanden (verspaetet)' : 'Bestanden',
            'is_late' => $isLate,
            'is_late_completion' => $isLate,
            'is_rework' => false,
        ];
    }

    if (($rawStatus === 'submitted' && $workedTasks > 0) || $allWorked) {
        return [
            'status' => $isLate ? 'late_completed' : 'completed',
            'label' => $isLate ? 'Verspaetet abgeschlossen' : 'Abgeschlossen',
            'is_late' => $isLate,
            'is_late_completion' => $isLate,
            'is_rework' => false,
        ];
    }

    if ($timing['phase'] === 'closed') {
        return [
            'status' => 'missed',
            'label' => 'Verpasst',
            'is_late' => $isLate,
            'is_late_completion' => false,
            'is_rework' => false,
        ];
    }

    if ($workedTasks > 0 || in_array($rawStatus, ['in_progress', 'submitted', 'failed'], true)) {
        return [
            'status' => 'in_progress',
            'label' => 'In Bearbeitung',
            'is_late' => $isLate,
            'is_late_completion' => false,
            'is_rework' => false,
        ];
    }

    return [
        'status' => 'assigned',
        'label' => 'Zugewiesen',
        'is_late' => $isLate,
        'is_late_completion' => false,
        'is_rework' => false,
    ];
}

$filterUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$filterAssignmentId = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : null;
$statusFilter = isset($_GET['status']) ? $_GET['status'] : null;
$showAll = isset($_GET['all']) && $_GET['all'] === '1';

$allowedStatus = ['assigned', 'in_progress', 'rework', 'submitted', 'passed', 'failed'];
if ($statusFilter !== null && !in_array($statusFilter, $allowedStatus, true)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid status filter'], 400);
}

if ($user['role'] !== 'admin' || !$showAll) {
    $filterUserId = $user['id'];
    $filterAssignmentId = null;
}

$sql = '
    SELECT 
        ua.id,
        ua.user_id,
        ua.assignment_id,
        ua.status,
        ua.attempts,
        ua.assigned_at,
        ua.submitted_at,
        ua.due_date AS user_due_date,
        ua.is_late,
        ua.is_rework,
        ua.graded_at,
        a.is_active AS assignment_active,
        a.available_from,
        a.due_date AS assignment_due_date,
        a.hard_deadline,
        a.allow_late_submission,
        COALESCE(ua.due_date, a.due_date) AS effective_due_date,
        a.title AS assignment_title,
        a.difficulty AS assignment_difficulty,
        u.email AS user_email,
        u.first_name,
        u.last_name
    FROM user_assignments ua
    JOIN assignments a ON a.id = ua.assignment_id
    JOIN users u ON u.id = ua.user_id
    WHERE 1 = 1
';

$params = [];
$types = '';

if ($filterUserId) {
    $sql .= ' AND ua.user_id = ?';
    $params[] = $filterUserId;
    $types .= 'i';
}

if ($filterAssignmentId) {
    $sql .= ' AND ua.assignment_id = ?';
    $params[] = $filterAssignmentId;
    $types .= 'i';
}

if ($statusFilter) {
    $sql .= ' AND ua.status = ?';
    $params[] = $statusFilter;
    $types .= 's';
}

$sql .= ' ORDER BY ua.assigned_at DESC';

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

$taskStatsMap = buildAssignmentTaskStatsMap(
    $conn,
    (int)($filterUserId ?: $user['id']),
    array_map(static fn(array $row): int => (int)$row['assignment_id'], $rows)
);

$items = [];
foreach ($rows as $row) {
    $timing = calcAssignmentTiming($row);
    $taskStats = $taskStatsMap[(int)$row['assignment_id']] ?? [
        'total_tasks' => 0,
        'worked_tasks' => 0,
        'passed_tasks' => 0,
        'failed_tasks' => 0,
        'finalized_tasks' => 0,
    ];
    $displayStatus = deriveAssignmentDisplayStatus($row, $timing, $taskStats);

    $items[] = [
        'id' => (int)$row['id'],
        'user_id' => (int)$row['user_id'],
        'assignment_id' => (int)$row['assignment_id'],
        'status' => $displayStatus['status'],
        'status_label' => $displayStatus['label'],
        'raw_status' => $row['status'],
        'attempts' => (int)$row['attempts'],
        'assigned_at' => $row['assigned_at'],
        'submitted_at' => $row['submitted_at'],
        'graded_at' => $row['graded_at'] ?? null,
        'user_due_date' => $row['user_due_date'],
        'available_from' => $row['available_from'],
        'due_date' => $row['effective_due_date'],
        'hard_deadline' => $row['hard_deadline'],
        'allow_late_submission' => isset($row['allow_late_submission']) ? (bool)$row['allow_late_submission'] : true,
        'is_late' => $displayStatus['is_late'] ?? (isset($row['is_late']) ? (bool)$row['is_late'] : false),
        'is_rework' => $displayStatus['is_rework'] ?? (isset($row['is_rework']) ? (bool)$row['is_rework'] : false),
        'timing_phase' => $timing['phase'],
        'days_remaining' => $timing['days_remaining'],
        'formatted_time_remaining' => $timing['formatted_time_remaining'],
        'assignment_title' => $row['assignment_title'],
        'assignment_difficulty' => $row['assignment_difficulty'],
        'user_email' => $row['user_email'],
        'user_name' => trim($row['first_name'] . ' ' . $row['last_name']),
        'task_progress' => $taskStats,
        'is_late_completion' => $displayStatus['is_late_completion'],
    ];
}

jsonResponse([
    'ok' => true,
    'items' => $items,
    'count' => count($items)
]);
