<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

function foreignKeyExists(mysqli $conn, string $constraint): bool
{
    $stmt = $conn->prepare(
        'SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = ?'
    );
    $stmt->bind_param('s', $constraint);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

try {
    $conn = getDbConnection();
    $columnResult = $conn->query("SHOW FULL COLUMNS FROM assignments LIKE 'semester'");
    $column = $columnResult ? $columnResult->fetch_assoc() : null;
    if (!$column) {
        throw new RuntimeException('assignments.semester is missing; run migration 063 first');
    }

    $collation = (string)($column['Collation'] ?? 'utf8mb4_unicode_ci');
    if (preg_match('/^[a-zA-Z0-9_]+$/', $collation) !== 1) {
        throw new RuntimeException('Invalid semester column collation');
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS semesters (
            code VARCHAR(8) COLLATE {$collation} NOT NULL PRIMARY KEY,
            start_date DATE NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE={$collation}"
    );

    $conn->query(
        "INSERT INTO semesters (code, start_date)
         SELECT DISTINCT semester,
             CASE
                 WHEN semester LIKE 'SS__' THEN STR_TO_DATE(CONCAT('20', SUBSTRING(semester, 3, 2), '-03-01'), '%Y-%m-%d')
                 ELSE STR_TO_DATE(CONCAT('20', SUBSTRING(semester, 3, 2), '-10-01'), '%Y-%m-%d')
             END
         FROM assignments
         WHERE semester REGEXP '^(SS[0-9]{2}|WS[0-9]{4})$'
         ON DUPLICATE KEY UPDATE start_date = VALUES(start_date)"
    );
    $conn->query(
        "INSERT IGNORE INTO semesters (code, start_date) VALUES
            ('SS26', '2026-03-01'),
            ('WS2627', '2026-10-01'),
            ('SS27', '2027-03-01'),
            ('WS2728', '2027-10-01'),
            ('SS28', '2028-03-01'),
            ('WS2829', '2028-10-01')"
    );

    if (!foreignKeyExists($conn, 'fk_assignments_semester')) {
        $conn->query(
            'ALTER TABLE assignments
             ADD CONSTRAINT fk_assignments_semester
             FOREIGN KEY (semester) REFERENCES semesters(code)
             ON UPDATE CASCADE ON DELETE SET NULL'
        );
    }

    echo "Migration 064 completed successfully.\n";
} catch (Throwable $exception) {
    echo "Migration 064 failed: " . $exception->getMessage() . "\n";
    exit(1);
}