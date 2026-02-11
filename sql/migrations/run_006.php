<?php
// Migration runner for migration 006
// Adds active_seconds, last_active_at, is_active columns to user_tasks

require_once __DIR__ . '/../../config/database.php';

try {
    $conn = getDbConnection();
    
    // Check if columns already exist
    $result = $conn->query("SHOW COLUMNS FROM user_tasks WHERE Field IN ('active_seconds', 'last_active_at', 'is_active')");
    
    if ($result && $result->num_rows > 0) {
        echo "Migration 006: Columns already exist. Skipping.\n";
    } else {
        $sql = file_get_contents(__DIR__ . '/006_add_user_tasks_active_time.sql');
        
        if ($conn->multi_query($sql)) {
            echo "Migration 006: Successfully added active time tracking columns.\n";
        } else {
            echo "Migration 006 Error: " . $conn->error . "\n";
        }
        
        // Clear result set
        while ($conn->more_results() && $conn->next_result()) {}
    }
    
    // Don't close pooled connection
} catch (Exception $e) {
    echo "Migration 006 Exception: " . $e->getMessage() . "\n";
}
?>
