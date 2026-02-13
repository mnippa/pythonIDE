<?php
require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

echo "=== Checking user_tasks table ===\n\n";

// Count total
$result = $conn->query('SELECT COUNT(*) as cnt FROM user_tasks');
$row = $result->fetch_assoc();
echo "Total user_tasks: " . $row['cnt'] . "\n\n";

// Show last 10
echo "Last 10 entries:\n";
$result = $conn->query('SELECT id, user_id, task_id, status, attempts FROM user_tasks ORDER BY id DESC LIMIT 10');
while($r = $result->fetch_assoc()) {
    echo sprintf("ID: %3d, User: %2d, Task: %3d, Status: %-12s, Attempts: %d\n", 
        $r['id'], $r['user_id'], $r['task_id'], $r['status'], $r['attempts']);
}

echo "\n=== Quiz tasks with attempts > 0 ===\n";
$result = $conn->query('
    SELECT ut.id, ut.user_id, ut.task_id, ut.status, ut.attempts, t.task_type
    FROM user_tasks ut
    INNER JOIN tasks t ON t.id = ut.task_id
    WHERE t.task_type IN ("single_choice", "multiple_choice", "free_text", "code_reading")
    AND ut.attempts > 0
    ORDER BY ut.id
');
while($r = $result->fetch_assoc()) {
    echo sprintf("ID: %3d, User: %2d, Task: %3d, Type: %-15s, Status: %-12s, Attempts: %d\n", 
        $r['id'], $r['user_id'], $r['task_id'], $r['task_type'], $r['status'], $r['attempts']);
}
