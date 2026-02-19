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
$maxIterationsInput = isset($input['max_iterations']) ? (int)$input['max_iterations'] : null;  // API param is max_iterations, but DB column is iterations_count
$showSolution = isset($input['show_solution']) ? (int)$input['show_solution'] : 1;
$showGeneratorCode = isset($input['show_generator_code']) ? (int)$input['show_generator_code'] : 0;
$minKeywordsRequired = isset($input['min_keywords_required']) ? (int)$input['min_keywords_required'] : null;
$problemType = $input['problem_type'] ?? 'code_completion';
$codeTemplate = $input['code_template'] ?? null;
$hint1 = $input['hint1'] ?? null;
$hint2 = $input['hint2'] ?? null;
$hint3 = $input['hint3'] ?? null;
$stoff = $input['stoff'] ?? null;
$expectedOutput = $input['expected_output'] ?? null;
$validationMode = $input['validation_mode'] ?? null;
$testCases = $input['test_cases'] ?? null;
$solutionCode = $input['solution_code'] ?? null;

// Debug logging for test_cases
error_log('=== TEST CASES DEBUG ===');
error_log('test_cases received type: ' . gettype($testCases));
error_log('test_cases value: ' . print_r($testCases, true));
error_log('=======================');

// New fields for quiz-style tasks
$taskType = $input['task_type'] ?? 'code';
$questionText = trim($input['question_text'] ?? '');
$imageUrl = trim($input['image_url'] ?? '') ?: null;
$correctAnswer = trim($input['correct_answer'] ?? '') ?: null;
$variableOverrides = $input['variable_overrides'] ?? null;
$options = $input['options'] ?? [];

$problemTypeMap = [
    'code' => 'code_completion',
    'code_reading' => 'code_completion',
    'code_random_complex' => 'code_completion',
    'single_choice' => 'multiple_choice',
    'multiple_choice' => 'multiple_choice',
    'free_text' => 'essay'
];

if (isset($problemTypeMap[$problemType])) {
    $problemType = $problemTypeMap[$problemType];
}

if (!$assignmentId) {
    jsonResponse(['ok' => false, 'error' => 'Assignment ID required'], 400);
}

if ($title === '') {
    jsonResponse(['ok' => false, 'error' => 'Title is required'], 400);
}

$allowedTypes = [
    'code_completion',
    'code_fix',
    'multiple_choice',
    'essay'
];
if (!in_array($problemType, $allowedTypes, true)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid problem_type'], 400);
}

// Validate task_type
$allowedTaskTypes = ['code', 'single_choice', 'multiple_choice', 'free_text', 'code_reading', 'code_random_complex'];
if (!in_array($taskType, $allowedTaskTypes, true)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid task_type'], 400);
}

// Validate quiz tasks have question_text
if (in_array($taskType, ['single_choice', 'multiple_choice', 'free_text', 'code_random_complex']) && empty($questionText)) {
    jsonResponse(['ok' => false, 'error' => 'question_text required for ' . $taskType], 400);
}

if ($taskType === 'code_random_complex' && empty($codeTemplate)) {
    jsonResponse(['ok' => false, 'error' => 'code_template required for ' . $taskType], 400);
}

if ($taskType === 'code_random_complex' && empty($solutionCode)) {
    jsonResponse(['ok' => false, 'error' => 'solution_code required for ' . $taskType], 400);
}

$variableOverridesRaw = $variableOverrides;
$variableOverridesTrimmed = is_string($variableOverridesRaw) ? trim($variableOverridesRaw) : $variableOverridesRaw;
$hasVariableOverrides = $variableOverridesTrimmed !== null
    && $variableOverridesTrimmed !== ''
    && $variableOverridesTrimmed !== '[]'
    && $variableOverridesTrimmed !== '{}';

if ($taskType === 'code_random_complex' && $hasVariableOverrides) {
    jsonResponse(['ok' => false, 'error' => 'variable_overrides not allowed for code_random_complex'], 400);
}

if ($taskType === 'code_reading' && !$hasVariableOverrides) {
    jsonResponse(['ok' => false, 'error' => 'variable_overrides required for code_reading'], 400);
}

if ($taskType === 'code_random_complex') {
    $templateValue = is_string($codeTemplate) ? $codeTemplate : '';
    if (!preg_match('/\bvalues\b/', $templateValue)) {
        jsonResponse(['ok' => false, 'error' => 'code_template must set values dict for code_random_complex'], 400);
    }
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
$variableOverridesParsed = null;
if ($variableOverrides) {
    if (is_string($variableOverrides)) {
        $variableOverridesJson = $variableOverrides;
        $decodedOverrides = json_decode($variableOverrides, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $variableOverridesParsed = $decodedOverrides;
        }
    } else {
        $variableOverridesParsed = $variableOverrides;
        $variableOverridesJson = json_encode($variableOverrides);
    }
}

$maxIterations = ($maxIterationsInput && $maxIterationsInput > 0) ? $maxIterationsInput : 1;
if ($taskType === 'code_reading') {
    $maxIterations = 1;
    if (is_array($variableOverridesParsed)) {
        $maxIterations = max(1, count($variableOverridesParsed));
    }
}
if ($taskType === 'code_random_complex' && $maxIterations < 1) {
    $maxIterations = 3;
}
if ($taskType === 'code_random_complex' && !$maxIterationsInput) {
    $maxIterations = 3;
}

// Ensure all string values are strings (not null or array)
$codeTemplate = is_string($codeTemplate) ? $codeTemplate : '';
$expectedOutput = is_string($expectedOutput) ? $expectedOutput : '';
$validationMode = is_string($validationMode) ? $validationMode : '';
$testCases = is_string($testCases) ? $testCases : '';
$solutionCode = is_string($solutionCode) ? $solutionCode : '';

$stmt = $conn->prepare(
    'INSERT INTO tasks (assignment_id, title, description, position, max_attempts, iterations_count, show_solution, show_generator_code, min_keywords_required, problem_type, code_template, hint1, hint2, hint3, stoff, expected_output, validation_mode, test_cases, solution_code, task_type, question_text, image_url, correct_answer, variable_overrides)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
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
$types .= 'i';     // iterations_count
$types .= 'i';     // show_solution
$types .= 'i';     // show_generator_code
$types .= 'i';     // min_keywords_required
$types .= 's';     // problem_type
$types .= 's';     // code_template
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
    $maxIterations,
    $showSolution,
    $showGeneratorCode,
    $minKeywordsRequired,
    $problemType,
    $codeTemplate,
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
