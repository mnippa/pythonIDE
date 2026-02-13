<?php
require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

echo "=== Finding duplicate user_tasks ===\n\n";

$result = $conn->query('
    SELECT user_id, task_id, COUNT(*) as cnt
    FROM user_tasks
    GROUP BY user_id, task_id
    HAVING cnt > 1
    ORDER BY cnt DESC
');

$duplicates = [];
while($r = $result->fetch_assoc()) {
    echo sprintf("User %d, Task %d: %d entries\n", $r['user_id'], $r['task_id'], $r['cnt']);
    $duplicates[] = ['user_id' => $r['user_id'], 'task_id' => $r['task_id']];
}

if (count($duplicates) > 0) {
    echo "\n=== Cleaning duplicates (keeping newest) ===\n";
    
    foreach ($duplicates as $dup) {
        // Get all IDs for this user/task
        $stmt = $conn->prepare('SELECT id FROM user_tasks WHERE user_id = ? AND task_id = ? ORDER BY id DESC');
        $stmt->bind_param('ii', $dup['user_id'], $dup['task_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $ids = [];
        while($r = $result->fetch_assoc()) {
            $ids[] = $r['id'];
        }
        
        // Keep the first (newest), delete the rest
        $keepId = array_shift($ids);
        
        if (count($ids) > 0) {
            $deleteIds = implode(',', $ids);
            $conn->query("DELETE FROM user_tasks WHERE id IN ($deleteIds)");
            echo sprintf("  User %d, Task %d: Kept ID %d, deleted %d old entries\n", 
                $dup['user_id'], $dup['task_id'], $keepId, count($ids));
        }
    }
    
    echo "\n✓ Cleanup complete!\n";
} else {
    echo "No duplicates found.\n";
}
