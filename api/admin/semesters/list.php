<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../auth/middleware.php';
require_once __DIR__ . '/../../../components/assignment_semesters.php';

header('Content-Type: application/json; charset=utf-8');

requireAdmin();
$conn = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}

try {
    jsonResponse([
        'ok' => true,
        'semesters' => listAssignmentSemesters($conn),
    ]);
} catch (Throwable $exception) {
    error_log('Semester list failed: ' . $exception->getMessage());
    jsonResponse(['ok' => false, 'error' => 'Failed to load semesters'], 500);
}