<?php
/**
 * Migration 027: Add tasks.task_difficulty enum.
 */

require_once __DIR__ . '/../../config/database.php';

function columnExists(mysqli $conn, string $table, string $column): bool {
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $res && $res->num_rows > 0;
}

function indexExists(mysqli $conn, string $table, string $index): bool {
    $safeTable = $conn->real_escape_string($table);
    $safeIndex = $conn->real_escape_string($index);
    $res = $conn->query("SHOW INDEX FROM `{$safeTable}` WHERE Key_name = '{$safeIndex}'");
    return $res && $res->num_rows > 0;
}

try {
    $conn = getDbConnection();
    echo "Running Migration 027: add task difficulty...\n";

    if (!columnExists($conn, 'tasks', 'task_difficulty')) {
        $sql = "ALTER TABLE tasks ADD COLUMN task_difficulty ENUM('basic', 'medium', 'hard') NOT NULL DEFAULT 'medium' AFTER task_type";
        if (!$conn->query($sql)) {
            throw new Exception('Failed to add tasks.task_difficulty: ' . $conn->error);
        }
        echo "✓ Added tasks.task_difficulty\n";
    } else {
        echo "⚠ tasks.task_difficulty already exists (skipping)\n";
    }

    if (!indexExists($conn, 'tasks', 'idx_tasks_task_difficulty')) {
        if (!$conn->query("CREATE INDEX idx_tasks_task_difficulty ON tasks(task_difficulty)")) {
            throw new Exception('Failed to add idx_tasks_task_difficulty: ' . $conn->error);
        }
        echo "✓ Added idx_tasks_task_difficulty\n";
    } else {
        echo "⚠ idx_tasks_task_difficulty already exists (skipping)\n";
    }

    echo "\n✅ Migration 027: Success!\n";
    $conn->close();
} catch (Exception $e) {
    echo "❌ Migration 027 failed: " . $e->getMessage() . "\n";
    exit(1);
}
