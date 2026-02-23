<?php
/**
 * Submit Quiz Answer (Single-Choice, Multiple-Choice, Free-Text, Code-Reading)
 * POST /api/user_tasks/submit_quiz.php
 */

session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$conn = getDbConnection();

function compareAnswers($userAnswer, $expected) {
    $userAnswer = trim((string)$userAnswer);

    if (is_array($expected) || is_object($expected)) {
        $expected = json_encode($expected);
    }
    $expected = trim((string)$expected);

    if ($userAnswer === '' || $expected === '') {
        return false;
    }

    if (is_numeric($userAnswer) && is_numeric($expected)) {
        $ua = (float)$userAnswer;
        $ex = (float)$expected;
        return abs($ua - $ex) < 1e-9;
    }

    return strtolower($userAnswer) === strtolower($expected);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

$taskId = isset($input['task_id']) ? (int)$input['task_id'] : null;
if (!$taskId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'task_id required']);
    exit;
}

// Get task details
$stmt = $conn->prepare('SELECT task_type, question_text, correct_answer, max_attempts, iterations_count, min_keywords_required, variable_overrides FROM tasks WHERE id = ?');
$stmt->bind_param('i', $taskId);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();

if (!$task) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Task not found']);
    exit;
}

$taskType = $task['task_type'];
$maxAttempts = isset($task['max_attempts']) && (int)$task['max_attempts'] > 0 ? (int)$task['max_attempts'] : 1;
$isIterative = in_array($taskType, ['code_reading', 'code_random_complex'], true);

$maxIterations = isset($task['iterations_count']) && (int)$task['iterations_count'] > 0 ? (int)$task['iterations_count'] : 3;
if ($taskType === 'code_reading') {
    $maxIterations = 1;
    if (!empty($task['variable_overrides'])) {
        $decodedOverrides = json_decode($task['variable_overrides'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedOverrides)) {
            $maxIterations = max(1, count($decodedOverrides));
        }
    }
}

// Current user task state (attempts/status)
$stmt = $conn->prepare('SELECT status, attempts, current_iteration FROM user_tasks WHERE user_id = ? AND task_id = ?');
$stmt->bind_param('ii', $userId, $taskId);
$stmt->execute();
$userTask = $stmt->get_result()->fetch_assoc();
$currentAttempts = $userTask ? (int)$userTask['attempts'] : 0;
$currentIteration = $userTask && isset($userTask['current_iteration']) ? (int)$userTask['current_iteration'] : 1;
$currentStatus = $userTask ? $userTask['status'] : null;

if ($currentStatus === 'passed') {
    echo json_encode([
        'ok' => true,
        'is_correct' => true,
        'status' => 'passed',
        'attempts' => $currentAttempts,
        'max_attempts' => $maxAttempts,
        'message' => 'Bereits bestanden',
        'current_iteration' => $isIterative ? $currentIteration : null,
        'max_iterations' => $isIterative ? $maxIterations : null,
        'reset_values' => false
    ]);
    exit;
}

if (in_array($taskType, ['single_choice', 'multiple_choice', 'free_text', 'code_reading', 'code_random_complex']) && $currentAttempts >= $maxAttempts) {
    echo json_encode([
        'ok' => false,
        'error' => 'Maximale Anzahl Versuche erreicht',
        'status' => 'failed',
        'attempts' => $currentAttempts,
        'max_attempts' => $maxAttempts
    ]);
    exit;
}
$isCorrect = false;
$message = '';
$variableValues = [];

