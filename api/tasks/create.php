<?php
/**
 * Create Task API (Admin only)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

$user = requireAdmin();
$conn = getDbConnection();

function getCodeUiTemplateVersion(): string {
    return '1.1.0';
}

function getCodeUiDefaultCodeTemplate(): string {
    return "import idegui as ui\n\nui.title('Code UI')\nui.text('Willkommen! Passe index.html und deinen Python-Code an.')\n";
}

function buildCodeUiIndexTemplate(string $version): string {
    return <<<HTML
<!doctype html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <!-- CODE_UI_TEMPLATE_VERSION: {$version} -->
  <title>Code UI Container</title>
  <link rel="stylesheet" href="style.css" />
</head>
<body>
  <div class="code-ui-wrapper">
    <h3>Code UI</h3>
    <p>Diese Datei darf von Admins und Schülern bearbeitet werden.</p>
    <div id="idegui-root" data-idegui-root="true"></div>
    <div id="idegui-output" data-idegui-output="true"></div>
  </div>
</body>
</html>
HTML;
}

function buildCodeUiStyleTemplate(string $version): string {
    return <<<CSS
/* CODE_UI_TEMPLATE_VERSION: {$version} */
.code-ui-wrapper {
    font-family: system-ui, sans-serif;
    margin: 0;
    padding: 16px;
}

#idegui-root {
    min-height: 180px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    padding: 12px;
}

#idegui-output {
    margin-top: 12px;
    font-size: 14px;
    color: #374151;
}
CSS;
}

function buildCodeUiIdeguiTemplate(string $version): string {
    return <<<PY
# CODE_UI_TEMPLATE_VERSION: {$version}
# Referenzdatei für die idegui-Struktur.
# Diese Datei zeigt die erwarteten API-Ideen, die Laufzeit kann davon abweichen.

def title(text):
    return {"type": "title", "text": text}

def text(value):
    return {"type": "text", "text": value}
PY;
}

