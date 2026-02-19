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

$stmt = $conn->prepare('SELECT id, task_type, code_template, variable_overrides FROM tasks WHERE id = ?');
$stmt->bind_param('i', $taskId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    jsonResponse(['ok' => false, 'error' => 'Task not found'], 404);
}
$existingTask = $result->fetch_assoc();
$existingTaskType = $existingTask['task_type'] ?? null;
$existingCodeTemplate = $existingTask['code_template'] ?? '';
$existingOverrides = $existingTask['variable_overrides'] ?? null;

$allowedTypes = [
    'code_completion',
    'code_fix',
    'multiple_choice',
    'essay'
];

$problemTypeMap = [
    'code' => 'code_completion',
    'code_reading' => 'code_completion',
    'code_random_complex' => 'code_completion',
    'single_choice' => 'multiple_choice',
    'multiple_choice' => 'multiple_choice',
    'free_text' => 'essay'
];

$updates = [];
$params = [];
$types = '';
$taskTypeInput = $input['task_type'] ?? null;

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

if (isset($input['max_attempts'])) {
    $maxAttempts = (int)$input['max_attempts'];
    if ($maxAttempts < 1) {
        jsonResponse(['ok' => false, 'error' => 'Invalid max_attempts'], 400);
    }
    $updates[] = 'max_attempts = ?';
    $params[] = $maxAttempts;
    $types .= 'i';
}

if (isset($input['max_iterations'])) {
    $maxIterations = (int)$input['max_iterations'];
    if ($maxIterations < 1) {
        jsonResponse(['ok' => false, 'error' => 'Invalid max_iterations'], 400);
    }
    $updates[] = 'iterations_count = ?';
    $params[] = $maxIterations;
    $types .= 'i';
}

if (isset($input['show_solution'])) {
    $showSolution = (int)$input['show_solution'];
    $updates[] = 'show_solution = ?';
    $params[] = $showSolution;
    $types .= 'i';
}

if (isset($input['show_generator_code'])) {
    $showGeneratorCode = (int)$input['show_generator_code'];
    $updates[] = 'show_generator_code = ?';
    $params[] = $showGeneratorCode;
    $types .= 'i';
}

if (array_key_exists('min_keywords_required', $input)) {
    $minKeywords = $input['min_keywords_required'] !== null && $input['min_keywords_required'] !== '' ? (int)$input['min_keywords_required'] : null;
    $updates[] = 'min_keywords_required = ?';
    $params[] = $minKeywords;
    $types .= 'i';
}

