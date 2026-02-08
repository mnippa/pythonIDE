<?php
/**
 * Create Assignment API (Admin only)
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

$title = trim($input['title'] ?? '');
$description = trim($input['description'] ?? '');
$codeTemplate = $input['code_template'] ?? null;
$difficulty = $input['difficulty'] ?? 'beginner';
$timeLimit = isset($input['time_limit_minutes']) ? (int)$input['time_limit_minutes'] : null;
$isActiveInput = $input['is_active'] ?? true;
$isActive = filter_var($isActiveInput, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

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

$stmt = $conn->prepare(
    'INSERT INTO assignments (title, description, code_template, created_by, is_active, difficulty, time_limit_minutes)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$stmt->bind_param(
    'sssissi',
    $title,
    $description,
    $codeTemplate,
    $user['id'],
    $isActive,
    $difficulty,
    $timeLimit
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
            'created_at' => date('Y-m-d H:i:s')
        ]
    ], 201);
} else {
    jsonResponse(['ok' => false, 'error' => 'Failed to create assignment'], 500);
}
