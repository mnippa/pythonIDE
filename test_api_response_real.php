<?php
/**
 * Test: Login as admin and call the API like the frontend does
 */
session_start();

require 'config/database.php';
$conn = getDbConnection();

// Simulate admin login by getting first admin
$sql = "SELECT id, email, role, first_name, last_name FROM users WHERE role = 'admin' LIMIT 1";
$result = $conn->query($sql);
$admin = $result->fetch_assoc();

if (!$admin) {
    echo "No admin found\n";
    exit;
}

// Set session
$_SESSION['user_id'] = $admin['id'];
$_SESSION['user_email'] = $admin['email'];

echo "Logged in as: {$admin['email']}\n";
echo "Role: {$admin['role']}\n\n";

// Now call the API
include 'api/auth/middleware.php';

// Simulate the request
$_GET['assignment_id'] = 12;
$_GET['test_mode'] = 1;

echo "Calling API with:\n";
echo "- assignment_id=12\n";
echo "- test_mode=1\n\n";

// Read and execute the API
ob_start();
include 'api/tasks/list.php';
$apiOutput = ob_get_clean();

$response = json_decode($apiOutput, true);

if (json_last_error()) {
    echo "JSON Error: " . json_last_error_msg() . "\n";
    echo "Output: " . substr($apiOutput, 0, 500) . "\n";
    exit;
}

// Check tasks 47, 50, 79
$testTasks = [47, 50, 79];
echo "=== API Response for Test Tasks ===\n\n";

foreach ($response['tasks'] ?? [] as $task) {
    if (in_array($task['id'], $testTasks)) {
        echo "Task {$task['id']}: {$task['title']} ({$task['task_type']})\n";
        echo "  solution_code in response: " . (isset($task['solution_code']) ? 'YES' : 'NO') . "\n";
        if (isset($task['solution_code'])) {
            echo "  Length: " . strlen($task['solution_code']) . " chars\n";
        }
        echo "\n";
    }
}
?>
