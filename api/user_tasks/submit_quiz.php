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

function normalizeAnswerText($value, bool $caseSensitive = false) {
    if (is_array($value) || is_object($value)) {
        $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $value = str_replace(["\r\n", "\r"], "\n", (string)$value);
    $value = str_replace(['"', "'", '“', '”', '„', '‚', '‘', '’', '`', '´'], '', $value);
    $value = preg_replace('/\s*,\s*/u', ', ', $value);
    $value = preg_replace('/\s+/u', ' ', $value);
    $value = trim($value);

    if (!$caseSensitive) {
        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    return $value;
}

function compareAnswers($userAnswer, $expected) {
    $userAnswerRaw = trim((string)$userAnswer);

    if (is_array($expected) || is_object($expected)) {
        $expected = json_encode($expected, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $expectedRaw = trim((string)$expected);

    if ($userAnswerRaw === '' || $expectedRaw === '') {
        return false;
    }

    if (is_numeric($userAnswerRaw) && is_numeric($expectedRaw)) {
        $ua = (float)$userAnswerRaw;
        $ex = (float)$expectedRaw;
        return abs($ua - $ex) < 1e-9;
    }

    return normalizeAnswerText($userAnswerRaw) === normalizeAnswerText($expectedRaw);
}

// Check if array is associative (has string keys) vs indexed
function is_assoc_array($arr) {
    if (!is_array($arr) || empty($arr)) return false;
    $firstKey = array_key_first($arr);
    return is_string($firstKey);
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
$stmt = $conn->prepare('SELECT task_type, question_text, correct_answer, test_cases, max_attempts, iterations_count, min_keywords_required, variable_overrides FROM tasks WHERE id = ?');
$stmt->bind_param('i', $taskId);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();

if (!$task) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Task not found']);
    exit;
}

$assignmentScheduleStmt = $conn->prepare(
    'SELECT
        t.assignment_id,
        a.is_active,
        a.available_from,
        a.due_date AS assignment_due_date,
        a.hard_deadline,
        a.allow_late_submission,
        ua.due_date AS user_due_date
     FROM tasks t
     INNER JOIN assignments a ON a.id = t.assignment_id
     LEFT JOIN user_assignments ua ON ua.assignment_id = a.id AND ua.user_id = ?
     WHERE t.id = ?
     LIMIT 1'
);
$assignmentScheduleStmt->bind_param('ii', $userId, $taskId);
$assignmentScheduleStmt->execute();
$schedule = $assignmentScheduleStmt->get_result()->fetch_assoc();

if (!$schedule) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Assignment context not found']);
    exit;
}

$nowTs = time();
$availableTs = !empty($schedule['available_from']) ? strtotime($schedule['available_from']) : null;
$dueTs = !empty($schedule['user_due_date'])
    ? strtotime($schedule['user_due_date'])
    : (!empty($schedule['assignment_due_date']) ? strtotime($schedule['assignment_due_date']) : null);
$hardTs = !empty($schedule['hard_deadline']) ? strtotime($schedule['hard_deadline']) : null;

if ($dueTs !== null && ($hardTs === null || $hardTs < $dueTs)) {
    $hardTs = $dueTs;
}

if ((int)$schedule['is_active'] !== 1) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Assignment is inactive']);
    exit;
}

if ($availableTs !== null && $nowTs < $availableTs) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Assignment not yet available']);
    exit;
}

if ($hardTs !== null && $nowTs > $hardTs) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Hard deadline passed']);
    exit;
}