// Validate and grade based on task type
if ($taskType === 'single_choice' || $taskType === 'multiple_choice') {
    $selectedOptions = $input['selected_options'] ?? [];
    if (empty($selectedOptions)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'No options selected']);
        exit;
    }
    
    // Get correct options
    $stmt = $conn->prepare('SELECT id FROM task_options WHERE task_id = ? AND is_correct = 1');
    $stmt->bind_param('i', $taskId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $correctOptionIds = [];
    while ($row = $result->fetch_assoc()) {
        $correctOptionIds[] = (int)$row['id'];
    }
    
    // Compare
    sort($selectedOptions);
    sort($correctOptionIds);
    $isCorrect = ($selectedOptions === $correctOptionIds);
    
    // Store selected options as JSON
    $selectedOptionsJson = json_encode($selectedOptions);
    
} elseif ($taskType === 'free_text') {
    $textAnswer = trim($input['text_answer'] ?? '');
    if ($textAnswer === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'No answer provided']);
        exit;
    }
    
    // Free-text uses test_cases array (same structure as OUTPUT tests)
    $testCases = [];
    if ($task['test_cases']) {
        $testCases = json_decode($task['test_cases'], true);
        if (!is_array($testCases)) {
            $testCases = [];
        }
    }
    
    // If no test_cases, check for legacy correct_answer field for backward compatibility
    if (empty($testCases) && $task['correct_answer']) {
        // Legacy keyword matching
        $keywords = explode(',', $task['correct_answer']);
        $keywords = array_map('trim', $keywords);
        $keywords = array_map('strtolower', $keywords);
        $keywords = array_filter($keywords);
        
        $textLower = strtolower($textAnswer);
        $foundKeywords = 0;
        foreach ($keywords as $keyword) {
            if ($keyword !== '' && strpos($textLower, $keyword) !== false) {
                $foundKeywords++;
            }
        }
        
        $totalKeywords = count($keywords);
        $minRequired = $task['min_keywords_required'] ?? $totalKeywords;
        $isCorrect = ($foundKeywords >= $minRequired && $totalKeywords > 0);
        $message = $isCorrect 
            ? "✓ Genügend Schlüsselwörter gefunden ($foundKeywords / $totalKeywords)"
            : "✗ Nicht genügend Schlüsselwörter ($foundKeywords / $totalKeywords)";
    } else {
        // New test_cases based validation (like OUTPUT tests)
        $isCorrect = false;
        $message = 'Keine Validierungsmuster definiert';
        $matchedPatterns = [];
        
        foreach ($testCases as $idx => $testCase) {
            $expectedType = $testCase['expected_type'] ?? 'text';
            $validationMode = $testCase['validation_mode'] ?? 'loose';
            $caseSensitive = $testCase['case_sensitive'] ?? false;
            
            // Get patterns array - expected is always an array of patterns
            $patterns = $testCase['expected'] ?? [];
            if (!is_array($patterns)) {
                $patterns = [$patterns]; // Convert single value to array if needed
            }
            
            // Check if ANY pattern in this test case matches
            $testCasePassed = false;
            foreach ($patterns as $pattern) {
                if (empty($pattern)) continue; // Skip empty patterns
                
                if ($expectedType === 'regex') {
                    // Regex pattern matching - case_sensitive is checked
                    try {
                        $flags = $caseSensitive ? '' : 'i'; // 'i' flag for case-insensitive
                        $regex = '/' . addcslashes($pattern, '/') . '/' . $flags;
                        if (preg_match($regex, $textAnswer)) {
                            $matchedPatterns[] = "Regex: {$pattern}";
                            $testCasePassed = true;
                            break; // Found a match in this test case, move to next
                        }
                    } catch (Exception $e) {
                        // Invalid regex, skip this pattern
                    }
                } else {
                    // Text pattern matching with validation_mode and case_sensitive
                    $matched = false;
                    $answer = $textAnswer;
                    $patternToMatch = $pattern;
                    
                    // Apply case sensitivity
                    if (!$caseSensitive) {
                        $answer = strtolower($answer);
                        $patternToMatch = strtolower($patternToMatch);
                    }
                    
                    switch ($validationMode) {
                        case 'strict':
                            // Exact match (but whitespace inside can differ based on loose concept)
                            $matched = (trim($answer) === trim($patternToMatch));
                            break;
                        case 'contains':
                            // Substring match
                            $matched = (strpos($answer, $patternToMatch) !== false);
                            break;
                        case 'loose':
                        default:
                            // Normalize whitespace and then compare
                            $normalizeWs = function($str) {
                                return trim(preg_replace('/\s+/', ' ', (string)$str));
                            };
                            $matched = ($normalizeWs($answer) === $normalizeWs($patternToMatch));
                            break;
                    }
                    
                    if ($matched) {
                        $matchedPatterns[] = "[{$validationMode}" . ($caseSensitive ? ":case-sensitive" : "") . "] Match";
                        $testCasePassed = true;
                        break; // Found a match in this test case, move to next
                    }
                }
            }
        }
        
        // ODER-Logik: Wenn mindestens einem Pattern matched, ist bestanden
        $isCorrect = count($matchedPatterns) > 0;
        if ($isCorrect) {
            $message = '✓ Antwort stimmt mit Muster überein (' . implode(', ', array_slice($matchedPatterns, 0, 2)) . ')';
        } else {
            $message = '✗ Antwort stimmt nicht mit nötige Mustern überein';
        }
    }
    
} elseif ($taskType === 'code_reading') {
    $textAnswer = trim($input['text_answer'] ?? '');
    $variableValues = $input['variable_values'] ?? [];
    $computedValue = $input['computed_value'] ?? null;
    
    if ($textAnswer === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'No answer provided']);
        exit;
    }
    
    // Get task code template and variable overrides
    $stmt = $conn->prepare('SELECT code_template, variable_overrides, correct_answer FROM tasks WHERE id = ?');
    $stmt->bind_param('i', $taskId);
    $stmt->execute();
    $taskData = $stmt->get_result()->fetch_assoc();
    
    $codeTemplate = $taskData['code_template'];
    $varName = $taskData['correct_answer']; // Variable name to check
    
    // Evaluate using computed value from Pyodide (client-side)
    if ($computedValue === null) {
        $isCorrect = false;
        $message = 'Keine Auswertung verfuegbar';
    } else {
        $isCorrect = compareAnswers($textAnswer, $computedValue);
        $message = $isCorrect ? 'Richtig' : 'Falsch';
    }
} elseif ($taskType === 'code_random_complex') {
    $textAnswer = trim($input['text_answer'] ?? '');
    $computedValue = $input['computed_value'] ?? null;
    $variableValues = $input['variable_values'] ?? [];

    if ($textAnswer === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'No answer provided']);
        exit;
    }

    if ($computedValue === null) {
        $isCorrect = false;
        $message = 'Keine Auswertung verfuegbar';
    } else {
        $isCorrect = compareAnswers($textAnswer, $computedValue);
        $message = $isCorrect ? 'Richtig' : 'Falsch';
    }
    
} else {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid task type for quiz submission']);
    exit;
}