function ensureCodeUiScaffold(int $taskId, bool $overwrite = false): void {
    $folderPath = __DIR__ . '/../../storage/tasks/folders/task_' . $taskId;
    if (!is_dir($folderPath)) {
        mkdir($folderPath, 0755, true);
    }

    $version = getCodeUiTemplateVersion();
    $files = [
        'index.html' => buildCodeUiIndexTemplate($version),
        'style.css' => buildCodeUiStyleTemplate($version),
        'idegui.py' => buildCodeUiIdeguiTemplate($version),
        'code_ui.template.json' => json_encode([
            'type' => 'code_ui',
            'template_version' => $version,
            'generated_at' => date('c')
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    ];

    foreach ($files as $fileName => $content) {
        $target = $folderPath . '/' . $fileName;
        if ($overwrite || !file_exists($target)) {
            file_put_contents($target, $content);
        }
    }
}

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
$showSolutionCode = isset($input['show_solution_code']) ? (int)$input['show_solution_code'] : 0;
$minKeywordsRequired = isset($input['min_keywords_required']) ? (int)$input['min_keywords_required'] : null;
$problemType = $input['problem_type'] ?? 'code_completion';
$codeTemplate = $input['code_template'] ?? null;
$hint1 = $input['hint1'] ?? null;
$hint2 = $input['hint2'] ?? null;
$hint3 = $input['hint3'] ?? null;
$stoff = $input['stoff'] ?? null;
$expectedOutput = $input['expected_output'] ?? null;
$testCases = $input['test_cases'] ?? null;
$solutionCode = $input['solution_code'] ?? null;
$randomizerCode = $input['randomizer_code'] ?? null;

// Debug logging for test_cases
error_log('=== TEST CASES DEBUG ===');
error_log('test_cases received type: ' . gettype($testCases));
error_log('test_cases value: ' . print_r($testCases, true));
error_log('=======================');

// New fields for quiz-style tasks
$taskType = $input['task_type'] ?? 'code';
$taskText = trim($input['task_text'] ?? '');
$questionText = trim($input['question_text'] ?? '');
// If task_text not provided, fallback to question_text for backward compatibility
if (empty($taskText)) {
    $taskText = $questionText;
}
$imageUrl = trim($input['image_url'] ?? '') ?: null;
$correctAnswer = trim($input['correct_answer'] ?? '') ?: null;
$variableOverrides = $input['variable_overrides'] ?? null;
$options = $input['options'] ?? [];
$folderstructure = isset($input['folderstructure']) ? (int)(bool)$input['folderstructure'] : 0;
$allowDownload = isset($input['allowDownload']) ? (int)(bool)$input['allowDownload'] : 0;
$allowCodeUiWebEdit = isset($input['allowCodeUiWebEdit']) ? (int)(bool)$input['allowCodeUiWebEdit'] : 1;
$taskDifficulty = strtolower(trim((string)($input['task_difficulty'] ?? 'medium')));

$problemTypeMap = [
    'code' => 'code_completion',
    'code_ui' => 'code_completion',
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

requireAdminOwnedAssignment($conn, $assignmentId, $user);

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
$allowedTaskTypes = ['code', 'code_ui', 'single_choice', 'multiple_choice', 'free_text', 'code_reading', 'code_random_complex'];
if (!in_array($taskType, $allowedTaskTypes, true)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid task_type'], 400);
}

$allowedTaskDifficulties = ['basic', 'medium', 'hard'];
if (!in_array($taskDifficulty, $allowedTaskDifficulties, true)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid task_difficulty'], 400);
}

if ($taskType === 'code_ui') {
    $folderstructure = 1;
    if (!is_string($codeTemplate) || trim($codeTemplate) === '') {
        $codeTemplate = getCodeUiDefaultCodeTemplate();
    }
}

// Validate quiz tasks have task_text
if (in_array($taskType, ['single_choice', 'multiple_choice', 'free_text', 'code_random_complex']) && empty($taskText)) {
    jsonResponse(['ok' => false, 'error' => 'task_text required for ' . $taskType], 400);
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

if ($taskType === 'code_reading' && !$hasVariableOverrides) {
    jsonResponse(['ok' => false, 'error' => 'variable_overrides required for code_reading'], 400);
}

// For code_random_complex: variable_overrides is allowed IF it contains <random> markers (iteration handling)
// Otherwise it should be null
if ($taskType === 'code_random_complex' && $hasVariableOverrides) {
    // Check if it contains <random> marker - if yes, it's for iterations and is allowed
    $overridesStr = is_string($variableOverridesTrimmed) ? $variableOverridesTrimmed : json_encode($variableOverridesTrimmed);
    if (strpos($overridesStr, '<random>') === false) {
        // No <random> marker - this is legacy or incorrect usage
        jsonResponse(['ok' => false, 'error' => 'variable_overrides not allowed for code_random_complex (use randomizer_code instead)'], 400);
    }
}

if ($taskType === 'code_random_complex') {
    $templateValue = is_string($codeTemplate) ? $codeTemplate : '';
    // code_template can either use old "values" dict or new Placeholder {varname} syntax
    // At least one must be present
    $hasValuesDict = preg_match('/\bvalues\b/', $templateValue);
    $hasPlaceholders = preg_match('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', $templateValue);
    
    if (!$hasValuesDict && !$hasPlaceholders) {
        jsonResponse(['ok' => false, 'error' => 'code_template must use either values dict or {placeholder} syntax for code_random_complex'], 400);
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
$testCases = is_string($testCases) ? $testCases : '';
$solutionCode = is_string($solutionCode) ? $solutionCode : '';
$randomizerCode = is_string($randomizerCode) ? $randomizerCode : '';

$stmt = $conn->prepare(
    'INSERT INTO tasks (assignment_id, title, description, position, max_attempts, iterations_count, show_solution, show_solution_code, min_keywords_required, problem_type, code_template, hint1, hint2, hint3, stoff, expected_output, test_cases, solution_code, task_type, task_text, question_text, image_url, correct_answer, variable_overrides, randomizer_code, folderstructure, allowDownload, allow_code_ui_web_edit, task_difficulty)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
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
$types .= 'i';     // show_solution_code
$types .= 'i';     // min_keywords_required
$types .= 's';     // problem_type
$types .= 's';     // code_template
$types .= 'sss';   // hint1, hint2, hint3
$types .= 's';     // stoff
$types .= 's';     // expected_output
$types .= 's';     // test_cases
$types .= 's';     // solution_code
$types .= 's';     // task_type
$types .= 's';     // task_text
$types .= 's';     // question_text
$types .= 's';     // image_url
$types .= 's';     // correct_answer
$types .= 's';     // variable_overrides
$types .= 's';     // randomizer_code
$types .= 'i';     // folderstructure
$types .= 'i';     // allowDownload
$types .= 'i';     // allow_code_ui_web_edit
$types .= 's';     // task_difficulty

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
    $showSolutionCode,
    $minKeywordsRequired,
    $problemType,
    $codeTemplate,
    $hint1,
    $hint2,
    $hint3,
    $stoff,
    $expectedOutput,
    $testCases,
    $solutionCode,
    $taskType,
    $taskText,
    $questionText,
    $imageUrl,
    $correctAnswer,
    $variableOverridesJson,
    $randomizerCode,
    $folderstructure,
    $allowDownload,
    $allowCodeUiWebEdit,
    $taskDifficulty
);

if (!$bindResult) {
    error_log('Bind error: ' . $stmt->error . ' | Type string: ' . $types);
    jsonResponse(['ok' => false, 'error' => 'Database bind error: ' . $stmt->error], 500);
}

if ($stmt->execute()) {
    $taskId = $conn->insert_id;
    
    // Create folder structure if folderstructure flag is set
    if ($folderstructure == 1) {
        $folderPath = __DIR__ . '/../../storage/tasks/folders/task_' . $taskId;
        if (!file_exists($folderPath)) {
            if (!mkdir($folderPath, 0755, true)) {
                error_log('Failed to create folder structure for task ' . $taskId);
            }
        }

        if ($taskType === 'code_ui') {
            ensureCodeUiScaffold($taskId, false);
        }
    }
    
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
            'test_cases' => $testCases,
            'solution_code' => $solutionCode,
            'task_difficulty' => $taskDifficulty,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ], 201);
} else {
    error_log('Execute error: ' . $stmt->error);
    jsonResponse(['ok' => false, 'error' => 'Failed to create task: ' . $stmt->error], 500);
}
