<?php
/**
 * Assign user to assignment (Admin only)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

$user = requireAdmin();
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
$email = isset($input['email']) ? trim($input['email']) : null;
$status = $input['status'] ?? 'assigned';

$allowedStatus = ['assigned', 'in_progress', 'submitted', 'passed', 'failed'];
if (!in_array($status, $allowedStatus, true)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid status'], 400);
}

if (!$assignmentId) {
    jsonResponse(['ok' => false, 'error' => 'Assignment ID required'], 400);
}

if (!$userId && !$email) {
    jsonResponse(['ok' => false, 'error' => 'User ID or email required'], 400);
}

$stmt = $conn->prepare('SELECT id FROM assignments WHERE id = ?');
$stmt->bind_param('i', $assignmentId);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    jsonResponse(['ok' => false, 'error' => 'Assignment not found'], 404);
}

if (!$userId && $email) {
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        jsonResponse(['ok' => false, 'error' => 'User not found'], 404);
    }
    $userId = (int)$row['id'];
}

$stmt = $conn->prepare('SELECT id, status FROM user_assignments WHERE user_id = ? AND assignment_id = ?');
$stmt->bind_param('ii', $userId, $assignmentId);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();

if ($existing) {
    jsonResponse([
        'ok' => false,
        'error' => 'User already assigned',
        'assignment_id' => $assignmentId,
        'user_id' => $userId,
        'current_status' => $existing['status']
    ], 409);
}

$stmt = $conn->prepare(
    'INSERT INTO user_assignments (user_id, assignment_id, status) VALUES (?, ?, ?)'
);
$stmt->bind_param('iis', $userId, $assignmentId, $status);

if ($stmt->execute()) {
    jsonResponse([
        'ok' => true,
        'assignment_id' => $assignmentId,
        'user_id' => $userId,
        'status' => $status
    ], 201);
} else {
    jsonResponse(['ok' => false, 'error' => 'Failed to assign user'], 500);
}
