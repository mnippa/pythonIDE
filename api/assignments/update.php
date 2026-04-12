<?php
/**
 * Update Assignment API (Admin only)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

$user = requireAdmin();
$conn = getDbConnection();

function normalizeDateTimeInput($value): ?string {
    if ($value === null) {
        return null;
    }
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return null;
    }
    return date('Y-m-d H:i:s', $ts);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
    jsonResponse(['ok' => false, 'error' => 'Invalid JSON'], 400);
}

$assignmentId = isset($input['id']) ? (int)$input['id'] : null;
if (!$assignmentId) {
    jsonResponse(['ok' => false, 'error' => 'Assignment ID required'], 400);
}

requireAdminOwnedAssignment($conn, $assignmentId, $user);

$allowedDifficulties = ['beginner', 'intermediate', 'advanced'];

$updates = [];
$params = [];
$types = '';

if (isset($input['title'])) {
    $title = trim($input['title']);
    if ($title === '') {
        jsonResponse(['ok' => false, 'error' => 'Title cannot be empty'], 400);
    }
    $updates[] = 'title = ?';
    $params[] = $title;
    $types .= 's';
}

if (isset($input['description'])) {
    $updates[] = 'description = ?';
    $params[] = trim($input['description']);
    $types .= 's';
}

if (array_key_exists('code_template', $input)) {
    $updates[] = 'code_template = ?';
    $params[] = $input['code_template'];
    $types .= 's';
}

if (isset($input['is_active'])) {
    $isActive = filter_var($input['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($isActive === null) {
        jsonResponse(['ok' => false, 'error' => 'Invalid is_active value'], 400);
    }
    $updates[] = 'is_active = ?';
    $params[] = (int)$isActive;
    $types .= 'i';
}

if (isset($input['difficulty'])) {
    $difficulty = $input['difficulty'];
    if (!in_array($difficulty, $allowedDifficulties, true)) {
        jsonResponse(['ok' => false, 'error' => 'Invalid difficulty'], 400);
    }
    $updates[] = 'difficulty = ?';
    $params[] = $difficulty;
    $types .= 's';
}

if (array_key_exists('time_limit_minutes', $input)) {
    $timeLimit = $input['time_limit_minutes'] !== null ? (int)$input['time_limit_minutes'] : null;
    if ($timeLimit !== null && $timeLimit < 1) {
        jsonResponse(['ok' => false, 'error' => 'Invalid time limit'], 400);
    }
    $updates[] = 'time_limit_minutes = ?';
    $params[] = $timeLimit;
    $types .= 'i';
}

if (array_key_exists('available_from', $input)) {
    $value = normalizeDateTimeInput($input['available_from']);
    if ($input['available_from'] !== null && trim((string)$input['available_from']) !== '' && $value === null) {
        jsonResponse(['ok' => false, 'error' => 'Invalid available_from datetime'], 400);
    }
    $updates[] = 'available_from = ?';
    $params[] = $value;
    $types .= 's';
}

if (array_key_exists('due_date', $input)) {
    $value = normalizeDateTimeInput($input['due_date']);
    if ($input['due_date'] !== null && trim((string)$input['due_date']) !== '' && $value === null) {
        jsonResponse(['ok' => false, 'error' => 'Invalid due_date datetime'], 400);
    }
    $updates[] = 'due_date = ?';
    $params[] = $value;
    $types .= 's';
}

if (array_key_exists('hard_deadline', $input)) {
    $value = normalizeDateTimeInput($input['hard_deadline']);
    if ($input['hard_deadline'] !== null && trim((string)$input['hard_deadline']) !== '' && $value === null) {
        jsonResponse(['ok' => false, 'error' => 'Invalid hard_deadline datetime'], 400);
    }
    $updates[] = 'hard_deadline = ?';
    $params[] = $value;
    $types .= 's';
}

if (array_key_exists('allow_late_submission', $input)) {
    $allowLate = filter_var($input['allow_late_submission'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($allowLate === null) {
        jsonResponse(['ok' => false, 'error' => 'Invalid allow_late_submission value'], 400);
    }
    $updates[] = 'allow_late_submission = ?';
    $params[] = (int)$allowLate;
    $types .= 'i';
}

if (array_key_exists('available_from', $input) || array_key_exists('due_date', $input) || array_key_exists('hard_deadline', $input)) {
    $dateStmt = $conn->prepare('SELECT available_from, due_date, hard_deadline FROM assignments WHERE id = ?');
    $dateStmt->bind_param('i', $assignmentId);
    $dateStmt->execute();
    $dateRow = $dateStmt->get_result()->fetch_assoc() ?: [];

    $effectiveAvailable = array_key_exists('available_from', $input)
        ? normalizeDateTimeInput($input['available_from'])
        : ($dateRow['available_from'] ?? null);
    $effectiveDue = array_key_exists('due_date', $input)
        ? normalizeDateTimeInput($input['due_date'])
        : ($dateRow['due_date'] ?? null);
    $effectiveHard = array_key_exists('hard_deadline', $input)
        ? normalizeDateTimeInput($input['hard_deadline'])
        : ($dateRow['hard_deadline'] ?? null);

    if ($effectiveAvailable !== null && $effectiveDue !== null && strtotime($effectiveDue) < strtotime($effectiveAvailable)) {
        jsonResponse(['ok' => false, 'error' => 'due_date must be on/after available_from'], 400);
    }
    if ($effectiveDue !== null && $effectiveHard !== null && strtotime($effectiveHard) < strtotime($effectiveDue)) {
        jsonResponse(['ok' => false, 'error' => 'hard_deadline must be on/after due_date'], 400);
    }
}

if (empty($updates)) {
    jsonResponse(['ok' => false, 'error' => 'No fields to update'], 400);
}

$params[] = $assignmentId;
$types .= 'i';

$sql = 'UPDATE assignments SET ' . implode(', ', $updates) . ' WHERE id = ?';
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    jsonResponse(['ok' => true, 'message' => 'Assignment updated']);
} else {
    jsonResponse(['ok' => false, 'error' => 'Failed to update assignment'], 500);
}
