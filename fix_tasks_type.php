<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting...\n";

require_once __DIR__ . '/config/database.php';

try {
    $conn = getDbConnection();
    echo "Connected to database.\n\n";
    
    // Check current state
    echo "=== BEFORE UPDATE ===\n";
    $stmt = $conn->prepare("SELECT id, title, task_type FROM tasks WHERE id IN (173, 174)");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $tasks = [];
    while ($row = $result->fetch_assoc()) {
        $tasks[] = $row;
        echo "Task #{$row['id']}: {$row['title']} | Type: '{$row['task_type']}'\n";
    }
    
    if (empty($tasks)) {
        die("\nERROR: No tasks found with IDs 173, 174\n");
    }
    
    // Update
    echo "\n=== UPDATING ===\n";
    $stmt = $conn->prepare("UPDATE tasks SET task_type = 'code_ui' WHERE id IN (173, 174)");
    $success = $stmt->execute();
    $affected = $stmt->affected_rows;
    
    echo "Query executed: " . ($success ? "SUCCESS" : "FAILED") . "\n";
    echo "Rows affected: $affected\n";
    
    // Check new state
    echo "\n=== AFTER UPDATE ===\n";
    $stmt = $conn->prepare("SELECT id, title, task_type FROM tasks WHERE id IN (173, 174)");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        echo "Task #{$row['id']}: {$row['title']} | Type: '{$row['task_type']}'\n";
    }
    
    $conn->close();
    echo "\nDone!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
