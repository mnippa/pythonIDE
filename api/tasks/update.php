<?php
/**
 * Update Task API (Admin only)
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

$taskId = isset($input['id']) ? (int)$input['id'] : null;
if (!$taskId) {
    jsonResponse(['ok' => false, 'error' => 'Task ID required'], 400);
}

$stmt = $conn->prepare('SELECT id FROM tasks WHERE id = ?');
$stmt->bind_param('i', $taskId);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    jsonResponse(['ok' => false, 'error' => 'Task not found'], 404);
}

$allowedTypes = ['code_completion', 'code_fix', 'multiple_choice', 'essay'];

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

if (isset($input['position'])) {
    $position = (int)$input['position'];
    if ($position < 1) {
        jsonResponse(['ok' => false, 'error' => 'Invalid position'], 400);
    }
    $updates[] = 'position = ?';
    $params[] = $position;
    $types .= 'i';
}

if (isset($input['problem_type'])) {
    $problemType = $input['problem_type'];
    if (!in_array($problemType, $allowedTypes, true)) {
        jsonResponse(['ok' => false, 'error' => 'Invalid problem_type'], 400);
    }
    $updates[] = 'problem_type = ?';
    $params[] = $problemType;
    $types .= 's';
}

if (array_key_exists('code_template', $input)) {
    $updates[] = 'code_template = ?';
    $params[] = $input['code_template'];
    $types .= 's';
}

if (array_key_exists('hint', $input)) {
    $updates[] = 'hint = ?';
    $params[] = $input['hint'];
    $types .= 's';
}

if (array_key_exists('hint1', $input)) {
    $updates[] = 'hint1 = ?';
    $params[] = $input['hint1'];
    $types .= 's';
}

if (array_key_exists('hint2', $input)) {
    $updates[] = 'hint2 = ?';
    $params[] = $input['hint2'];
    $types .= 's';
}

if (array_key_exists('hint3', $input)) {
    $updates[] = 'hint3 = ?';
    $params[] = $input['hint3'];
    $types .= 's';
}

if (array_key_exists('stoff', $input)) {
    $updates[] = 'stoff = ?';
    $params[] = $input['stoff'];
    $types .= 's';
}

if (array_key_exists('expected_output', $input)) {
    $updates[] = 'expected_output = ?';
    $params[] = $input['expected_output'];
    $types .= 's';
}

if (array_key_exists('validation_mode', $input)) {
    $updates[] = 'validation_mode = ?';
    $params[] = $input['validation_mode'];
    $types .= 's';
}

if (array_key_exists('test_cases', $input)) {
    $updates[] = 'test_cases = ?';
    $params[] = $input['test_cases'];
    $types .= 's';
}

if (array_key_exists('solution_code', $input)) {
    $updates[] = 'solution_code = ?';
    $params[] = $input['solution_code'];
    $types .= 's';
}

if (empty($updates)) {
    jsonResponse(['ok' => false, 'error' => 'No fields to update'], 400);
}

$params[] = $taskId;
$types .= 'i';

$sql = 'UPDATE tasks SET ' . implode(', ', $updates) . ' WHERE id = ?';
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    jsonResponse(['ok' => true, 'message' => 'Task updated']);
} else {
    jsonResponse(['ok' => false, 'error' => 'Failed to update task'], 500);
}
