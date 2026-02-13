<?php
/**
 * Test tasks/list.php API directly
 */
session_start();
$_SESSION['user_id'] = 6; // Fake login as user 6

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/auth/middleware.php';

$user = requireAuth();
$conn = getDbConnection();

echo "Testing as User: {$user['id']} (role: {$user['role']})\n\n";

$assignmentId = 7;

// Simulate the API call
$stmt = $conn->prepare('SELECT id, task_type FROM tasks WHERE assignment_id = ? AND task_type = "single_choice" LIMIT 1');
$stmt->bind_param('i', $assignmentId);
$stmt->execute();
$result = $stmt->get_result();
$task = $result->fetch_assoc();

if (!$task) {
    echo "No single_choice task found\n";
    exit;
}

$taskId = $task['id'];
$taskType = $task['task_type'];

echo "Testing Task ID: $taskId (type: $taskType)\n\n";

// Check once if user has attempted this task
$showCorrectAnswers = $user['role'] === 'admin';

echo "Is Admin? " . ($user['role'] === 'admin' ? 'YES' : 'NO') . "\n";
echo "showCorrectAnswers (initial): " . ($showCorrectAnswers ? 'TRUE' : 'FALSE') . "\n\n";

if (!$showCorrectAnswers) {
    $attemptStmt = $conn->prepare(
        'SELECT status FROM user_tasks WHERE user_id = ? AND task_id = ? LIMIT 1'
    );
    $attemptStmt->bind_param('ii', $user['id'], $taskId);
    $attemptStmt->execute();
    $attemptResult = $attemptStmt->get_result();
    
    echo "user_tasks query result rows: " . $attemptResult->num_rows . "\n";
    
    if ($attemptResult->num_rows > 0) {
        $attempt = $attemptResult->fetch_assoc();
        echo "Found attempt: status='{$attempt['status']}'\n";
        echo "Status !== 'unbearbeitet'? " . ($attempt['status'] !== 'unbearbeitet' ? 'YES' : 'NO') . "\n";
        // Show correct answers if user has submitted (status is not just 'unbearbeitet')
        $showCorrectAnswers = ($attempt['status'] !== 'unbearbeitet');
    } else {
        echo "No attempt found\n";
    }
    $attemptStmt->close();
}

echo "\nFinal showCorrectAnswers: " . ($showCorrectAnswers ? 'TRUE' : 'FALSE') . "\n\n";

// Load options
$optionsStmt = $conn->prepare(
    'SELECT id, option_text, is_correct FROM task_options WHERE task_id = ? ORDER BY order_num ASC'
);
$optionsStmt->bind_param('i', $taskId);
$optionsStmt->execute();
$optionsResult = $optionsStmt->get_result();

echo "Options:\n";
while ($optionRow = $optionsResult->fetch_assoc()) {
    $option = [
        'id' => (int)$optionRow['id'],
        'text' => $optionRow['option_text']
    ];
    
    // Include is_correct if allowed
    if ($showCorrectAnswers) {
        $option['is_correct'] = (bool)$optionRow['is_correct'];
    }
    
    echo "  Option {$option['id']}: {$option['text']}";
    if (isset($option['is_correct'])) {
        echo " [is_correct: " . ($option['is_correct'] ? 'true' : 'false') . "]";
    } else {
        echo " [is_correct: NOT INCLUDED]";
    }
    echo "\n";
}
