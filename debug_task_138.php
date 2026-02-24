<?php
/**
 * Debug Task 138 - Inspect test_cases and output rules
 */

require_once __DIR__ . '/config/database.php';

try {
    $conn = getPdoConnection();
    $stmt = $conn->prepare('SELECT id, title, task_type, test_cases FROM tasks WHERE id = 138');
    $stmt->execute();
    $task = $stmt->fetch();

    if (!$task) {
        echo "Task 138 not found!\n";
        exit(1);
    }

    echo "=== TASK 138 DATA ===\n\n";
    echo "ID: {$task['id']}\n";
    echo "Title: {$task['title']}\n";
    echo "Task Type: {$task['task_type']}\n\n";

    echo "--- TEST CASES (raw) ---\n";
    echo ($task['test_cases'] ?: '(NULL)') . "\n\n";

    if ($task['test_cases']) {
        echo "--- TEST CASES (parsed) ---\n";
        $parsed = json_decode($task['test_cases'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "Invalid JSON: " . json_last_error_msg() . "\n";
        } else {
            print_r($parsed);
        }
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
