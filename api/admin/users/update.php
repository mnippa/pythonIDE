<?php
/**
 * Admin: Update user status
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../auth/middleware.php';

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

$userId = isset($input['id']) ? (int)$input['id'] : null;
$status = isset($input['status']) ? $input['status'] : null;

if (!$userId || !$status) {
    jsonResponse(['ok' => false, 'error' => 'User ID and status required'], 400);
}

$allowed = ['aktiv', 'archiviert'];
if (!in_array($status, $allowed, true)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid status'], 400);
}

$stmt = $conn->prepare('SELECT id FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    jsonResponse(['ok' => false, 'error' => 'User not found'], 404);
}

$stmt = $conn->prepare('UPDATE users SET status = ? WHERE id = ?');
$stmt->bind_param('si', $status, $userId);

if ($stmt->execute()) {
    jsonResponse(['ok' => true, 'message' => 'User updated', 'status' => $status]);
} else {
    jsonResponse(['ok' => false, 'error' => 'Failed to update user'], 500);
}
