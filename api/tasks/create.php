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
$maxAttempts = isset($input['max_attempts']) ? (int)$input['max_attempts'] : 1;
$showSolution = isset($input['show_solution']) ? (int)$input['show_solution'] : 1;
$minKeywordsRequired = isset($input['min_keywords_required']) ? (int)$input['min_keywords_required'] : null;
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

// New fields for quiz-style tasks
$taskType = $input['task_type'] ?? 'code';
$questionText = trim($input['question_text'] ?? '');
$imageUrl = trim($input['image_url'] ?? '') ?: null;
$correctAnswer = trim($input['correct_answer'] ?? '') ?: null;
$variableOverrides = $input['variable_overrides'] ?? null;
$options = $input['options'] ?? [];

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

// Validate task_type
$allowedTaskTypes = ['code', 'single_choice', 'multiple_choice', 'free_text', 'code_reading'];
if (!in_array($taskType, $allowedTaskTypes, true)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid task_type'], 400);
}

// Validate quiz tasks have question_text
if (in_array($taskType, ['single_choice', 'multiple_choice', 'free_text']) && empty($questionText)) {
    jsonResponse(['ok' => false, 'error' => 'question_text required for ' . $taskType], 400);
}

// Validate choice tasks have options
if (in_array($taskType, ['single_choice', 'multiple_choice']) && empty($options)) {
    jsonResponse(['ok' => false, 'error' => 'options required for ' . $taskType], 400);
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

if ($maxAttempts < 1) {
    $maxAttempts = 1;
}

// Encode JSON fields
$variableOverridesJson = null;
if ($variableOverrides) {
    if (is_string($variableOverrides)) {
        $variableOverridesJson = $variableOverrides;
    } else {
        $variableOverridesJson = json_encode($variableOverrides);
    }
}

// Ensure all string values are strings (not null or array)
$codeTemplate = is_string($codeTemplate) ? $codeTemplate : '';
$hint = is_string($hint) ? $hint : '';
$expectedOutput = is_string($expectedOutput) ? $expectedOutput : '';
$validationMode = is_string($validationMode) ? $validationMode : '';
$testCases = is_string($testCases) ? $testCases : '';
$solutionCode = is_string($solutionCode) ? $solutionCode : '';

$stmt = $conn->prepare(
    'INSERT INTO tasks (assignment_id, title, description, position, max_attempts, show_solution, min_keywords_required, problem_type, code_template, hint, hint1, hint2, hint3, stoff, expected_output, validation_mode, test_cases, solution_code, task_type, question_text, image_url, correct_answer, variable_overrides)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

if (!$stmt) {
    error_log('Prepare error: ' . $conn->error);
    jsonResponse(['ok' => false, 'error' => 'Database prepare error'], 500);
}

// Build type string programmatically to avoid issues
$types = 'i';      // assignment_id
$types .= 'ss';    // title, description
$types .= 'i';     // position
$types .= 'i';     // max_attempts
$types .= 'i';     // show_solution
$types .= 'i';     // min_keywords_required
$types .= 's';     // problem_type
$types .= 's';     // code_template
$types .= 's';     // hint
$types .= 'sss';   // hint1, hint2, hint3
$types .= 's';     // stoff
$types .= 's';     // expected_output
$types .= 's';     // validation_mode
$types .= 's';     // test_cases
$types .= 's';     // solution_code
$types .= 's';     // task_type
$types .= 's';     // question_text
$types .= 's';     // image_url
$types .= 's';     // correct_answer
$types .= 's';     // variable_overrides

error_log('Type string: ' . $types . ' (length: ' . strlen($types) . ')');

$bindResult = @$stmt->bind_param(
    $types,
    $assignmentId,
    $title,
    $description,
    $position,
    $maxAttempts,
    $showSolution,
    $minKeywordsRequired,
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
    $solutionCode,
    $taskType,
    $questionText,
    $imageUrl,
    $correctAnswer,
    $variableOverridesJson
);

if (!$bindResult) {
    error_log('Bind error: ' . $stmt->error . ' | Type string: ' . $types);
    jsonResponse(['ok' => false, 'error' => 'Database bind error: ' . $stmt->error], 500);
}

if ($stmt->execute()) {
    $taskId = $conn->insert_id;
    
    // Insert task options for single/multiple choice
    if (in_array($taskType, ['single_choice', 'multiple_choice']) && !empty($options)) {
        $optionStmt = $conn->prepare(
            'INSERT INTO task_options (task_id, option_text, image_url, is_correct, order_num) VALUES (?, ?, ?, ?, ?)'
        );
        
        if (!$optionStmt) {
            error_log('Option prepare error: ' . $conn->error);
        } else {
            foreach ($options as $index => $option) {
                $optionText = trim($option['text'] ?? '');
                $optionImage = trim($option['image_url'] ?? '') ?: null;
                $isCorrect = !empty($option['is_correct']) ? 1 : 0;
                $orderNum = $index + 1;
                
                $optionStmt->bind_param('issii', $taskId, $optionText, $optionImage, $isCorrect, $orderNum);
                if (!$optionStmt->execute()) {
                    error_log('Option insert error: ' . $optionStmt->error);
                }
            }
        }
    }

    jsonResponse([
        'ok' => true,
        'id' => $taskId,
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
    error_log('Execute error: ' . $stmt->error);
    jsonResponse(['ok' => false, 'error' => 'Failed to create task: ' . $stmt->error], 500);
}
