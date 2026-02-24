<?php
/**
 * Sync iterations_count between original and cloned tasks
 * This script identifies tasks that should be identical but have different iterations_count values
 * and synchronizes them.
 */

require_once __DIR__ . '/config/database.php';

$pdo = getPdoConnection();

echo "=== TASK ITERATIONS SYNCHRONIZATION ===\n\n";

// Test: Check if #147 and #155 are identical except for iterations_count
$stmt = $pdo->prepare('
    SELECT 
        id, title, task_type, assignment_id, 
        code_template, solution_code, randomizer_code, 
        test_cases, variable_overrides, 
        iterations_count
    FROM tasks 
    WHERE id IN (147, 155)
    ORDER BY id
');
$stmt->execute();
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($tasks) < 2) {
    echo "❌ One or both tasks not found\n";
    exit(1);
}

$task147 = $tasks[0];
$task155 = $tasks[1];

echo "Task #147 iterations_count: " . ($task147['iterations_count'] === null ? "NULL" : $task147['iterations_count']) . "\n";
echo "Task #155 iterations_count: " . ($task155['iterations_count'] === null ? "NULL" : $task155['iterations_count']) . "\n";
echo "\n";

// Check if they're otherwise identical
$keysToCompare = ['title', 'task_type', 'assignment_id', 'code_template', 'solution_code', 'randomizer_code', 'test_cases', 'variable_overrides'];
$differences = [];

foreach ($keysToCompare as $key) {
    if ($task147[$key] !== $task155[$key]) {
        $differences[$key] = [
            '147' => $task147[$key] !== null ? substr($task147[$key], 0, 50) . (strlen($task147[$key]) > 50 ? "..." : "") : "NULL",
            '155' => $task155[$key] !== null ? substr($task155[$key], 0, 50) . (strlen($task155[$key]) > 50 ? "..." : "") : "NULL"
        ];
    }
}

if (!empty($differences)) {
    echo "❌ Tasks differ in other fields (not just iterations_count):\n";
    foreach ($differences as $field => $values) {
        echo "  $field:\n";
        echo "    #147: {$values['147']}\n";
        echo "    #155: {$values['155']}\n";
    }
} else {
    echo "✅ Tasks are identical except possibly for iterations_count\n\n";
    
    // If #147 has a real iterations_count, sync it to #155
    if ($task147['iterations_count'] !== null && $task147['iterations_count'] > 0) {
        echo "Syncing iterations_count from #147 to #155...\n";
        $updateStmt = $pdo->prepare('UPDATE tasks SET iterations_count = ? WHERE id = ?');
        $updateStmt->execute([$task147['iterations_count'], 155]);
        echo "✅ Updated Task #155 iterations_count to {$task147['iterations_count']}\n";
    } elseif ($task147['iterations_count'] === null && $task155['iterations_count'] !== null) {
        echo "⚠️  Task #147 has NULL iterations_count but #155 has {$task155['iterations_count']}\n";
        echo "This might be correct if #147 was created before iterations_count was required.\n";
    }
}

echo "\n=== SYNC COMPLETE ===\n";
