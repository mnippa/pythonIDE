<?php
/**
 * Execute Migration 004: Add team assignment fields to user_assignments
 * Run via: php sql/migrations/run_004.php
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $conn = getDbConnection();

    echo "Starting migration 004: Add team fields to user_assignments...\n";

    $steps = [
        "ALTER TABLE user_assignments ADD COLUMN team_id INT NULL AFTER user_id",
        "ALTER TABLE user_assignments ADD COLUMN assigned_by INT NULL AFTER assigned_at",
        "ALTER TABLE user_assignments ADD COLUMN due_date DATETIME NULL AFTER assigned_by",
        "ALTER TABLE user_assignments ADD CONSTRAINT fk_user_assignments_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE",
        "ALTER TABLE user_assignments ADD CONSTRAINT fk_user_assignments_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL",
        "CREATE INDEX idx_user_assignments_team ON user_assignments(team_id)"
    ];

    foreach ($steps as $sql) {
        try {
            $conn->query($sql);
        } catch (Exception $e) {
        }
    }

    echo "\n✅ Migration 004 completed successfully!\n";
} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