if (isset($input['problem_type'])) {
    $problemType = $input['problem_type'];
    if (isset($problemTypeMap[$problemType])) {
        $problemType = $problemTypeMap[$problemType];
    }
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

// New fields for quiz-style tasks
if (isset($input['task_type'])) {
    $taskType = $input['task_type'];
    $allowedTaskTypes = ['code', 'single_choice', 'multiple_choice', 'free_text', 'code_reading', 'code_random_complex'];
    if (!in_array($taskType, $allowedTaskTypes, true)) {
        jsonResponse(['ok' => false, 'error' => 'Invalid task_type'], 400);
    }
    $updates[] = 'task_type = ?';
    $params[] = $taskType;
    $types .= 's';
}

$effectiveTaskType = $taskTypeInput ?? $existingTaskType;
$templateValue = array_key_exists('code_template', $input) ? ($input['code_template'] ?? '') : ($existingCodeTemplate ?? '');
$overridesValue = array_key_exists('variable_overrides', $input) ? ($input['variable_overrides'] ?? null) : $existingOverrides;
$overridesTrimmed = is_string($overridesValue) ? trim($overridesValue) : $overridesValue;
$hasOverrides = $overridesTrimmed !== null
    && $overridesTrimmed !== ''
    && $overridesTrimmed !== '[]'
    && $overridesTrimmed !== '{}';

if ($effectiveTaskType === 'code_random_complex') {
    if ($hasOverrides) {
        jsonResponse(['ok' => false, 'error' => 'variable_overrides not allowed for code_random_complex'], 400);
    }
    $templateString = is_string($templateValue) ? $templateValue : '';
    if (trim($templateString) === '') {
        jsonResponse(['ok' => false, 'error' => 'code_template required for code_random_complex'], 400);
    }
    if (!preg_match('/\bvalues\b/', $templateString)) {
        jsonResponse(['ok' => false, 'error' => 'code_template must set values dict for code_random_complex'], 400);
    }
}

if ($effectiveTaskType === 'code_reading' && !$hasOverrides) {
    jsonResponse(['ok' => false, 'error' => 'variable_overrides required for code_reading'], 400);
}

if (array_key_exists('question_text', $input)) {
    $updates[] = 'question_text = ?';
    $params[] = trim($input['question_text']);
    $types .= 's';
}

if (array_key_exists('image_url', $input)) {
    $updates[] = 'image_url = ?';
    $params[] = trim($input['image_url']) ?: null;
    $types .= 's';
}

if (array_key_exists('correct_answer', $input)) {
    $updates[] = 'correct_answer = ?';
    $params[] = trim($input['correct_answer']) ?: null;
    $types .= 's';
}

if (array_key_exists('variable_overrides', $input)) {
    $updates[] = 'variable_overrides = ?';
    $variableOverridesValue = $input['variable_overrides'];
    if ($variableOverridesValue === null || $variableOverridesValue === '') {
        $variableOverridesJson = null;
    } elseif (is_string($variableOverridesValue)) {
        $variableOverridesJson = $variableOverridesValue;
    } else {
        $variableOverridesJson = json_encode($variableOverridesValue);
    }
    $params[] = $variableOverridesJson;
    $types .= 's';

        if ($taskTypeInput === 'code_reading') {
            if (is_string($variableOverridesValue)) {
                $decodedOverrides = json_decode($variableOverridesValue, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedOverrides)) {
                    $maxIterationsFromOverrides = max(1, count($decodedOverrides));
                    $updates[] = 'iterations_count = ?';
                    $params[] = $maxIterationsFromOverrides;
                    $types .= 'i';
                }
            } elseif (is_array($variableOverridesValue)) {
                $maxIterationsFromOverrides = max(1, count($variableOverridesValue));
                $updates[] = 'iterations_count = ?';
                $params[] = $maxIterationsFromOverrides;
                $types .= 'i';
            }
        }
}

if (empty($updates)) {
    jsonResponse(['ok' => false, 'error' => 'No fields to update'], 400);
}

$params[] = $taskId;
$types .= 'i';

$sql = 'UPDATE tasks SET ' . implode(', ', $updates) . ' WHERE id = ?';
$stmt = $conn->prepare($sql);
if (!$stmt) {
    jsonResponse(['ok' => false, 'error' => 'Database prepare error: ' . $conn->error], 500);
}

$bindResult = $stmt->bind_param($types, ...$params);
if (!$bindResult) {
    jsonResponse(['ok' => false, 'error' => 'Database bind error: ' . $stmt->error], 500);
}

if ($stmt->execute()) {
    // Update task options if provided (for single/multiple choice)
    if (isset($input['options'])) {
        // Delete existing options
        $deleteStmt = $conn->prepare('DELETE FROM task_options WHERE task_id = ?');
        $deleteStmt->bind_param('i', $taskId);
        $deleteStmt->execute();
        
        // Insert new options
        if (!empty($input['options'])) {
            $optionStmt = $conn->prepare(
                'INSERT INTO task_options (task_id, option_text, image_url, is_correct, order_num) VALUES (?, ?, ?, ?, ?)'
            );
            
            foreach ($input['options'] as $index => $option) {
                $optionText = trim($option['text'] ?? '');
                $optionImage = trim($option['image_url'] ?? '') ?: null;
                $isCorrect = !empty($option['is_correct']) ? 1 : 0;
                $orderNum = $index + 1;
                
                $optionStmt->bind_param('issii', $taskId, $optionText, $optionImage, $isCorrect, $orderNum);
                $optionStmt->execute();
            }
        }
    }
    
    jsonResponse(['ok' => true, 'message' => 'Task updated']);
} else {
    jsonResponse(['ok' => false, 'error' => 'Failed to update task: ' . $stmt->error], 500);
}
