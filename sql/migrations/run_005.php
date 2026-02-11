<?php
/**
 * Execute Migration 005: Add run_count to user_tasks
 * Run via: php sql/migrations/run_005.php
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $conn = getDbConnection();

    echo "Starting migration 005: Add run_count to user_tasks...\n";

    $steps = [
        "ALTER TABLE user_tasks ADD COLUMN run_count INT NOT NULL DEFAULT 0 AFTER attempts",
        "CREATE INDEX idx_user_tasks_run_count ON user_tasks(run_count)"
    ];

    foreach ($steps as $sql) {
        try {
            $conn->query($sql);
        } catch (Exception $e) {
        }
    }

    echo "\n✅ Migration 005 completed successfully!\n";
} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
