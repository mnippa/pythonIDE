<?php
require_once __DIR__ . '/config/database.php';

$conn = getDbConnection();

echo "=== User Tasks Columns ===\n";
$result = $conn->query("SHOW COLUMNS FROM user_tasks");
while ($row = $result->fetch_assoc()) {
    echo "{$row['Field']} - {$row['Type']}\n";
}

echo "\n=== User Tasks with active_seconds or run_count > 0 ===\n";
$result = $conn->query("SELECT id, user_id, task_id, status, attempts, run_count, active_seconds FROM user_tasks WHERE active_seconds > 0 OR run_count > 0 LIMIT 10");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: {$row['id']}, User: {$row['user_id']}, Task: {$row['task_id']}, Status: {$row['status']}, Attempts: {$row['attempts']}, Runs: {$row['run_count']}, ActiveSec: {$row['active_seconds']}\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}

echo "\n=== Recent user_tasks entries ===\n";
$result2 = $conn->query("SELECT id, user_id, task_id, status, run_count, active_seconds FROM user_tasks ORDER BY id DESC LIMIT 5");
if ($result2) {
    while ($row = $result2->fetch_assoc()) {
        echo "ID: {$row['id']}, User: {$row['user_id']}, Task: {$row['task_id']}, Status: {$row['status']}, Runs: {$row['run_count']}, ActiveSec: {$row['active_seconds']}\n";
    }
}
?>
