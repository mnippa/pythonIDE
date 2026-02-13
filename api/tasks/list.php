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

// Always fetch solution_code and expected_output (needed for intelligent tests)
$sql = 'SELECT id, assignment_id, title, description, position, problem_type, code_template, hint, hint1, hint2, hint3, stoff, max_attempts, test_cases, validation_mode, expected_output, solution_code, task_type, question_text, image_url, correct_answer, variable_overrides FROM tasks WHERE assignment_id = ? ORDER BY position ASC';

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $assignmentId);
$stmt->execute();
$result = $stmt->get_result();

$tasks = [];
while ($row = $result->fetch_assoc()) {
    $taskId = (int)$row['id'];
    $task = [
        'id' => $taskId,
        'assignment_id' => (int)$row['assignment_id'],
        'title' => $row['title'],
        'description' => $row['description'],
        'position' => (int)$row['position'],
        'problem_type' => $row['problem_type'],
        'code_template' => $row['code_template'],
        'hint' => $row['hint'],
        'hint1' => $row['hint1'],
        'hint2' => $row['hint2'],
        'hint3' => $row['hint3'],
        'stoff' => $row['stoff'],
        'max_attempts' => (int)$row['max_attempts'],
        'test_cases' => $row['test_cases'],
        'validation_mode' => $row['validation_mode'],
        'task_type' => $row['task_type'],
        'question_text' => $row['question_text'],
        'image_url' => $row['image_url'],
        'correct_answer' => $row['correct_answer'],
        'variable_overrides' => $row['variable_overrides']
    ];
    
    // Include solution_code for intelligent tests (needed for execution)
    // Also include if admin requested it explicitly
    if ($includeExpected || $row['validation_mode'] === 'intelligent') {
        $task['expected_output'] = $row['expected_output'];
        $task['solution_code'] = $row['solution_code'];
    }
    
    // Load options for single/multiple choice tasks
    $taskType = $row['task_type'];
    if (in_array($taskType, ['single_choice', 'multiple_choice'])) {
        // Check once if user has attempted this task
        $showCorrectAnswers = $user['role'] === 'admin';
        
        if (!$showCorrectAnswers) {
            $attemptStmt = $conn->prepare(
                'SELECT status FROM user_tasks WHERE user_id = ? AND task_id = ? LIMIT 1'
            );
            $attemptStmt->bind_param('ii', $user['id'], $taskId);
            $attemptStmt->execute();
            $attemptResult = $attemptStmt->get_result();
            
            if ($attemptResult->num_rows > 0) {
                $attempt = $attemptResult->fetch_assoc();
                // Show correct answers if user has submitted (status is not just 'unbearbeitet')
                $showCorrectAnswers = ($attempt['status'] !== 'unbearbeitet');
            }
            $attemptStmt->close();
        }
        
        $optionsStmt = $conn->prepare(
            'SELECT id, option_text, image_url, is_correct, order_num FROM task_options WHERE task_id = ? ORDER BY order_num ASC'
        );
        $optionsStmt->bind_param('i', $taskId);
        $optionsStmt->execute();
        $optionsResult = $optionsStmt->get_result();
        
        $options = [];
        while ($optionRow = $optionsResult->fetch_assoc()) {
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
