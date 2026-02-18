<?php
/**
 * Test: Check API response for code tasks in test mode
 */
require 'config/database.php';
require 'api/auth/middleware.php';

// Simulate test_mode=1 request
$_GET['test_mode'] = '1';
$_GET['assignment_id'] = 12;

// Check as admin
echo "=== Simulating Admin API Call (test_mode=1) ===\n\n";

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Simulate admin user
}

$user = ['id' => 1, 'role' => 'admin'];
$conn = getDbConnection();

$assignmentId = 12;
$isTestMode = isset($_GET['test_mode']) && $_GET['test_mode'] === '1';
$includeExpected = false;

echo "isTestMode: " . ($isTestMode ? 'YES' : 'NO') . "\n";
echo "user role: {$user['role']}\n\n";

// Build SQL like the API
$selectColumns = 'id, title, task_type';
$needsSolution = $includeExpected || $isTestMode || ($user['role'] === 'admin');

if ($needsSolution) {
    $selectColumns .= ', solution_code';
}

echo "needsSolution: " . ($needsSolution ? 'YES' : 'NO') . "\n";
echo "Columns included: $selectColumns\n\n";

// Fetch task 47
$sql = "SELECT $selectColumns FROM tasks WHERE id = 47";
$result = $conn->query($sql);
$task = $result->fetch_assoc();

if ($task) {
    echo "Task 47 (code):\n";
    echo "- ID: {$task['id']}\n";
    echo "- Title: {$task['title']}\n";
    echo "- Type: {$task['task_type']}\n";
    echo "- solution_code included: " . (isset($task['solution_code']) ? 'YES' : 'NO') . "\n";
    if (isset($task['solution_code'])) {
        echo "- solution_code value: " . substr($task['solution_code'], 0, 100) . "\n";
    }
} else {
    echo "Task not found\n";
}
?>
