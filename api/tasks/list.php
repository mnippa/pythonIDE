<?php
/**
 * List Tasks API
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

$user = requireAuth();
$conn = getDbConnection();

$assignmentId = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : null;
if (!$assignmentId) {
    jsonResponse(['ok' => false, 'error' => 'Assignment ID required'], 400);
}

// Check assignment access
$stmt = $conn->prepare(
    'SELECT a.is_active, ua.user_id AS assigned_user
     FROM assignments a
     LEFT JOIN user_assignments ua ON ua.assignment_id = a.id AND ua.user_id = ?
     WHERE a.id = ?'
);
$stmt->bind_param('ii', $user['id'], $assignmentId);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();

if (!$assignment) {
    jsonResponse(['ok' => false, 'error' => 'Assignment not found'], 404);
}

$canAccess = $user['role'] === 'admin' || (bool)$assignment['is_active'] || $assignment['assigned_user'] !== null;
if (!$canAccess) {
    jsonResponse(['ok' => false, 'error' => 'Access denied'], 403);
}

$includeExpected = $user['role'] === 'admin' && isset($_GET['include_expected']) && $_GET['include_expected'] === '1';
$isTestMode = isset($_GET['test_mode']) && $_GET['test_mode'] === '1';

// Determine which columns to fetch based on context
$selectColumns = 'id, assignment_id, title, description, position, problem_type, code_template, hint1, hint2, hint3, stoff, max_attempts, iterations_count, show_solution, show_solution_code, test_cases, task_type, task_text, question_text, image_url, correct_answer, variable_overrides';

// Add solution/expected only if needed
// Include in test mode, when include_expected is set, or for admins viewing solutions
$needsSolution = $includeExpected || $isTestMode || ($user['role'] === 'admin');
if ($needsSolution) {
    $selectColumns .= ', expected_output, solution_code, randomizer_code';
}

// Always fetch solution_code and expected_output (needed for intelligent tests)
$sql = "SELECT $selectColumns FROM tasks WHERE assignment_id = ? ORDER BY position ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $assignmentId);
$stmt->execute();
$result = $stmt->get_result();

// First pass: collect all tasks and task IDs for batch loading
$taskIds = [];
$rawTasks = [];
while ($row = $result->fetch_assoc()) {
    $taskIds[] = (int)$row['id'];
    $rawTasks[(int)$row['id']] = $row;
}

// Batch load options for all choice tasks
$choiceTaskIds = [];
foreach ($rawTasks as $taskId => $row) {
    if (in_array($row['task_type'], ['single_choice', 'multiple_choice'])) {
        $choiceTaskIds[] = $taskId;
    }
}

$optionsMap = [];  // taskId => [options]
$userAttemptsMap = [];  // taskId => attempt data

if (!empty($choiceTaskIds)) {
    // Batch load: Get ALL options for ALL choice tasks in ONE query
    $placeholders = implode(',', array_fill(0, count($choiceTaskIds), '?'));
    $optionsStmt = $conn->prepare(
        "SELECT task_id, id, option_text, image_url, is_correct, order_num 
         FROM task_options 
         WHERE task_id IN ($placeholders) 
         ORDER BY task_id, order_num ASC"
    );
    $optionsStmt->bind_param(str_repeat('i', count($choiceTaskIds)), ...$choiceTaskIds);
    $optionsStmt->execute();
    $optionsResult = $optionsStmt->get_result();
    
    while ($optionRow = $optionsResult->fetch_assoc()) {
        $taskId = (int)$optionRow['task_id'];
        if (!isset($optionsMap[$taskId])) {
            $optionsMap[$taskId] = [];
        }
        $optionsMap[$taskId][] = $optionRow;
    }
    $optionsStmt->close();
    
    // Batch load: Get user attempts for ALL choice tasks in ONE query (if not admin)
    if ($user['role'] !== 'admin') {
        $attemptsStmt = $conn->prepare(
            "SELECT task_id, status FROM user_tasks 
             WHERE user_id = ? AND task_id IN ($placeholders)"
        );
        $params = array_merge([$user['id']], $choiceTaskIds);
        $attemptsStmt->bind_param(str_repeat('i', count($params)), ...$params);
        $attemptsStmt->execute();
        $attemptsResult = $attemptsStmt->get_result();
        
        while ($attemptRow = $attemptsResult->fetch_assoc()) {
            $userAttemptsMap[(int)$attemptRow['task_id']] = $attemptRow;
        }
        $attemptsStmt->close();
    }
}

// Second pass: build task array with loaded options
$tasks = [];
foreach ($rawTasks as $taskId => $row) {
    $task = [
        'id' => $taskId,
        'assignment_id' => (int)$row['assignment_id'],
        'title' => $row['title'],
        'description' => $row['description'],
        'position' => (int)$row['position'],
        'problem_type' => $row['problem_type'],
        'code_template' => $row['code_template'],
        'hint1' => $row['hint1'],
        'hint2' => $row['hint2'],
        'hint3' => $row['hint3'],
        'stoff' => $row['stoff'],
        'max_attempts' => (int)$row['max_attempts'],
        'max_iterations' => isset($row['iterations_count']) ? (int)$row['iterations_count'] : null,
        'show_solution' => (int)$row['show_solution'],
        'show_solution_code' => (int)$row['show_solution_code'],
        'test_cases' => $row['test_cases'],
        'task_type' => $row['task_type'],
        'task_text' => $row['task_text'],
        'question_text' => $row['question_text'],
        'image_url' => $row['image_url'],
        'correct_answer' => $row['correct_answer'],
        'variable_overrides' => $row['variable_overrides']
    ];

    if (isset($row['randomizer_code'])) {
        $task['randomizer_code'] = $row['randomizer_code'];
    }
    
    // Include solution_code for testing, admin/expected views, and specific task types
    if ($includeExpected || $isTestMode || 
        $row['task_type'] === 'code' || $row['task_type'] === 'code_random_complex') {
        if (isset($row['expected_output'])) {
            $task['expected_output'] = $row['expected_output'];
        }
        if (isset($row['solution_code'])) {
            $task['solution_code'] = $row['solution_code'];
        }
    }
    
    // Load options for single/multiple choice tasks (from batch-loaded data)
    if (in_array($row['task_type'], ['single_choice', 'multiple_choice'])) {
        $showCorrectAnswers = $user['role'] === 'admin';
        
        if (!$showCorrectAnswers && isset($userAttemptsMap[$taskId])) {
            // User has attempted this task - show correct answers
            $showCorrectAnswers = ($userAttemptsMap[$taskId]['status'] !== 'unbearbeitet');
        }
        
        $options = [];
        if (isset($optionsMap[$taskId])) {
            foreach ($optionsMap[$taskId] as $optionRow) {
                $option = [
                    'id' => (int)$optionRow['id'],
                    'text' => $optionRow['option_text'],
                    'image_url' => $optionRow['image_url'],
                    'order_num' => (int)$optionRow['order_num']
                ];
                
                // Include is_correct if allowed
                if ($showCorrectAnswers) {
                    $option['is_correct'] = (bool)$optionRow['is_correct'];
                }
                
                $options[] = $option;
            }
        }
        
        $task['options'] = $options;
    }
    
    $tasks[] = $task;
}

jsonResponse([
    'ok' => true,
    'assignment_id' => $assignmentId,
    'tasks' => $tasks,
    'count' => count($tasks)
]);
