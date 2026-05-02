<?php
/**
 * Migration 051: Add explicit rework status to user_assignments.status enum.
 * Run via: php sql/migrations/run_051.php
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $conn = getDbConnection();

    echo "Starting migration 051: add rework to user_assignments.status...\n";

    $conn->query(
        "ALTER TABLE user_assignments
         MODIFY COLUMN status ENUM('assigned', 'in_progress', 'rework', 'submitted', 'passed', 'failed')
         NOT NULL DEFAULT 'assigned'"
    );

    echo "\n✅ Migration 051 completed successfully!\n";
    $conn->close();
} catch (Exception $e) {
    echo "\n❌ Migration 051 failed: " . $e->getMessage() . "\n";
    exit(1);
}
