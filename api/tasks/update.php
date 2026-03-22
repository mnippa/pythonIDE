<?php
/**
 * Update Task API (Admin only)
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

$taskId = isset($input['id']) ? (int)$input['id'] : null;
if (!$taskId) {
    jsonResponse(['ok' => false, 'error' => 'Task ID required'], 400);
}

$existingTask = requireAdminOwnedTask($conn, $taskId, $user);
$existingTaskType = $existingTask['task_type'] ?? null;
$existingCodeTemplate = $existingTask['code_template'] ?? '';
$existingOverrides = $existingTask['variable_overrides'] ?? null;
$existingFolderstructure = (int)($existingTask['folderstructure'] ?? 0);
$shouldCreateFolder = false;

$allowedTypes = [
    'code_completion',
    'code_fix',
    'multiple_choice',
    'essay'
];

$problemTypeMap = [
    'code' => 'code_completion',
    'code_ui' => 'code_completion',
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

if (isset($input['show_solution_code'])) {
    $showSolutionCode = (int)$input['show_solution_code'];
    $updates[] = 'show_solution_code = ?';
    $params[] = $showSolutionCode;
    $types .= 'i';
}

if (isset($input['folderstructure'])) {
    $folderstructure = (int)(bool)$input['folderstructure'];
    $updates[] = 'folderstructure = ?';
    $params[] = $folderstructure;
    $types .= 'i';
    
    // Check if we need to create the folder (transitioning from 0 to 1)
    if ($folderstructure == 1 && $existingFolderstructure == 0) {
        $shouldCreateFolder = true;
    }
}

if (isset($input['allowDownload'])) {
    $allowDownload = (int)(bool)$input['allowDownload'];
    $updates[] = 'allowDownload = ?';
    $params[] = $allowDownload;
    $types .= 'i';
}

if (isset($input['allowCodeUiWebEdit'])) {
    $allowCodeUiWebEdit = (int)(bool)$input['allowCodeUiWebEdit'];
    $updates[] = 'allow_code_ui_web_edit = ?';
    $params[] = $allowCodeUiWebEdit;
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
    $allowedTaskTypes = ['code', 'code_ui', 'single_choice', 'multiple_choice', 'free_text', 'code_reading', 'code_random_complex'];
    if (!in_array($taskType, $allowedTaskTypes, true)) {
        jsonResponse(['ok' => false, 'error' => 'Invalid task_type'], 400);
    }
    $updates[] = 'task_type = ?';
    $params[] = $taskType;
    $types .= 's';

    if ($taskType === 'code_ui') {
        if (!isset($input['folderstructure'])) {
            $updates[] = 'folderstructure = ?';
            $params[] = 1;
            $types .= 'i';
        }
        if ($existingFolderstructure == 0) {
            $shouldCreateFolder = true;
        }
        if (!array_key_exists('code_template', $input) || trim((string)($input['code_template'] ?? '')) === '') {
            $updates[] = 'code_template = ?';
            $params[] = getCodeUiDefaultCodeTemplate();
            $types .= 's';
        }
    }
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
        $overridesStr = is_string($overridesTrimmed) ? $overridesTrimmed : json_encode($overridesTrimmed);
        if (strpos($overridesStr, '<random>') === false) {
            jsonResponse(['ok' => false, 'error' => 'variable_overrides not allowed for code_random_complex (use <random> markers in inputs)'], 400);
        }
    }
    $templateString = is_string($templateValue) ? $templateValue : '';
    if (trim($templateString) === '') {
        jsonResponse(['ok' => false, 'error' => 'code_template required for code_random_complex'], 400);
    }
    $hasValuesDict = preg_match('/\bvalues\b/', $templateString);
    $hasPlaceholders = preg_match('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', $templateString);
    if (!$hasValuesDict && !$hasPlaceholders) {
        jsonResponse(['ok' => false, 'error' => 'code_template must use either values dict or {placeholder} syntax for code_random_complex'], 400);
    }
}

if ($effectiveTaskType === 'code_reading' && !$hasOverrides) {
    jsonResponse(['ok' => false, 'error' => 'variable_overrides required for code_reading'], 400);
}

if (array_key_exists('task_text', $input)) {
    $updates[] = 'task_text = ?';
    $params[] = trim($input['task_text']);
    $types .= 's';
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

if (array_key_exists('randomizer_code', $input)) {
    $updates[] = 'randomizer_code = ?';
    $params[] = $input['randomizer_code'] ?? null;
    $types .= 's';
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
    // Create folder structure if needed (transitioning from no folder to folder)
    if ($shouldCreateFolder) {
        $folderPath = __DIR__ . '/../../storage/tasks/folders/task_' . $taskId;
        if (!file_exists($folderPath)) {
            if (!mkdir($folderPath, 0755, true)) {
                error_log('Failed to create folder structure for task ' . $taskId);
            }
        }
    }

    if ($effectiveTaskType === 'code_ui') {
        ensureCodeUiScaffold($taskId, false);
    }
    
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
