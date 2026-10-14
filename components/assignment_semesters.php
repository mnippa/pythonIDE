<?php

declare(strict_types=1);

function normalizeAssignmentSemester($value): ?string
{
    if ($value === null || trim((string)$value) === '') {
        return null;
    }

    $semester = strtoupper(trim((string)$value));
    return preg_match('/^(WS\d{4}|SS\d{2})$/', $semester) === 1 ? $semester : null;
}

function assignmentSemesterStartDate(string $semester): string
{
    if (preg_match('/^SS(\d{2})$/', $semester, $matches) === 1) {
        return '20' . $matches[1] . '-03-01';
    }
    if (preg_match('/^WS(\d{2})\d{2}$/', $semester, $matches) === 1) {
        return '20' . $matches[1] . '-10-01';
    }
    throw new InvalidArgumentException('Invalid semester code');
}

function assignmentSemestersTableExists(mysqli $conn): bool
{
    $result = $conn->query("SHOW TABLES LIKE 'semesters'");
    return ($result instanceof mysqli_result) && $result->num_rows > 0;
}

function registerAssignmentSemester(mysqli $conn, ?string $semester): void
{
    if ($semester === null || !assignmentSemestersTableExists($conn)) {
        return;
    }

    $startDate = assignmentSemesterStartDate($semester);
    $stmt = $conn->prepare(
        'INSERT INTO semesters (code, start_date)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE start_date = VALUES(start_date)'
    );
    $stmt->bind_param('ss', $semester, $startDate);
    if (!$stmt->execute()) {
        throw new RuntimeException('Failed to register semester: ' . $stmt->error);
    }
}

function listAssignmentSemesters(mysqli $conn): array
{
    if (assignmentSemestersTableExists($conn)) {
        $result = $conn->query('SELECT code, start_date FROM semesters ORDER BY start_date DESC, code DESC');
    } else {
        $result = $conn->query(
            'SELECT DISTINCT semester AS code, NULL AS start_date
             FROM assignments
             WHERE semester IS NOT NULL AND semester <> ""
             ORDER BY semester DESC'
        );
    }

    if (!$result) {
        throw new RuntimeException('Failed to list semesters: ' . $conn->error);
    }
    return $result->fetch_all(MYSQLI_ASSOC);
}