<?php
/**
 * Migration 063: Add nullable semester to assignments.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

function columnExists(mysqli $conn, string $table, string $column): bool
{
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return ($result instanceof mysqli_result) && $result->num_rows > 0;
}

function indexExists(mysqli $conn, string $table, string $index): bool
{
    $safeTable = $conn->real_escape_string($table);
    $safeIndex = $conn->real_escape_string($index);
    $result = $conn->query("SHOW INDEX FROM `{$safeTable}` WHERE Key_name = '{$safeIndex}'");
    return ($result instanceof mysqli_result) && $result->num_rows > 0;
}

try {
    $conn = getDbConnection();
    echo "Starting migration 063...\n";

    if (!columnExists($conn, 'assignments', 'semester')) {
        $conn->query("ALTER TABLE assignments ADD COLUMN semester VARCHAR(8) NULL AFTER difficulty");
        echo "Added assignments.semester\n";
    } else {
        echo "assignments.semester already exists\n";
    }

    if (!indexExists($conn, 'assignments', 'idx_assignments_semester')) {
        $conn->query("CREATE INDEX idx_assignments_semester ON assignments(semester)");
        echo "Added idx_assignments_semester\n";
    } else {
        echo "idx_assignments_semester already exists\n";
    }

    $conn->query("UPDATE assignments SET semester = 'SS26' WHERE semester IS NULL");
    echo "Assigned assignments without a semester to SS26\n";

    echo "Migration 063 completed successfully.\n";
} catch (Throwable $exception) {
    echo "Migration 063 failed: " . $exception->getMessage() . "\n";
    exit(1);
}