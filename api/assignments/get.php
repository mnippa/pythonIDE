<?php
/**
 * Get Assignment API
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

$user = requireAuth();
$conn = getDbConnection();

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

function calcTimingPhaseForAccess(array $row): string {
    $now = new DateTimeImmutable('now');
    $availableFrom = parseApiDateTime($row['available_from'] ?? null, false);
    $dueDate = parseApiDateTime($row['effective_due_date'] ?? null, true);
    $hardDeadline = getEffectiveHardDeadline(
        parseApiDateTime($row['hard_deadline'] ?? null, true),
        $dueDate
    );

    if (isset($row['is_active']) && (int)$row['is_active'] === 0) {
        return 'hidden';
    }
    if ($availableFrom !== null && $now < $availableFrom) {
        return 'upcoming';
    }
    if ($hardDeadline !== null && $now > $hardDeadline) {
        return 'closed';
    }
    if ($dueDate !== null && $now > $dueDate) {
        return 'late';
    }

    return 'open';
}

$assignmentId = isset($_GET['id']) ? (int)$_GET['id'] : null;
if (!$assignmentId) {
    jsonResponse(['ok' => false, 'error' => 'Assignment ID required'], 400);
}

$isStudentView = isset($_GET['student_view']) && $_GET['student_view'] === '1';

$sql = '
    SELECT 
        a.id,
        a.title,
        a.description,
        a.code_template,
        a.created_by,
        a.created_at,
        a.updated_at,
        a.is_active,
        a.available_from,
        a.due_date,
        a.hard_deadline,
        a.allow_late_submission,
        ua.user_id AS assigned_user,
        ua.due_date AS user_due_date,
        COALESCE(ua.due_date, a.due_date) AS effective_due_date,
        a.difficulty,
        a.time_limit_minutes,
        u.first_name,
        u.last_name,
        u.email,
        ua.status AS user_status
    FROM assignments a
    LEFT JOIN users u ON a.created_by = u.id
    LEFT JOIN user_assignments ua ON ua.assignment_id = a.id AND ua.user_id = ?
    WHERE a.id = ?
';

$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $user['id'], $assignmentId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    jsonResponse(['ok' => false, 'error' => 'Assignment not found'], 404);
}

$assignment = $result->fetch_assoc();

$canAccess = $user['role'] === 'admin' || (bool)$assignment['is_active'] || $assignment['user_status'] !== null;
if (!$canAccess) {
    jsonResponse(['ok' => false, 'error' => 'Access denied'], 403);
}

if ($isStudentView) {
    $isAssignedToUser = $assignment['assigned_user'] !== null;
    $phase = calcTimingPhaseForAccess($assignment);
    $isOpenable = in_array($phase, ['open', 'late'], true);

    if (!$isAssignedToUser || !$isOpenable) {
        jsonResponse(['ok' => false, 'error' => 'Assignment not available yet'], 403);
    }
}

jsonResponse([
    'ok' => true,
    'assignment' => [
        'id' => (int)$assignment['id'],
        'title' => $assignment['title'],
        'description' => $assignment['description'],
        'code_template' => $assignment['code_template'],
        'created_by' => (int)$assignment['created_by'],
        'created_by_name' => trim($assignment['first_name'] . ' ' . $assignment['last_name']),
        'created_by_email' => $assignment['email'],
        'created_at' => $assignment['created_at'],
        'updated_at' => $assignment['updated_at'],
        'is_active' => (bool)$assignment['is_active'],
        'available_from' => $assignment['available_from'],
        'due_date' => $assignment['due_date'],
        'hard_deadline' => $assignment['hard_deadline'],
        'allow_late_submission' => isset($assignment['allow_late_submission']) ? (bool)$assignment['allow_late_submission'] : true,
        'difficulty' => $assignment['difficulty'],
        'time_limit_minutes' => $assignment['time_limit_minutes'] !== null ? (int)$assignment['time_limit_minutes'] : null,
        'user_status' => $assignment['user_status']
    ]
]);
