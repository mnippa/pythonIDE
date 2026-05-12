<?php
/**
 * Migration 052: Add explicit user_assignments flags for late and rework handling.
 *
 * Goal:
 * - Keep legacy status workflow compatible.
 * - Add dedicated flags for simplified display logic:
 *   - is_late (clock icon)
 *   - is_rework (hammer icon)
 *
 * Run via: php sql/migrations/run_052.php
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

    echo "Starting migration 052: add user_assignments flags...\n";

    // Ensure status enum supports existing rework workflows.
    $conn->query(
        "ALTER TABLE user_assignments
         MODIFY COLUMN status ENUM('assigned', 'in_progress', 'rework', 'submitted', 'passed', 'failed')
         NOT NULL DEFAULT 'assigned'"
    );
    echo "✓ Ensured user_assignments.status includes rework\n";

    // Existing column in many environments, but keep migration idempotent.
    if (!columnExists($conn, 'user_assignments', 'is_late')) {
        $conn->query("ALTER TABLE user_assignments ADD COLUMN is_late TINYINT(1) NOT NULL DEFAULT 0 AFTER submitted_at");
        echo "✓ Added user_assignments.is_late\n";
    } else {
        echo "• user_assignments.is_late already exists\n";
    }

    if (!columnExists($conn, 'user_assignments', 'is_rework')) {
        $afterColumn = columnExists($conn, 'user_assignments', 'is_late') ? 'is_late' : 'submitted_at';
        $conn->query("ALTER TABLE user_assignments ADD COLUMN is_rework TINYINT(1) NOT NULL DEFAULT 0 AFTER {$afterColumn}");
        echo "✓ Added user_assignments.is_rework\n";
    } else {
        echo "• user_assignments.is_rework already exists\n";
    }

    if (!indexExists($conn, 'user_assignments', 'idx_user_assignments_user_late')) {
        $conn->query("CREATE INDEX idx_user_assignments_user_late ON user_assignments(user_id, is_late)");
        echo "✓ Added idx_user_assignments_user_late\n";
    } else {
        echo "• idx_user_assignments_user_late already exists\n";
    }

    if (!indexExists($conn, 'user_assignments', 'idx_user_assignments_user_rework')) {
        $conn->query("CREATE INDEX idx_user_assignments_user_rework ON user_assignments(user_id, is_rework)");
        echo "✓ Added idx_user_assignments_user_rework\n";
    } else {
        echo "• idx_user_assignments_user_rework already exists\n";
    }

    echo "\n✅ Migration 052 completed successfully!\n";
    $conn->close();
} catch (Exception $e) {
    echo "\n❌ Migration 052 failed: " . $e->getMessage() . "\n";
    exit(1);
}
