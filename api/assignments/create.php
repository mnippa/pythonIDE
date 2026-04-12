<?php
/**
 * Create Assignment API (Admin only)
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

$title = trim($input['title'] ?? '');
$description = trim($input['description'] ?? '');
$codeTemplate = $input['code_template'] ?? null;
$difficulty = $input['difficulty'] ?? 'beginner';
$timeLimit = isset($input['time_limit_minutes']) ? (int)$input['time_limit_minutes'] : null;
$isActiveInput = $input['is_active'] ?? true;
$isActive = filter_var($isActiveInput, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

$availableFrom = array_key_exists('available_from', $input)
    ? normalizeDateTimeInput($input['available_from'])
    : date('Y-m-d H:i:s');
$dueDate = array_key_exists('due_date', $input)
    ? normalizeDateTimeInput($input['due_date'])
    : date('Y-m-d H:i:s', strtotime('+14 days'));
$hardDeadline = array_key_exists('hard_deadline', $input)
    ? normalizeDateTimeInput($input['hard_deadline'])
    : date('Y-m-d H:i:s', strtotime('+17 days'));
$allowLateInput = $input['allow_late_submission'] ?? true;
$allowLateSubmission = filter_var($allowLateInput, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

if ($isActive === null) {
    $isActive = true;
}

if ($title === '') {
    jsonResponse(['ok' => false, 'error' => 'Title is required'], 400);
}

$allowedDifficulties = ['beginner', 'intermediate', 'advanced'];
if (!in_array($difficulty, $allowedDifficulties, true)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid difficulty'], 400);
}

if ($timeLimit !== null && $timeLimit < 1) {
    jsonResponse(['ok' => false, 'error' => 'Invalid time limit'], 400);
}

if ($allowLateSubmission === null) {
    $allowLateSubmission = true;
}

if (array_key_exists('available_from', $input) && $input['available_from'] !== null && $availableFrom === null) {
    jsonResponse(['ok' => false, 'error' => 'Invalid available_from datetime'], 400);
}
if (array_key_exists('due_date', $input) && $input['due_date'] !== null && $dueDate === null) {
    jsonResponse(['ok' => false, 'error' => 'Invalid due_date datetime'], 400);
}
if (array_key_exists('hard_deadline', $input) && $input['hard_deadline'] !== null && $hardDeadline === null) {
    jsonResponse(['ok' => false, 'error' => 'Invalid hard_deadline datetime'], 400);
}

if ($availableFrom !== null && $dueDate !== null && strtotime($dueDate) < strtotime($availableFrom)) {
    jsonResponse(['ok' => false, 'error' => 'due_date must be on/after available_from'], 400);
}
if ($dueDate !== null && $hardDeadline !== null && strtotime($hardDeadline) < strtotime($dueDate)) {
    jsonResponse(['ok' => false, 'error' => 'hard_deadline must be on/after due_date'], 400);
}

$stmt = $conn->prepare(
    'INSERT INTO assignments (title, description, code_template, created_by, is_active, difficulty, time_limit_minutes, available_from, due_date, hard_deadline, allow_late_submission)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$stmt->bind_param(
    'sssississsi',
    $title,
    $description,
    $codeTemplate,
    $user['id'],
    $isActive,
    $difficulty,
    $timeLimit,
    $availableFrom,
    $dueDate,
    $hardDeadline,
    $allowLateSubmission
);

if ($stmt->execute()) {
    $assignmentId = $conn->insert_id;

    jsonResponse([
        'ok' => true,
        'assignment' => [
            'id' => $assignmentId,
            'title' => $title,
            'description' => $description,
            'code_template' => $codeTemplate,
            'created_by' => $user['id'],
            'is_active' => (bool)$isActive,
            'difficulty' => $difficulty,
            'time_limit_minutes' => $timeLimit,
            'available_from' => $availableFrom,
            'due_date' => $dueDate,
            'hard_deadline' => $hardDeadline,
            'allow_late_submission' => (bool)$allowLateSubmission,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ], 201);
} else {
    jsonResponse(['ok' => false, 'error' => 'Failed to create assignment'], 500);
}