$isLateSubmission = ($dueTs !== null && $nowTs > $dueTs);

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
    if (!empty($task['test_cases'])) {
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
                    
                    $answer = normalizeAnswerText($answer, $caseSensitive);
                    $patternToMatch = normalizeAnswerText($patternToMatch, $caseSensitive);
                    
                    switch ($validationMode) {
                        case 'contains':
                            // Substring match
                            $matched = (strpos($answer, $patternToMatch) !== false);
                            break;
                        case 'strict':
                        case 'loose':
                        default:
                            // Compare with normalized casing, commas and whitespace
                            $matched = ($answer === $patternToMatch);
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
    
    // Get task variable overrides
    $stmt = $conn->prepare('SELECT variable_overrides FROM tasks WHERE id = ?');
    $stmt->bind_param('i', $taskId);
    $stmt->execute();
    $taskData = $stmt->get_result()->fetch_assoc();
    
    $isCorrect = false;
    $message = 'Keine Auswertung verfuegbar';
    
    // Get current iteration's override set - NEW SCHEMA: {inputs: {...}, expected: {variable: "x"} OR {value: 42}}
    $overridesArray = [];
    if (!empty($taskData['variable_overrides'])) {
        $overridesArray = json_decode($taskData['variable_overrides'], true) ?? [];
    }
    
    $currentOverrideIndex = $currentIteration - 1;
    $currentOverride = isset($overridesArray[$currentOverrideIndex]) ? $overridesArray[$currentOverrideIndex] : null;
    
    if ($currentOverride && is_array($currentOverride)) {
        // NEW SCHEMA: {inputs: {...}, expected: {...}}
        if (isset($currentOverride['inputs']) && isset($currentOverride['expected'])) {
            $expected = $currentOverride['expected'];
            $expectedValue = null;
            
            // Determine expected value based on expected field
            if (is_array($expected)) {
                if (isset($expected['variable']) && !empty($expected['variable'])) {
                    // MODE 1: Variable mode - Client executed solution_code and extracted variable value
                    // Backend receives computed_value from client
                    $expectedValue = $computedValue;
                } elseif (isset($expected['value'])) {
                    // MODE 2: Direct value mode - Use hardcoded value
                    $expectedValue = $expected['value'];
                }
            }
            
            // Compare student answer with expected value
            if ($expectedValue !== null) {
                $isCorrect = compareAnswers($textAnswer, $expectedValue);
                $message = $isCorrect ? 'Richtig' : 'Falsch';
            } else {
                $message = 'Ergebnis konnte nicht berechnet werden';
            }
        } else {
            // LEGACY SCHEMA: just {inputs: {...}} without expected field
            // In legacy, always use AUTO mode - client sends computed_value
            if ($computedValue !== null) {
                $isCorrect = compareAnswers($textAnswer, $computedValue);
                $message = $isCorrect ? 'Richtig' : 'Falsch';
            }
        }
    } else {
        // No matching override found
        $message = 'Überrides nicht gefunden';
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

    // CODE_RANDOM_COMPLEX uses unified variable_overrides structure (same as CODE_READING)
    // with <random> markers in inputs dict
    // Get task randomizer code and variable_overrides
    $stmt = $conn->prepare('SELECT variable_overrides, randomizer_code FROM tasks WHERE id = ?');
    $stmt->bind_param('i', $taskId);
    $stmt->execute();
    $taskData = $stmt->get_result()->fetch_assoc();

    $isCorrect = false;
    $message = 'Keine Auswertung verfügbar';

    $overridesArray = [];
    if (!empty($taskData['variable_overrides'])) {
        $overridesArray = json_decode($taskData['variable_overrides'], true) ?? [];
    }

    // For CODE_RANDOM_COMPLEX, there should be exactly 1 entry with <random> markers
    if (count($overridesArray) > 0) {
        $override = $overridesArray[0]; // First (and usually only) entry
        
        if (isset($override['inputs']) && isset($override['expected'])) {
            $expectedField = $override['expected'];
            $expectedValue = null;

            // Check if inputs have <random> markers
            $hasRandomInputs = false;
            if (is_array($override['inputs'])) {
                foreach ($override['inputs'] as $val) {
                    if ($val === '<random>' || $val === '<random>') {
                        $hasRandomInputs = true;
                        break;
                    }
                }
            }

            // If random markers detected, randomizer_code must be executed
            if ($hasRandomInputs && !empty($taskData['randomizer_code'])) {
                // Execute randomizer_code in Python to generate actual input values
                // Client sends computed_value from running solution_code with these generated values
                $expectedValue = $computedValue;
            } else {
                // Fallback: no random markers or no randomizer code
                // Use computed_value if variable mode, or hardcoded value if manual mode
                if (isset($expectedField['variable']) && !empty($expectedField['variable'])) {
                    $expectedValue = $computedValue;
                } elseif (isset($expectedField['value'])) {
                    $expectedValue = $expectedField['value'];
                }
            }

            if ($expectedValue !== null) {
                $isCorrect = compareAnswers($textAnswer, $expectedValue);
                $message = $isCorrect ? 'Richtig' : 'Falsch';
            }
        }
    }

    // Fallback if not structured properly
    if ($computedValue !== null && !$isCorrect && !$message) {
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
    $assignmentStatus = $status === 'passed' ? 'submitted' : 'in_progress';
    $assignmentId = (int)$schedule['assignment_id'];
    $assignedByForProgress = (int)$userId;

    $assignmentUpsert = $conn->prepare(
        'INSERT INTO user_assignments (assignment_id, user_id, status, submitted_at, is_late, assigned_by)
         VALUES (?, ?, ?, NOW(), ?, ?)
         ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            submitted_at = NOW(),
            is_late = VALUES(is_late)'
    );
    if ($assignmentUpsert) {
        $lateInt = $isLateSubmission ? 1 : 0;
        $assignmentUpsert->bind_param('iisii', $assignmentId, $userId, $assignmentStatus, $lateInt, $assignedByForProgress);
        $assignmentUpsert->execute();
    }

    $response = [
        'ok' => true,
        'is_correct' => $isCorrect,
        'status' => $status,
        'attempts' => $newAttempts,
        'max_attempts' => $maxAttempts,
        'message' => $message,
        'is_late' => $isLateSubmission,
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
