<?php
/**
 * Migration 026: Assignment schedule fields + team assignment defaults.
 * Production-safe and idempotent.
 */

require_once __DIR__ . '/../../config/database.php';

function tableExists(mysqli $conn, string $table): bool {
    $safeTable = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
    return $res && $res->num_rows > 0;
}

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
    echo "Running Migration 026: schedule fields and team defaults...\n";

    if (!columnExists($conn, 'assignments', 'available_from')) {
        $conn->query("ALTER TABLE assignments ADD COLUMN available_from DATETIME NULL AFTER is_active");
        echo "✓ Added assignments.available_from\n";
    }

    if (!columnExists($conn, 'assignments', 'due_date')) {
        $conn->query("ALTER TABLE assignments ADD COLUMN due_date DATETIME NULL AFTER available_from");
        echo "✓ Added assignments.due_date\n";
    }

    if (!columnExists($conn, 'assignments', 'hard_deadline')) {
        $conn->query("ALTER TABLE assignments ADD COLUMN hard_deadline DATETIME NULL AFTER due_date");
        echo "✓ Added assignments.hard_deadline\n";
    }

    if (!columnExists($conn, 'assignments', 'allow_late_submission')) {
        $conn->query("ALTER TABLE assignments ADD COLUMN allow_late_submission TINYINT(1) NOT NULL DEFAULT 1 AFTER hard_deadline");
        echo "✓ Added assignments.allow_late_submission\n";
    }

    if (tableExists($conn, 'user_assignments') && !columnExists($conn, 'user_assignments', 'is_late')) {
        $conn->query("ALTER TABLE user_assignments ADD COLUMN is_late TINYINT(1) NOT NULL DEFAULT 0 AFTER submitted_at");
        echo "✓ Added user_assignments.is_late\n";
    }

    $createDefaultsSql = "CREATE TABLE IF NOT EXISTS team_assignment_defaults (
        id INT AUTO_INCREMENT PRIMARY KEY,
        team_id INT NOT NULL,
        assignment_id INT(10) UNSIGNED NOT NULL,
        assigned_by INT(10) UNSIGNED NULL,
        due_date DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        UNIQUE KEY uq_team_assignment_defaults (team_id, assignment_id),
        KEY idx_tad_team (team_id),
        KEY idx_tad_assignment (assignment_id),
        CONSTRAINT fk_tad_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
        CONSTRAINT fk_tad_assignment FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
        CONSTRAINT fk_tad_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (!$conn->query($createDefaultsSql)) {
        throw new Exception('Failed to create team_assignment_defaults: ' . $conn->error);
    }
    echo "✓ team_assignment_defaults ready\n";

    if (tableExists($conn, 'team_assignment_defaults') && !columnExists($conn, 'team_assignment_defaults', 'due_date')) {
        $conn->query("ALTER TABLE team_assignment_defaults ADD COLUMN due_date DATETIME NULL AFTER assigned_by");
        echo "✓ Added team_assignment_defaults.due_date\n";
    }

    if (tableExists($conn, 'user_assignments') && columnExists($conn, 'user_assignments', 'team_id')) {
        $backfillDefaultsSql = "
            INSERT IGNORE INTO team_assignment_defaults (team_id, assignment_id, assigned_by, due_date, is_active)
            SELECT ua.team_id, ua.assignment_id, ua.assigned_by, ua.due_date, 1
            FROM user_assignments ua
            WHERE ua.team_id IS NOT NULL
        ";
        $conn->query($backfillDefaultsSql);
        echo "✓ Backfilled team_assignment_defaults from existing team assignments\n";

        if (columnExists($conn, 'team_assignment_defaults', 'due_date')) {
            $conn->query("UPDATE team_assignment_defaults tad
                         INNER JOIN assignments a ON a.id = tad.assignment_id
                         SET tad.due_date = COALESCE(tad.due_date, a.due_date)");
            echo "✓ Backfilled team_assignment_defaults.due_date\n";
        }

        $materializeFromDefaultsSql = "
            INSERT IGNORE INTO user_assignments (assignment_id, user_id, assigned_by, due_date, status)
            SELECT tad.assignment_id, u.id, tad.assigned_by, COALESCE(tad.due_date, a.due_date), 'assigned'
            FROM team_assignment_defaults tad
            INNER JOIN users u ON u.team_id = tad.team_id
            INNER JOIN assignments a ON a.id = tad.assignment_id
            WHERE tad.is_active = 1
        ";
        $conn->query($materializeFromDefaultsSql);
        echo "✓ Materialized current team members into user_assignments\n";
    }

    if (tableExists($conn, 'user_assignments') && !indexExists($conn, 'user_assignments', 'uq_user_assignment_direct')) {
        // Cleanup duplicates before adding unique index
        $conn->query("DELETE ua1 FROM user_assignments ua1
                     INNER JOIN user_assignments ua2
                       ON ua1.assignment_id = ua2.assignment_id
                      AND ua1.user_id = ua2.user_id
                      AND ua1.id > ua2.id
                     WHERE ua1.user_id IS NOT NULL");

        $conn->query("ALTER TABLE user_assignments ADD UNIQUE KEY uq_user_assignment_direct (assignment_id, user_id)");
        echo "✓ Added uq_user_assignment_direct\n";
    }

    if (!indexExists($conn, 'assignments', 'idx_assignments_is_active_dates')) {
        $conn->query("CREATE INDEX idx_assignments_is_active_dates ON assignments(is_active, available_from, due_date, hard_deadline)");
        echo "✓ Added idx_assignments_is_active_dates\n";
    }

    if (tableExists($conn, 'user_assignments') && !indexExists($conn, 'user_assignments', 'idx_user_assignments_user_status')) {
        $conn->query("CREATE INDEX idx_user_assignments_user_status ON user_assignments(user_id, status)");
        echo "✓ Added idx_user_assignments_user_status\n";
    }

    if (tableExists($conn, 'user_assignments') && !indexExists($conn, 'user_assignments', 'idx_user_assignments_user_late')) {
        $conn->query("CREATE INDEX idx_user_assignments_user_late ON user_assignments(user_id, is_late)");
        echo "✓ Added idx_user_assignments_user_late\n";
    }

    // Backfill is_late safely from existing submitted_at and due_date data (if any)
    if (columnExists($conn, 'user_assignments', 'submitted_at') && columnExists($conn, 'user_assignments', 'due_date')) {
        $conn->query("UPDATE user_assignments
                     SET is_late = CASE
                         WHEN submitted_at IS NOT NULL AND due_date IS NOT NULL AND submitted_at > due_date THEN 1
                         ELSE 0
                     END");
        echo "✓ Backfilled user_assignments.is_late\n";
    }

    echo "\n✅ Migration 026: Success!\n";
    $conn->close();
} catch (Exception $e) {
    echo "❌ Migration 026 failed: " . $e->getMessage() . "\n";
    exit(1);
}
