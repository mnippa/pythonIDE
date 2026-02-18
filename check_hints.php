<?php
require 'config/database.php';
$conn = getDbConnection();

echo "=== Test Tasks - Hints Status ===\n\n";

foreach ([47, 50, 79] as $taskId) {
    $sql = "SELECT id, title, task_type, hint1, hint2, hint3 FROM tasks WHERE id = $taskId";
    $result = $conn->query($sql);
    $task = $result->fetch_assoc();
    
    if ($task) {
        echo "Task {$taskId}: {$task['title']}\n";
        echo "  Type: {$task['task_type']}\n";
        echo "  Hint1: " . ($task['hint1'] ? 'YES (' . strlen($task['hint1']) . ')' : 'NO') . "\n";
        echo "  Hint2: " . ($task['hint2'] ? 'YES (' . strlen($task['hint2']) . ')' : 'NO') . "\n";
        echo "  Hint3: " . ($task['hint3'] ? 'YES (' . strlen($task['hint3']) . ')' : 'NO') . "\n";
        
        $hasHints = !!($task['hint1'] || $task['hint2'] || $task['hint3']);
        echo "  Status: " . ($hasHints ? '✓ Has hints' : '⚠ No hints') . "\n\n";
    }
}
?>
