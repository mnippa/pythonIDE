<?php
/**
 * Admin: Update assignment status for a user
 * POST { assignment_id, user_id, status }
 */

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../auth/middleware.php';

header('Content-Type: application/json');

try {
    $admin = requireAdmin();
    $conn = getDbConnection();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(['ok' => false, 'error' => 'Invalid JSON'], 400);
    }

    $assignmentId = isset($input['assignment_id']) ? (int)$input['assignment_id'] : null;
    $userId = isset($input['user_id']) ? (int)$input['user_id'] : null;
    $status = $input['status'] ?? null;

    // passed_delayed is not a DB value; it maps to passed + is_late=1
    $isLate = null; // null = do not touch is_late
    if ($status === 'passed_delayed') {
        $status = 'passed';
        $isLate = 1;
    } elseif ($status === 'passed') {
        $isLate = 0; // explicitly clear is_late when setting plain passed
    }

    // General alias map for other lifecycle labels that may arrive
    $statusAliasMap = [
        'completed'    => 'submitted',
        'late_completed' => 'submitted',
        'missed'       => 'failed',
    ];
    if (isset($statusAliasMap[$status])) {
        $status = $statusAliasMap[$status];
    }

    $allowedStatus = ['assigned', 'in_progress', 'submitted', 'passed', 'failed'];
    if (!$assignmentId || !$userId) {
        jsonResponse(['ok' => false, 'error' => 'assignment_id and user_id required'], 400);
    }

    if (!in_array($status, $allowedStatus, true)) {
        jsonResponse(['ok' => false, 'error' => 'Invalid status'], 400);
    }

    $stmt = $conn->prepare('SELECT id FROM user_assignments WHERE user_id = ? AND assignment_id = ?');
    $stmt->bind_param('ii', $userId, $assignmentId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();

    if ($existing) {
        if ($isLate !== null) {
            $update = $conn->prepare('UPDATE user_assignments SET status = ?, is_late = ? WHERE id = ?');
            $update->bind_param('sii', $status, $isLate, $existing['id']);
        } else {
            $update = $conn->prepare('UPDATE user_assignments SET status = ? WHERE id = ?');
            $update->bind_param('si', $status, $existing['id']);
        }
        if ($update->execute()) {
            jsonResponse(['ok' => true, 'updated' => true]);
        }
        jsonResponse(['ok' => false, 'error' => 'Failed to update status'], 500);
    }

    $insert = $conn->prepare('INSERT INTO user_assignments (user_id, assignment_id, status, assigned_by) VALUES (?, ?, ?, ?)');
    $adminId = (int)$admin['id'];
    $insert->bind_param('iisi', $userId, $assignmentId, $status, $adminId);

    if ($insert->execute()) {
        jsonResponse(['ok' => true, 'created' => true]);
    }

    jsonResponse(['ok' => false, 'error' => 'Failed to create assignment status'], 500);
} catch (Exception $e) {
    error_log('Assignment status update error: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'Failed to update assignment status'], 500);
}
