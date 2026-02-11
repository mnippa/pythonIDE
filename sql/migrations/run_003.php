<?php
/**
 * Execute Migration 003: Add status to user_assignments
 * Run via: php sql/migrations/run_003.php
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $conn = getDbConnection();

    echo "Starting migration 003: Add status to user_assignments...\n";

    $conn->query("ALTER TABLE user_assignments ADD COLUMN status ENUM('assigned', 'in_progress', 'submitted', 'passed', 'failed') NOT NULL DEFAULT 'assigned' AFTER assignment_id");

    echo "Creating status index...\n";
    try {
        $conn->query("CREATE INDEX idx_user_assignments_status ON user_assignments(status)");
    } catch (Exception $e) {
    }

    echo "\n✅ Migration 003 completed successfully!\n";
} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
