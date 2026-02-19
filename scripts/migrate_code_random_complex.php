<?php
/**
 * Migration: Enforce generator-only code_random_complex
 * - Clears variable_overrides for code_random_complex
 * - Reports tasks missing values-dict generator code
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

$tasks = [];
$result = $conn->query("SELECT id, title, code_template, variable_overrides FROM tasks WHERE task_type = 'code_random_complex'");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $tasks[] = $row;
    }
}

$cleared = 0;
$missingGenerator = [];
$hasOverrides = 0;

foreach ($tasks as $task) {
    $overrides = $task['variable_overrides'];
    $template = $task['code_template'] ?? '';
    $hasOverridesValue = $overrides !== null && $overrides !== '' && $overrides !== '[]' && $overrides !== '{}';

    if ($hasOverridesValue) {
        $hasOverrides++;
        $stmt = $conn->prepare("UPDATE tasks SET variable_overrides = NULL WHERE id = ?");
        $stmt->bind_param('i', $task['id']);
        if ($stmt->execute()) {
            $cleared++;
        }
        $stmt->close();
    }

    if (!is_string($template) || trim($template) === '' || !preg_match('/\bvalues\b/', $template)) {
        $missingGenerator[] = $task;
    }
}

echo "=== code_random_complex migration ===\n";
echo "Total tasks: " . count($tasks) . "\n";
echo "Overrides cleared: $cleared (had overrides: $hasOverrides)\n";

echo "\nTasks missing generator values-dict (manual fix required):\n";
if (count($missingGenerator) === 0) {
    echo "- none\n";
} else {
    foreach ($missingGenerator as $task) {
        echo "- ID {$task['id']}: {$task['title']}\n";
    }
}

$conn->close();
