<?php
/**
 * Update Assignment API (Admin only)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

$user = requireAdmin();
$conn = getDbConnection();

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
