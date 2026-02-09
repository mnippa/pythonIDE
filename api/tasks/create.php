<?php
/**
 * Create Task API (Admin only)
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

$assignmentId = isset($input['assignment_id']) ? (int)$input['assignment_id'] : null;
$title = trim($input['title'] ?? '');
$description = trim($input['description'] ?? '');
$position = isset($input['position']) ? (int)$input['position'] : null;
$problemType = $input['problem_type'] ?? 'code_completion';
$codeTemplate = $input['code_template'] ?? null;
$hint = $input['hint'] ?? null;
$hint1 = $input['hint1'] ?? null;
$hint2 = $input['hint2'] ?? null;
$hint3 = $input['hint3'] ?? null;
$stoff = $input['stoff'] ?? null;
$expectedOutput = $input['expected_output'] ?? null;
$validationMode = $input['validation_mode'] ?? null;
$testCases = $input['test_cases'] ?? null;
$solutionCode = $input['solution_code'] ?? null;

if (!$assignmentId) {
    jsonResponse(['ok' => false, 'error' => 'Assignment ID required'], 400);
}

if ($title === '') {
    jsonResponse(['ok' => false, 'error' => 'Title is required'], 400);
}

$allowedTypes = ['code_completion', 'code_fix', 'multiple_choice', 'essay'];
if (!in_array($problemType, $allowedTypes, true)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid problem_type'], 400);
}

$stmt = $conn->prepare('SELECT id FROM assignments WHERE id = ?');
$stmt->bind_param('i', $assignmentId);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    jsonResponse(['ok' => false, 'error' => 'Assignment not found'], 404);
}

if ($position === null || $position < 1) {
    $stmt = $conn->prepare('SELECT COALESCE(MAX(position), 0) AS max_pos FROM tasks WHERE assignment_id = ?');
    $stmt->bind_param('i', $assignmentId);
    $stmt->execute();
    $maxPos = (int)$stmt->get_result()->fetch_assoc()['max_pos'];
    $position = $maxPos + 1;
}

$stmt = $conn->prepare(
    'INSERT INTO tasks (assignment_id, title, description, position, problem_type, code_template, hint, hint1, hint2, hint3, stoff, expected_output, validation_mode, test_cases, solution_code)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$stmt->bind_param(
    'isisssssssssss',
    $assignmentId,
    $title,
    $description,
    $position,
    $problemType,
    $codeTemplate,
    $hint,
    $hint1,
    $hint2,
    $hint3,
    $stoff,
    $expectedOutput,
    $validationMode,
    $testCases,
    $solutionCode
);

if ($stmt->execute()) {
    $taskId = $conn->insert_id;

    jsonResponse([
        'ok' => true,
        'task' => [
            'id' => $taskId,
            'assignment_id' => $assignmentId,
            'title' => $title,
            'description' => $description,
            'position' => $position,
            'problem_type' => $problemType,
            'code_template' => $codeTemplate,
            'hint' => $hint,
            'expected_output' => $expectedOutput,
            'validation_mode' => $validationMode,
            'test_cases' => $testCases,
            'solution_code' => $solutionCode,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ], 201);
} else {
    jsonResponse(['ok' => false, 'error' => 'Failed to create task'], 500);
}