// Determine status based on attempts and iteration rules
$newAttempts = $currentAttempts + (($isIterative && $isCorrect) ? 0 : 1);
$status = $isCorrect ? 'passed' : 'failed';
$nextIteration = $currentIteration;
$resetValues = false;

if ($isIterative) {
    if ($isCorrect) {
        if ($currentIteration >= $maxIterations) {
            $status = 'passed';
            $nextIteration = $maxIterations;
        } else {
            $status = 'in-progress';
            $nextIteration = $currentIteration + 1;
            $resetValues = in_array($taskType, ['code_reading', 'code_random_complex']);
            $message = $message ?: 'Iteration abgeschlossen. Neue Werte werden geladen.';
        }
    } elseif ($newAttempts >= $maxAttempts) {
        $status = 'failed';
    } else {
        $status = 'in-progress';
    }
} elseif (in_array($taskType, ['single_choice', 'multiple_choice', 'free_text'])) {
    if ($isCorrect) {
        $status = 'passed';
    } elseif ($newAttempts >= $maxAttempts) {
        $status = 'failed';
    } else {
        $status = 'in-progress';
    }
}

// Create or update user_tasks entry
$stmt = $conn->prepare(
        'INSERT INTO user_tasks (user_id, task_id, status, selected_options, text_answer, variable_values, attempts, current_iteration)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE 
             status = VALUES(status),
             selected_options = VALUES(selected_options),
             text_answer = VALUES(text_answer),
             variable_values = VALUES(variable_values),
             attempts = VALUES(attempts),
             current_iteration = VALUES(current_iteration)'
);

$selectedOptionsJson = isset($selectedOptionsJson) ? $selectedOptionsJson : json_encode($input['selected_options'] ?? []);
$textAnswerValue = isset($input['text_answer']) ? $input['text_answer'] : null;
$variableValuesJson = !empty($variableValues) ? json_encode($variableValues) : null;
if ($resetValues) {
    $variableValuesJson = null;
}

$stmt->bind_param(
    'iissssii',
    $userId,
    $taskId,
    $status,
    $selectedOptionsJson,
    $textAnswerValue,
    $variableValuesJson,
    $newAttempts,
    $nextIteration
);

if ($stmt->execute()) {
    $response = [
        'ok' => true,
        'is_correct' => $isCorrect,
        'status' => $status,
        'attempts' => $newAttempts,
        'max_attempts' => $maxAttempts,
        'message' => $message,
        'current_iteration' => $isIterative ? $nextIteration : null,
        'max_iterations' => $isIterative ? $maxIterations : null,
        'reset_values' => $resetValues
    ];
    
    // For choice tasks, include options with is_correct after submission
    if (in_array($taskType, ['single_choice', 'multiple_choice'])) {
        $optionsStmt = $conn->prepare(
            'SELECT id, option_text, image_url, is_correct, order_num FROM task_options WHERE task_id = ? ORDER BY order_num ASC'
        );
        $optionsStmt->bind_param('i', $taskId);
        $optionsStmt->execute();
        $optionsResult = $optionsStmt->get_result();
        
        $options = [];
        while ($optionRow = $optionsResult->fetch_assoc()) {
            $options[] = [
                'id' => (int)$optionRow['id'],
                'text' => $optionRow['option_text'],
                'image_url' => $optionRow['image_url'],
                'is_correct' => (bool)$optionRow['is_correct'],
                'order_num' => (int)$optionRow['order_num']
            ];
        }
        $optionsStmt->close();
        
        $response['options'] = $options;
    }
    
    echo json_encode($response);
} else {
    http_response_code(500);
    error_log("submit_quiz.php SQL error: " . $stmt->error);
    echo json_encode(['ok' => false, 'error' => 'Failed to save answer: ' . $stmt->error]);
}
